<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Client;
use App\Models\InventoryItem;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Quotation;
use App\Models\Setting;
use App\Models\User;
use App\Services\SystemData\DemoDatasetGenerator;
use App\Services\SystemData\SystemDataExporter;
use App\Services\SystemData\SystemDataImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;
use ZipArchive;

class AdminSystemDataManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Raflora Admin',
            'first_name' => 'Raflora',
            'last_name' => 'Admin',
            'email' => 'admin@raflora.com',
            'password' => bcrypt('AdminPassword123!'),
            'role' => 'admin',
            'is_bootstrap' => false,
        ]);

        $this->staff = User::create([
            'name' => 'Raflora Staff',
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'email' => 'staff@raflora.com',
            'password' => bcrypt('StaffPassword123!'),
            'role' => 'staff',
            'is_bootstrap' => false,
        ]);
    }

    /**
     * Helper to create a seeded business record set for export tests.
     */
    protected function seedBusinessData(): void
    {
        $client = Client::create([
            'full_name' => 'Export Client',
            'email' => 'export.client@raflora.local',
            'phone' => '09171234567',
            'address' => 'Sample Address',
        ]);

        $item1 = InventoryItem::create([
            'item_code' => 'FLW-0001',
            'name' => 'Export Red Roses',
            'category' => 'Flowers',
            'is_perishable' => true,
            'current_stock' => 100,
            'unit_cost' => 15.00,
            'min_stock' => 20,
            'unit' => 'stems',
        ]);

        $pkg = Package::create([
            'package_code' => 'PKG-EXP-01',
            'title' => 'Export Package',
            'category' => 'Wedding',
            'description' => 'Test package for export',
            'price' => 5000.00,
            'included_items' => ['10x Export Red Roses'],
            'is_active' => true,
        ]);

        $pkg->inventoryItems()->attach($item1->id, ['quantity' => 10]);

        $booking = Booking::create([
            'client_id' => $client->id,
            'package_id' => $pkg->id,
            'handled_by' => $this->admin->id,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(30)->toDateString(),
            'venue' => 'Export Venue',
            'status' => 'quotation_sent',
            'total_quoted' => 5000.00,
            'final_quoted_price' => 5000.00,
            'multiplier' => 3.0,
        ]);

        BookingItem::create([
            'booking_id' => $booking->id,
            'inventory_item_id' => $item1->id,
            'item_name' => 'Export Red Roses',
            'quantity' => 10,
            'quoted_unit_price' => 45.00,
            'is_ai_suggested' => false,
            'procurement_status' => 'confirmed',
        ]);

        Quotation::create([
            'booking_id' => $booking->id,
            'issued_by' => $this->admin->id,
            'version' => 1,
            'status' => 'issued',
            'final_quoted_price' => 5000.00,
            'valid_until' => now()->addDays(7)->toDateString(),
        ]);
    }

    // =========================================================================
    // EXPORT TESTS
    // =========================================================================

    public function test_admin_can_export_allowed_business_data(): void
    {
        $this->seedBusinessData();

        $response = $this->actingAs($this->admin)->get(route('admin.system-data.export'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/zip');
        $this->assertStringContainsString('raflora-system-data-', $response->headers->get('content-disposition'));
    }

    public function test_export_contains_manifest_and_expected_entity_files(): void
    {
        $this->seedBusinessData();

        $exporter = new SystemDataExporter();
        $zipPath = $exporter->export();

        $this->assertFileExists($zipPath);

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($zipPath));

        $this->assertNotFalse($zip->locateName('manifest.json'));
        $this->assertNotFalse($zip->locateName('data/clients.json'));
        $this->assertNotFalse($zip->locateName('data/inventory_items.json'));
        $this->assertNotFalse($zip->locateName('data/packages.json'));
        $this->assertNotFalse($zip->locateName('data/package_materials.json'));
        $this->assertNotFalse($zip->locateName('data/bookings.json'));
        $this->assertNotFalse($zip->locateName('data/booking_items.json'));
        $this->assertNotFalse($zip->locateName('data/quotations.json'));
        $this->assertNotFalse($zip->locateName('data/users.json'));

        // Check manifest contents
        $manifestJson = $zip->getFromName('manifest.json');
        $manifest = json_decode($manifestJson, true);

        $this->assertSame('raflora-system-data', $manifest['format']);
        $this->assertSame(1, $manifest['format_version']);
        $this->assertSame('business_export', $manifest['data_mode']);
        $this->assertNotEmpty($manifest['schema_fingerprint']);
        $this->assertGreaterThan(0, $manifest['entities']['clients']);

        $zip->close();
        @unlink($zipPath);
    }

    public function test_export_excludes_secrets_passwords_tokens_and_hashes(): void
    {
        $this->seedBusinessData();

        $exporter = new SystemDataExporter();
        $zipPath = $exporter->export();

        $zip = new ZipArchive();
        $zip->open($zipPath);

        // Check users.json does not contain passwords
        $usersJson = $zip->getFromName('data/users.json');
        $this->assertStringNotContainsString('password', $usersJson);
        $this->assertStringNotContainsString('$2y$', $usersJson);
        $this->assertStringNotContainsString('remember_token', $usersJson);

        // Check bookings.json does not contain guest_access_token
        $bookingsJson = $zip->getFromName('data/bookings.json');
        $this->assertStringNotContainsString('guest_access_token', $bookingsJson);

        // Check no security/runtime tables exist in the archive
        $this->assertFalse($zip->locateName('data/sessions.json'));
        $this->assertFalse($zip->locateName('data/password_resets.json'));
        $this->assertFalse($zip->locateName('data/admin_recovery_codes.json'));
        $this->assertFalse($zip->locateName('data/trusted_devices.json'));

        $zip->close();
        @unlink($zipPath);
    }

    // =========================================================================
    // IMPORT & PREVIEW TESTS
    // =========================================================================

    public function test_admin_can_upload_and_preview_valid_dataset_without_database_mutations(): void
    {
        $generator = new DemoDatasetGenerator();
        $demoZipPath = $generator->generate();

        $initialBookingsCount = Booking::count();
        $initialClientsCount = Client::count();

        $file = new UploadedFile($demoZipPath, 'demo.zip', 'application/zip', null, true);

        $response = $this->actingAs($this->admin)->postJson(route('admin.system-data.preview'), [
            'dataset_file' => $file,
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'preview' => [
                'isValid' => true,
            ],
        ]);

        // CRITICAL: Preview must make 0 database mutations
        $this->assertSame($initialBookingsCount, Booking::count());
        $this->assertSame($initialClientsCount, Client::count());

        @unlink($demoZipPath);
    }

    public function test_invalid_archive_is_rejected(): void
    {
        $fakeFile = UploadedFile::fake()->create('invalid.zip', 10, 'application/zip');

        $response = $this->actingAs($this->admin)->postJson(route('admin.system-data.preview'), [
            'dataset_file' => $fakeFile,
        ]);

        $response->assertStatus(422);
    }

    public function test_missing_required_entity_file_is_rejected(): void
    {
        // Build zip with manifest but missing bookings.json
        $tempDir = storage_path('app/tmp_system_data/test_' . Str::random(8));
        File::ensureDirectoryExists($tempDir . '/data');

        File::put($tempDir . '/manifest.json', json_encode([
            'format' => 'raflora-system-data',
            'format_version' => 1,
            'dataset_id' => 'test-incomplete',
            'data_mode' => 'test',
        ]));
        File::put($tempDir . '/data/clients.json', '[]');

        $zipPath = storage_path('app/tmp_system_data/incomplete.zip');
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFile($tempDir . '/manifest.json', 'manifest.json');
        $zip->addFile($tempDir . '/data/clients.json', 'data/clients.json');
        $zip->close();

        File::deleteDirectory($tempDir);

        $file = new UploadedFile($zipPath, 'incomplete.zip', 'application/zip', null, true);

        $response = $this->actingAs($this->admin)->postJson(route('admin.system-data.preview'), [
            'dataset_file' => $file,
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['Missing required entity file: data/inventory_items.json.']);

        @unlink($zipPath);
    }

    public function test_duplicate_dataset_references_are_rejected(): void
    {
        $generator = new DemoDatasetGenerator();
        $demoZipPath = $generator->generate();

        // Unpack, inject duplicate key into clients.json, repack
        $tempDir = storage_path('app/tmp_system_data/test_dup_' . Str::random(8));
        $zip = new ZipArchive();
        $zip->open($demoZipPath);
        $zip->extractTo($tempDir);
        $zip->close();
        @unlink($demoZipPath);

        $clients = json_decode(File::get($tempDir . '/data/clients.json'), true);
        $clients[] = [
            'key' => 'client-001', // duplicate key!
            'full_name' => 'Duplicate Client',
            'email' => 'duplicate@demo.com',
        ];
        File::put($tempDir . '/data/clients.json', json_encode($clients));

        $badZipPath = storage_path('app/tmp_system_data/dup_keys.zip');
        $zip2 = new ZipArchive();
        $zip2->open($badZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip2->addFile($tempDir . '/manifest.json', 'manifest.json');
        foreach (File::files($tempDir . '/data') as $f) {
            $zip2->addFile($f->getPathname(), 'data/' . $f->getFilename());
        }
        $zip2->close();
        File::deleteDirectory($tempDir);

        $file = new UploadedFile($badZipPath, 'dup_keys.zip', 'application/zip', null, true);

        $response = $this->actingAs($this->admin)->postJson(route('admin.system-data.preview'), [
            'dataset_file' => $file,
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(["Duplicate dataset key 'client-001' in data/clients.json."]);

        @unlink($badZipPath);
    }

    public function test_missing_relation_reference_is_rejected(): void
    {
        $generator = new DemoDatasetGenerator();
        $demoZipPath = $generator->generate();

        $tempDir = storage_path('app/tmp_system_data/test_orphan_' . Str::random(8));
        $zip = new ZipArchive();
        $zip->open($demoZipPath);
        $zip->extractTo($tempDir);
        $zip->close();
        @unlink($demoZipPath);

        // Make a booking point to non-existent client
        $bookings = json_decode(File::get($tempDir . '/data/bookings.json'), true);
        $bookings[0]['client_ref'] = 'client-non-existent-999';
        File::put($tempDir . '/data/bookings.json', json_encode($bookings));

        $badZipPath = storage_path('app/tmp_system_data/orphan_ref.zip');
        $zip2 = new ZipArchive();
        $zip2->open($badZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip2->addFile($tempDir . '/manifest.json', 'manifest.json');
        foreach (File::files($tempDir . '/data') as $f) {
            $zip2->addFile($f->getPathname(), 'data/' . $f->getFilename());
        }
        $zip2->close();
        File::deleteDirectory($tempDir);

        $file = new UploadedFile($badZipPath, 'orphan_ref.zip', 'application/zip', null, true);

        $response = $this->actingAs($this->admin)->postJson(route('admin.system-data.preview'), [
            'dataset_file' => $file,
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(["Booking '{$bookings[0]['key']}' references non-existent client 'client-non-existent-999'."]);

        @unlink($badZipPath);
    }

    public function test_invalid_enum_status_is_rejected(): void
    {
        $generator = new DemoDatasetGenerator();
        $demoZipPath = $generator->generate();

        $tempDir = storage_path('app/tmp_system_data/test_status_' . Str::random(8));
        $zip = new ZipArchive();
        $zip->open($demoZipPath);
        $zip->extractTo($tempDir);
        $zip->close();
        @unlink($demoZipPath);

        $bookings = json_decode(File::get($tempDir . '/data/bookings.json'), true);
        $bookings[0]['status'] = 'invalid_invented_status';
        File::put($tempDir . '/data/bookings.json', json_encode($bookings));

        $badZipPath = storage_path('app/tmp_system_data/invalid_status.zip');
        $zip2 = new ZipArchive();
        $zip2->open($badZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip2->addFile($tempDir . '/manifest.json', 'manifest.json');
        foreach (File::files($tempDir . '/data') as $f) {
            $zip2->addFile($f->getPathname(), 'data/' . $f->getFilename());
        }
        $zip2->close();
        File::deleteDirectory($tempDir);

        $file = new UploadedFile($badZipPath, 'invalid_status.zip', 'application/zip', null, true);

        $response = $this->actingAs($this->admin)->postJson(route('admin.system-data.preview'), [
            'dataset_file' => $file,
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(["Booking '{$bookings[0]['key']}' has invalid status 'invalid_invented_status'."]);

        @unlink($badZipPath);
    }

    public function test_atomic_import_commits_all_records_and_preserves_relationships(): void
    {
        $generator = new DemoDatasetGenerator();
        $demoZipPath = $generator->generate();

        $file = new UploadedFile($demoZipPath, 'demo.zip', 'application/zip', null, true);

        // 1. Preview
        $previewResponse = $this->actingAs($this->admin)->postJson(route('admin.system-data.preview'), [
            'dataset_file' => $file,
        ]);

        $previewResponse->assertOk();
        $token = $previewResponse->json('preview.preview_token');
        $this->assertNotEmpty($token);

        // 2. Commit import
        $importResponse = $this->actingAs($this->admin)->postJson(route('admin.system-data.import'), [
            'preview_token' => $token,
        ]);

        $importResponse->assertOk();
        $importResponse->assertJson(['success' => true]);

        // 3. Verify database state
        $this->assertGreaterThan(0, Client::count());
        $this->assertGreaterThan(0, InventoryItem::count());
        $this->assertGreaterThan(0, Package::count());
        $this->assertGreaterThan(0, Booking::count());
        $this->assertGreaterThan(0, BookingItem::count());
        $this->assertGreaterThan(0, Quotation::count());
        $this->assertGreaterThan(0, Payment::count());
        $this->assertGreaterThan(0, DB::table('returns')->count());
        $this->assertGreaterThan(0, DB::table('return_items')->count());
        $this->assertGreaterThan(0, DB::table('inventory_transactions')->count());

        // 4. Verify relations connect properly
        $booking = Booking::with(['client', 'package', 'bookingItems', 'quotations', 'payments', 'returns'])->where('status', 'completed')->first();
        $this->assertNotNull($booking);
        $this->assertNotNull($booking->client);
        $this->assertNotNull($booking->package);
        $this->assertNotEmpty($booking->bookingItems);
        $this->assertNotEmpty($booking->quotations);
        $this->assertNotEmpty($booking->payments);
        $this->assertNotEmpty($booking->returns);

        // Verify audit log recorded
        $this->assertTrue(AuditLog::where('action', 'system_data_imported')->exists());

        @unlink($demoZipPath);
    }

    public function test_failed_import_is_atomic_and_leaves_database_unchanged(): void
    {
        $initialClientsCount = Client::count();
        $initialBookingsCount = Booking::count();

        // Create an invalid session token
        $response = $this->actingAs($this->admin)->postJson(route('admin.system-data.import'), [
            'preview_token' => (string) Str::uuid(),
        ]);

        $response->assertStatus(404);

        $this->assertSame($initialClientsCount, Client::count());
        $this->assertSame($initialBookingsCount, Booking::count());
    }

    public function test_unauthorized_user_cannot_access_import_export(): void
    {
        // Staff user is forbidden
        $this->actingAs($this->staff)->get(route('admin.system-data.export'))->assertForbidden();
        $this->actingAs($this->staff)->get(route('admin.system-data.demo'))->assertForbidden();
        $this->actingAs($this->staff)->post(route('admin.system-data.preview'))->assertForbidden();
        $this->actingAs($this->staff)->post(route('admin.system-data.import'))->assertForbidden();

        // Guest user is redirected
        auth()->logout();
        $this->get(route('admin.system-data.export'))->assertRedirect();
        $this->get(route('admin.system-data.demo'))->assertRedirect();
        $this->post(route('admin.system-data.preview'))->assertRedirect();
        $this->post(route('admin.system-data.import'))->assertRedirect();
    }

    // =========================================================================
    // DEMO DATASET GENERATOR TESTS
    // =========================================================================

    public function test_demo_dataset_is_generated_successfully_and_covers_workflow(): void
    {
        $generator = new DemoDatasetGenerator();
        $zipPath = $generator->generate();

        $this->assertFileExists($zipPath);

        $importer = new SystemDataImporter();
        $preview = $importer->preview($zipPath);

        $this->assertTrue($preview['isValid'], 'Demo dataset must be structurally and relationally valid. Errors: ' . implode('; ', $preview['errors']));
        $this->assertSame('demo', $preview['manifest']['data_mode']);

        $counts = $preview['record_counts'];
        $this->assertGreaterThanOrEqual(4, $counts['clients']);
        $this->assertGreaterThanOrEqual(3, $counts['packages']);
        $this->assertGreaterThanOrEqual(10, $counts['inventory_items']);
        $this->assertGreaterThanOrEqual(8, $counts['bookings']);
        $this->assertGreaterThanOrEqual(3, $counts['returns']);
        $this->assertGreaterThanOrEqual(4, $counts['payments']);

        @unlink($zipPath);
    }

    // =========================================================================
    // SECURITY TESTS
    // =========================================================================

    public function test_arbitrary_zip_paths_cannot_escape_temporary_directory(): void
    {
        $maliciousZipPath = storage_path('app/tmp_system_data/malicious.zip');
        $zip = new ZipArchive();
        $zip->open($maliciousZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('../../../evil.txt', 'malicious file content');
        $zip->close();

        $importer = new SystemDataImporter();

        $this->expectException(\InvalidArgumentException::class);
        $importer->extractZipSecurely($maliciousZipPath);

        @unlink($maliciousZipPath);
    }

    // =========================================================================
    // BUSINESS INTEGRITY TEST
    // =========================================================================

    public function test_business_integrity_after_demo_import(): void
    {
        $generator = new DemoDatasetGenerator();
        $zipPath = $generator->generate();

        $importer = new SystemDataImporter();
        $importResult = $importer->import($zipPath);

        $this->assertTrue($importResult['success']);

        // 1. Packages connect to inventory BOM
        $package = Package::with('inventoryItems')->where('package_code', 'PKG-WED-01')->first();
        $this->assertNotNull($package);
        $this->assertGreaterThan(0, $package->inventoryItems->count());

        // 2. Bookings connect to clients & packages
        $confirmedBooking = Booking::with(['client', 'package', 'bookingItems'])->where('status', 'confirmed')->first();
        $this->assertNotNull($confirmedBooking);
        $this->assertNotNull($confirmedBooking->client);
        $this->assertNotNull($confirmedBooking->package);

        // 3. Booking items connect to inventory
        foreach ($confirmedBooking->bookingItems as $bi) {
            $this->assertNotNull($bi->inventoryItem);
        }

        // 4. Returns connect to bookings and return items connect to inventory
        $partReturn = DB::table('returns')->where('status', 'Partially Returned')->first();
        $this->assertNotNull($partReturn);
        $returnItems = DB::table('return_items')->where('return_id', $partReturn->id)->get();
        $this->assertGreaterThan(0, $returnItems->count());
        foreach ($returnItems as $ri) {
            $this->assertTrue(DB::table('inventory_items')->where('id', $ri->inventory_item_id)->exists());
        }

        // 5. Return conditions and charge decisions remain valid
        $damagedItem = DB::table('return_items')->where('condition', 'damaged')->first();
        $this->assertNotNull($damagedItem);
        $this->assertSame('charge', $damagedItem->charge_decision);
        $this->assertGreaterThan(0, (float) $damagedItem->damage_charge);

        // 6. Quotations connect to bookings
        $quotation = Quotation::where('status', 'accepted')->first();
        $this->assertNotNull($quotation);
        $this->assertNotNull($quotation->booking_id);
        $this->assertTrue(Booking::where('id', $quotation->booking_id)->exists());

        // 7. Payments connect to bookings and quotations
        $payment = Payment::where('status', 'verified')->first();
        $this->assertNotNull($payment);
        $this->assertNotNull($payment->booking_id);
        $this->assertTrue(Booking::where('id', $payment->booking_id)->exists());
        if ($payment->quotation_id) {
            $this->assertTrue(Quotation::where('id', $payment->quotation_id)->exists());
        }

        // 8. Inventory transactions connect to items and bookings
        $tx = DB::table('inventory_transactions')->whereNotNull('booking_id')->first();
        $this->assertNotNull($tx);
        $this->assertTrue(InventoryItem::where('id', $tx->inventory_item_id)->exists());
        $this->assertTrue(Booking::where('id', $tx->booking_id)->exists());

        @unlink($zipPath);
    }

    // =========================================================================
    // DATETIME NORMALIZATION & VALIDATION TESTS
    // =========================================================================

    public function test_iso8601_utc_and_offset_timestamps_import_successfully(): void
    {
        $generator = new DemoDatasetGenerator();
        $demoZipPath = $generator->generate();

        $tempDir = storage_path('app/tmp_system_data/test_iso_' . Str::random(8));
        $zip = new ZipArchive();
        $zip->open($demoZipPath);
        $zip->extractTo($tempDir);
        $zip->close();
        @unlink($demoZipPath);

        // Inject ISO-8601 with UTC 'Z' and microseconds
        $clients = json_decode(File::get($tempDir . '/data/clients.json'), true);
        $clients[0]['created_at'] = '2026-08-25T05:11:52.351068Z';
        // Inject ISO-8601 with offset
        $clients[1]['created_at'] = '2026-08-25T13:11:52.500000+08:00';
        File::put($tempDir . '/data/clients.json', json_encode($clients));

        $testZip = storage_path('app/tmp_system_data/test_iso.zip');
        $zip = new ZipArchive();
        $zip->open($testZip, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFile($tempDir . '/manifest.json', 'manifest.json');
        foreach (File::files($tempDir . '/data') as $f) {
            $zip->addFile($f->getRealPath(), 'data/' . $f->getFilename());
        }
        $zip->close();
        File::deleteDirectory($tempDir);

        $importer = new SystemDataImporter();
        $preview = $importer->preview($testZip);
        $this->assertTrue($preview['isValid'], 'Preview must accept valid ISO-8601 timestamps. Errors: ' . implode('; ', $preview['errors']));

        $result = $importer->import($testZip);
        $this->assertTrue($result['success']);

        $c1 = Client::where('email', $clients[0]['email'])->first();
        $this->assertNotNull($c1);
        $this->assertStringContainsString('2026-08-25 13:11:52', (string) $c1->created_at);

        $c2 = Client::where('email', $clients[1]['email'])->first();
        $this->assertNotNull($c2);
        $this->assertStringContainsString('2026-08-25 13:11:52', (string) $c2->created_at);

        @unlink($testZip);
    }

    public function test_timestamps_without_microseconds_and_date_fields_import_successfully(): void
    {
        $generator = new DemoDatasetGenerator();
        $demoZipPath = $generator->generate();

        $tempDir = storage_path('app/tmp_system_data/test_dt_' . Str::random(8));
        $zip = new ZipArchive();
        $zip->open($demoZipPath);
        $zip->extractTo($tempDir);
        $zip->close();
        @unlink($demoZipPath);

        $clients = json_decode(File::get($tempDir . '/data/clients.json'), true);
        $clients[0]['created_at'] = '2026-08-25 13:11:52';
        File::put($tempDir . '/data/clients.json', json_encode($clients));

        $bookings = json_decode(File::get($tempDir . '/data/bookings.json'), true);
        $bookings[0]['event_date'] = '2026-11-20';
        $bookings[0]['price_valid_until'] = '2026-10-15';
        File::put($tempDir . '/data/bookings.json', json_encode($bookings));

        $testZip = storage_path('app/tmp_system_data/test_dt.zip');
        $zip = new ZipArchive();
        $zip->open($testZip, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFile($tempDir . '/manifest.json', 'manifest.json');
        foreach (File::files($tempDir . '/data') as $f) {
            $zip->addFile($f->getRealPath(), 'data/' . $f->getFilename());
        }
        $zip->close();
        File::deleteDirectory($tempDir);

        $importer = new SystemDataImporter();
        $result = $importer->import($testZip);
        $this->assertTrue($result['success']);

        $c = Client::where('email', $clients[0]['email'])->first();
        $this->assertNotNull($c);
        $this->assertStringContainsString('2026-08-25 13:11:52', (string) $c->created_at);

        $b = Booking::where('venue', $bookings[0]['venue'])->first();
        $this->assertNotNull($b);
        $rawBooking = DB::table('bookings')->where('id', $b->id)->first();
        $this->assertSame('2026-11-20', $rawBooking->event_date);
        $this->assertSame('2026-10-15', $rawBooking->price_valid_until);
        $this->assertSame('2026-11-20', $b->event_date->format('Y-m-d'));

        @unlink($testZip);
    }

    public function test_null_datetime_remains_null_after_import(): void
    {
        $generator = new DemoDatasetGenerator();
        $demoZipPath = $generator->generate();

        $tempDir = storage_path('app/tmp_system_data/test_null_' . Str::random(8));
        $zip = new ZipArchive();
        $zip->open($demoZipPath);
        $zip->extractTo($tempDir);
        $zip->close();
        @unlink($demoZipPath);

        $bookings = json_decode(File::get($tempDir . '/data/bookings.json'), true);
        $bookings[0]['confirmed_at'] = null;
        $bookings[0]['price_valid_until'] = null;
        File::put($tempDir . '/data/bookings.json', json_encode($bookings));

        $testZip = storage_path('app/tmp_system_data/test_null.zip');
        $zip = new ZipArchive();
        $zip->open($testZip, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFile($tempDir . '/manifest.json', 'manifest.json');
        foreach (File::files($tempDir . '/data') as $f) {
            $zip->addFile($f->getRealPath(), 'data/' . $f->getFilename());
        }
        $zip->close();
        File::deleteDirectory($tempDir);

        $importer = new SystemDataImporter();
        $result = $importer->import($testZip);
        $this->assertTrue($result['success']);

        $b = Booking::where('venue', $bookings[0]['venue'])->first();
        $this->assertNotNull($b);
        $this->assertNull($b->confirmed_at);
        $this->assertNull($b->price_valid_until);

        @unlink($testZip);
    }

    public function test_invalid_datetime_rejected_during_preview_with_zero_mutations(): void
    {
        $generator = new DemoDatasetGenerator();
        $demoZipPath = $generator->generate();

        $tempDir = storage_path('app/tmp_system_data/test_inv_' . Str::random(8));
        $zip = new ZipArchive();
        $zip->open($demoZipPath);
        $zip->extractTo($tempDir);
        $zip->close();
        @unlink($demoZipPath);

        $clients = json_decode(File::get($tempDir . '/data/clients.json'), true);
        $clients[0]['created_at'] = 'invalid-not-a-datetime';
        File::put($tempDir . '/data/clients.json', json_encode($clients));

        $testZip = storage_path('app/tmp_system_data/test_inv.zip');
        $zip = new ZipArchive();
        $zip->open($testZip, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFile($tempDir . '/manifest.json', 'manifest.json');
        foreach (File::files($tempDir . '/data') as $f) {
            $zip->addFile($f->getRealPath(), 'data/' . $f->getFilename());
        }
        $zip->close();
        File::deleteDirectory($tempDir);

        $initialClientsCount = Client::count();

        $importer = new SystemDataImporter();
        $preview = $importer->preview($testZip);

        // Preview reports exact entity, field, record key
        $this->assertFalse($preview['isValid']);
        $this->assertTrue(collect($preview['errors'])->contains(function ($e) use ($clients) {
            return str_contains($e, "Invalid datetime value for clients.created_at in record '{$clients[0]['key']}'.");
        }));

        // Zero mutations occurred
        $this->assertSame($initialClientsCount, Client::count());

        // HTTP Preview route returns 422
        $uploaded = new UploadedFile($testZip, 'test_inv.zip', 'application/zip', null, true);
        $response = $this->actingAs($this->admin)->postJson(route('admin.system-data.preview'), [
            'dataset_file' => $uploaded,
        ]);
        $response->assertStatus(422);
        $response->assertJsonFragment([
            "Invalid datetime value for clients.created_at in record '{$clients[0]['key']}'."
        ]);

        $this->assertSame($initialClientsCount, Client::count());

        @unlink($testZip);
    }

    public function test_multiple_entities_with_timestamps_import_successfully(): void
    {
        $generator = new DemoDatasetGenerator();
        $zipPath = $generator->generate();

        $importer = new SystemDataImporter();
        $result = $importer->import($zipPath);

        $this->assertTrue($result['success']);

        // Verify entities with timestamps from all required modules:
        // 1. Clients
        $this->assertNotNull(DB::table('clients')->whereNotNull('created_at')->first());
        // 2. Bookings
        $this->assertNotNull(DB::table('bookings')->whereNotNull('created_at')->first());
        // 3. Quotations
        $this->assertNotNull(DB::table('quotations')->whereNotNull('created_at')->first());
        // 4. Payments
        $this->assertNotNull(DB::table('payments')->whereNotNull('created_at')->first());
        // 5. Inventory Transactions
        $this->assertNotNull(DB::table('inventory_transactions')->whereNotNull('created_at')->first());
        // 6. Returns
        $this->assertNotNull(DB::table('returns')->whereNotNull('created_at')->first());
        // 7. Audit Logs
        $this->assertNotNull(DB::table('audit_logs')->whereNotNull('created_at')->first());
        // 8. Notifications / Alerts
        $this->assertNotNull(DB::table('client_notifications')->whereNotNull('created_at')->first());
        $this->assertNotNull(DB::table('admin_alerts')->whereNotNull('created_at')->first());

        @unlink($zipPath);
    }

    public function test_export_import_export_round_trip_succeeds(): void
    {
        $this->seedBusinessData();

        $exporter = new SystemDataExporter();
        $zip1 = $exporter->export();
        $this->assertFileExists($zip1);

        $importer = new SystemDataImporter();
        $preview1 = $importer->preview($zip1);
        $this->assertTrue($preview1['isValid'], 'Export 1 must be valid. Errors: ' . implode('; ', $preview1['errors']));

        $importResult = $importer->import($zip1);
        $this->assertTrue($importResult['success']);

        $zip2 = $exporter->export();
        $this->assertFileExists($zip2);

        $preview2 = $importer->preview($zip2);
        $this->assertTrue($preview2['isValid'], 'Export 2 must be valid. Errors: ' . implode('; ', $preview2['errors']));

        @unlink($zip1);
        @unlink($zip2);
    }
}
