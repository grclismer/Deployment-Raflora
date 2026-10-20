<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\Payment;
use App\Models\User;
use App\Services\StaffWorkspaceService;
use App\Services\SystemData\DemoDatasetGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;
use ZipArchive;

/**
 * Holds (booking_lock) are stored negative — every application version wrote -quantity, and one
 * July 2026 version wrote 0 as a "downpayment verified" marker. On that data every hold reader
 * agrees. System Data import keeps it so: a positive hold or an empty transaction type is refused,
 * while older types and zero markers still import.
 */
class HistoricalLedgerConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::create(['name' => 'Raflora Admin', 'first_name' => 'Raflora', 'last_name' => 'Admin', 'email' => 'admin@raflora.com',
            'password' => bcrypt('AdminPassword123!'), 'role' => 'admin', 'is_bootstrap' => false]);
        User::create(['name' => 'Raflora Staff', 'first_name' => 'Maria', 'last_name' => 'Santos', 'email' => 'staff@raflora.com',
            'password' => bcrypt('StaffPassword123!'), 'role' => 'staff', 'is_bootstrap' => false]);
    }

    // Import: a positive hold would be read as a hold by some readers and ignored by others.
    public function test_positive_hold_is_refused_on_import_and_nothing_is_written(): void
    {
        $zip = $this->datasetWithLedger([$this->tx('probe-hold', 4, 'booking_lock')]);
        $before = DB::table('inventory_transactions')->count();

        $this->preview($zip)->assertStatus(422)
            ->assertJsonFragment(["Inventory transaction 'probe-hold' is a hold and must have a negative quantity_change, or 0 for an older marker (got 4)."]);
        $this->assertSame($before, DB::table('inventory_transactions')->count());
        @unlink($zip);
    }

    public function test_empty_or_blank_transaction_types_are_refused(): void
    {
        foreach (['', '   ', null] as $i => $type) {
            $zip = $this->datasetWithLedger([$this->tx('probe-type-' . $i, 1, $type)]);
            $this->preview($zip)->assertStatus(422)
                ->assertJsonFragment(["Inventory transaction 'probe-type-{$i}' has no transaction_type."]);
            @unlink($zip);
        }
    }

    // Canonical negative holds, legacy zero markers and older types still import, with releases kept positive.
    public function test_canonical_and_historical_rows_still_import(): void
    {
        $zip = $this->datasetWithLedger([
            $this->tx('probe-hold', -4, 'booking_lock'),
            $this->tx('probe-marker', 0, 'booking_lock'),
            $this->tx('probe-release', 1, 'booking_release'),
            $this->tx('probe-consumed', 0, 'consumed'),
            $this->tx('probe-started', 0, 'event_started'),
        ]);

        $token = $this->preview($zip)->assertOk()->json('preview.preview_token');
        $this->actingAs($this->admin)->postJson(route('admin.system-data.import'), ['preview_token' => $token])->assertOk();

        $this->assertSame(5, InventoryTransaction::where('reason', 'like', 'probe-%')->count());
        $hold = InventoryTransaction::where('reason', 'probe-hold')->sole();
        $this->assertEquals(3.0, InventoryItem::findOrFail($hold->inventory_item_id)->reservedForBooking($hold->booking_id));
        @unlink($zip);
    }

    // Readers: on canonical data (negative holds plus a zero marker) the shared rule, the booking
    // page, the Staff dispatch board and the workflow all see the same hold.
    public function test_hold_readers_agree_on_canonical_and_marker_rows(): void
    {
        $arch = InventoryItem::create(['name' => 'Gold Arch', 'category' => 'props', 'is_perishable' => false, 'current_stock' => 10, 'unit_cost' => 100, 'min_stock' => 0, 'unit' => 'piece']);
        $booking = Booking::create(['event_type' => 'wedding', 'event_date' => now()->addDays(5)->toDateString(), 'venue' => 'Hall',
            'status' => 'confirmed', 'final_quoted_price' => 18000, 'total_quoted' => 18000, 'confirmed_at' => now()]);
        BookingItem::create(['booking_id' => $booking->id, 'inventory_item_id' => $arch->id, 'item_name' => 'Gold Arch', 'quantity' => 5, 'quoted_unit_price' => 100, 'confirmed_at' => now()]);
        $this->ledger($arch, $booking, 'booking_lock', 0);   // legacy marker: holds nothing
        $this->ledger($arch, $booking, 'booking_lock', -5);
        $this->ledger($arch, $booking, 'booking_release', 1);
        $this->ledger($arch, $booking, 'dispatch', -2);

        // Net hold (locks − releases − dispatched): 2.
        $this->assertEquals(2.0, $arch->fresh()->reservedForBooking($booking->id));

        // Total hold recorded (locks − releases, dispatched shown separately): 4 on the booking page and the Staff board.
        $this->actingAs($this->admin)->get(route('admin.bookings.show', $booking))->assertOk()
            ->assertViewHas('reusableStatusList', function ($rows) {
                $row = collect($rows)->first();

                return (float) $row['locked'] === 4.0 && (float) $row['net_dispatched'] === 2.0 && (float) $row['remaining'] === 1.0;
            });
        $board = app(StaffWorkspaceService::class)->dispatchRows($booking->fresh()->load('inventoryTransactions.inventoryItem'))->first();
        $this->assertEquals(4.0, (float) $board['reserved']);
        $this->assertEquals(2.0, (float) $board['dispatched']);
    }

    // Payment verification still refuses an impossible ledger (more released than ever held).
    public function test_payment_verification_still_rejects_an_impossible_ledger(): void
    {
        $arch = InventoryItem::create(['name' => 'Gold Arch', 'category' => 'props', 'is_perishable' => false, 'current_stock' => 10, 'unit_cost' => 100, 'min_stock' => 0, 'unit' => 'piece']);
        $booking = Booking::create(['event_type' => 'wedding', 'event_date' => now()->addDays(5)->toDateString(), 'venue' => 'Hall',
            'status' => 'payment_submitted', 'final_quoted_price' => 18000, 'total_quoted' => 18000, 'preparation_start_date' => now()->subDay()->toDateString()]);
        BookingItem::create(['booking_id' => $booking->id, 'inventory_item_id' => $arch->id, 'item_name' => 'Gold Arch', 'quantity' => 2, 'quoted_unit_price' => 100, 'confirmed_at' => now()]);
        $booking->inventoryItems()->syncWithoutDetaching([$arch->id => ['quantity' => 2, 'quoted_unit_price' => 100]]);
        // More released than was ever held (a dispatch would make it a post-event payment instead).
        $this->ledger($arch, $booking, 'booking_lock', -2);
        $this->ledger($arch, $booking, 'booking_release', 3);
        $payment = Payment::create(['booking_id' => $booking->id, 'amount' => 9000, 'amount_paid' => 0, 'payment_option' => 'downpayment',
            'payment_type' => 'gcash', 'reference_number' => 'GCASH-IMPOSSIBLE', 'status' => 'pending']);

        $this->actingAs($this->admin)->post(route('admin.payments.verify', $payment))->assertSessionHas('error');

        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertSame('payment_submitted', $booking->fresh()->status);
    }

    private function ledger(InventoryItem $item, Booking $booking, string $type, float $change): void
    {
        InventoryTransaction::create(['inventory_item_id' => $item->id, 'booking_id' => $booking->id, 'quantity_change' => $change,
            'transaction_type' => $type, 'reason' => 'test', 'performed_by' => $this->admin->id]);
    }

    private function tx(string $key, mixed $quantity, ?string $type): array
    {
        return ['key' => $key, 'inventory_item_ref' => 'inventory-008', 'booking_ref' => 'booking-001', 'reference_transaction_ref' => null,
            'performed_by_ref' => 'user-admin', 'quantity_change' => $quantity, 'transaction_type' => $type, 'reason' => $key,
            'created_at' => now()->toISOString(), 'updated_at' => now()->toISOString()];
    }

    /** The demo dataset with extra ledger rows, rebuilt as a zip under storage like the existing System Data tests. */
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
}
