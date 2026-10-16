<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\User;
use App\Services\InventoryDispatchService;
use App\Services\SystemData\DemoDatasetGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;
use ZipArchive;

/**
 * System Data import keeps the ledger's sign convention: a release (booking_release) is stored as
 * a positive quantity, as the application writes it, so imported holds read the same as
 * application-made ones; a dataset that breaks this is refused before anything is written.
 * Also: the Notifications shortage table names the stock it shows.
 */
class SystemDataLedgerImportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // The demo dataset refers to these accounts by email.
        $this->admin = User::create(['name' => 'Raflora Admin', 'first_name' => 'Raflora', 'last_name' => 'Admin', 'email' => 'admin@raflora.com',
            'password' => bcrypt('AdminPassword123!'), 'role' => 'admin', 'is_bootstrap' => false]);
        User::create(['name' => 'Raflora Staff', 'first_name' => 'Maria', 'last_name' => 'Santos', 'email' => 'staff@raflora.com',
            'password' => bcrypt('StaffPassword123!'), 'role' => 'staff', 'is_bootstrap' => false]);
    }

    public function test_negative_release_is_refused_and_nothing_is_written(): void
    {
        $zip = $this->datasetWithLedger([
            $this->tx('probe-lock', -4, 'booking_lock'),
            $this->tx('probe-release', -1, 'booking_release'),
        ]);
        $before = $this->counts();

        $this->preview($zip)->assertStatus(422)
            ->assertJsonFragment(["Inventory transaction 'probe-release' is a release and must have a positive quantity_change (got -1)."]);

        try {
            app(\App\Services\SystemData\SystemDataImporter::class)->import($zip);
            $this->fail('A negative release must be refused.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('must have a positive quantity_change', $e->getMessage());
        }

        $this->assertSame($before, $this->counts());
        @unlink($zip);
    }

    public function test_non_numeric_quantity_is_refused(): void
    {
        $zip = $this->datasetWithLedger([$this->tx('probe-bad', 'four', 'booking_lock')]);

        $this->preview($zip)->assertStatus(422)
            ->assertJsonFragment(["Inventory transaction 'probe-bad' has a missing or non-numeric quantity_change."]);
        @unlink($zip);
    }

    public function test_imported_hold_and_release_read_like_application_made_ones(): void
    {
        $zip = $this->datasetWithLedger([
            $this->tx('probe-lock', -4, 'booking_lock'),
            $this->tx('probe-release', 1, 'booking_release'),
        ]);

        $token = $this->preview($zip)->assertOk()->json('preview.preview_token');
        $this->actingAs($this->admin)->postJson(route('admin.system-data.import'), ['preview_token' => $token])->assertOk();

        $lock = InventoryTransaction::where('reason', 'probe-lock')->sole();
        $release = InventoryTransaction::where('reason', 'probe-release')->sole();
        $this->assertEquals(-4.0, (float) $lock->quantity_change);
        $this->assertEquals(1.0, (float) $release->quantity_change);

        $item = InventoryItem::findOrFail($lock->inventory_item_id);
        $booking = Booking::findOrFail($lock->booking_id);
        $this->assertEquals(3.0, $item->reservedForBooking($booking->id));

        // Same movements made by the application give the same hold.
        $other = Booking::create(['event_type' => 'wedding', 'event_date' => now()->addDays(5)->toDateString(), 'venue' => 'Hall', 'status' => 'confirmed', 'confirmed_at' => now()]);
        InventoryTransaction::create(['inventory_item_id' => $item->id, 'booking_id' => $other->id, 'quantity_change' => -4, 'transaction_type' => 'booking_lock', 'reason' => 'app', 'performed_by' => $this->admin->id]);
        InventoryTransaction::create(['inventory_item_id' => $item->id, 'booking_id' => $other->id, 'quantity_change' => 1, 'transaction_type' => 'booking_release', 'reason' => 'app', 'performed_by' => $this->admin->id]);
        $this->assertEquals($item->reservedForBooking($booking->id), $item->reservedForBooking($other->id));

        // Releasing what is left releases exactly the 3 still held.
        app(InventoryDispatchService::class)->releaseUnfulfilledReservations($booking, $this->admin->id, 'Event over');
        $this->assertEquals(0.0, $item->fresh()->reservedForBooking($booking->id));
        @unlink($zip);
    }

    public function test_shortage_table_names_the_stock_held_for_other_bookings(): void
    {
        $item = InventoryItem::create(['name' => 'Gold Arch', 'category' => 'props', 'is_perishable' => false, 'current_stock' => 3, 'unit_cost' => 100, 'min_stock' => 0, 'unit' => 'piece']);
        $holder = Booking::create(['event_type' => 'wedding', 'event_date' => now()->addDays(5)->toDateString(), 'venue' => 'Hall', 'status' => 'confirmed', 'confirmed_at' => now()]);
        InventoryTransaction::create(['inventory_item_id' => $item->id, 'booking_id' => $holder->id, 'quantity_change' => -2, 'transaction_type' => 'booking_lock', 'reason' => 'hold', 'performed_by' => $this->admin->id]);
        $needer = Booking::create(['event_type' => 'birthday', 'event_date' => now()->addDays(6)->toDateString(), 'venue' => 'Hall', 'status' => 'admin_approved']);
        \App\Models\BookingItem::create(['booking_id' => $needer->id, 'inventory_item_id' => $item->id, 'item_name' => 'Gold Arch', 'quantity' => 2, 'quoted_unit_price' => 100, 'confirmed_at' => now()]);

        $this->actingAs($this->admin)->get(route('admin.notifications'))->assertOk()
            ->assertSee('Held for Other Bookings')
            ->assertDontSee('<th scope="col" class="!text-right">Reserved</th>', false);
    }

    private function tx(string $key, mixed $quantity, string $type): array
    {
        return ['key' => $key, 'inventory_item_ref' => 'inventory-008', 'booking_ref' => 'booking-001', 'reference_transaction_ref' => null,
            'performed_by_ref' => 'user-admin', 'quantity_change' => $quantity, 'transaction_type' => $type, 'reason' => $key,
            'created_at' => now()->toISOString(), 'updated_at' => now()->toISOString()];
    }

    /** The demo dataset with extra ledger rows appended, rebuilt as a zip under storage like the existing System Data tests. */
    private function datasetWithLedger(array $rows): string
    {
        $demoZip = (new DemoDatasetGenerator())->generate();
        $dir = storage_path('app/tmp_system_data/ledger_' . Str::random(8));
        $zip = new ZipArchive();
        $zip->open($demoZip);
        $zip->extractTo($dir);
        $zip->close();
        @unlink($demoZip);

        $ledger = json_decode(File::get($dir . '/data/inventory_transactions.json'), true);
        File::put($dir . '/data/inventory_transactions.json', json_encode(array_merge($ledger, $rows)));

        $path = storage_path('app/tmp_system_data/ledger_' . Str::random(8) . '.zip');
        $out = new ZipArchive();
        $out->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $out->addFile($dir . '/manifest.json', 'manifest.json');
        foreach (File::files($dir . '/data') as $file) {
            $out->addFile($file->getPathname(), 'data/' . $file->getFilename());
        }
        $out->close();
        File::deleteDirectory($dir);

        return $path;
    }

    private function preview(string $zip)
    {
        $copy = $zip . '.upload.zip';
        File::copy($zip, $copy);

        return $this->actingAs($this->admin)->postJson(route('admin.system-data.preview'), [
            'dataset_file' => new UploadedFile($copy, 'dataset.zip', 'application/zip', null, true),
        ]);
    }

    private function counts(): array
    {
        return collect(['inventory_transactions', 'bookings', 'inventory_items', 'clients', 'payments'])
            ->mapWithKeys(fn ($table) => [$table => DB::table($table)->count()])->all();
    }
}
