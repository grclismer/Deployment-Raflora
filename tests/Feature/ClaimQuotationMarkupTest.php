<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Client;
use App\Models\InventoryItem;
use App\Models\Package;
use App\Models\Quotation;
use App\Models\Setting;
use App\Models\TemporaryGuestBooking;
use App\Models\User;
use App\Services\QuotationIssuanceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * A claimed custom guest request starts at the same official quotation markup as a client's own
 * custom booking (Booking::DEFAULT_QUOTATION_MARKUP), not 1×; a claimed package keeps its fixed
 * catalog price (1×). The AI estimate markup is a separate setting and never touches quotations.
 */
class ClaimQuotationMarkupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_claimed_custom_request_is_quoted_at_the_default_markup_through_acceptance(): void
    {
        InventoryItem::create(['name' => 'White Roses', 'category' => 'flowers', 'is_perishable' => true, 'current_stock' => 500, 'min_stock' => 5, 'unit_cost' => 600, 'unit' => 'stem']);
        [$user, $token] = $this->guestRequest('custom_ai', null, ['suggested_materials' => [
            ['item_name' => 'White Roses', 'category' => 'flower', 'quantity' => 10, 'unit_type' => 'stem', 'unit_cost_php' => 450],
        ]]);

        $this->actingAs($user)->post(route('client.claim-guest-booking.claim', ['token' => $token]))->assertRedirect(route('bookings'));

        $booking = Booking::where('guest_email', $user->email)->firstOrFail();
        $this->assertSame(Booking::DEFAULT_QUOTATION_MARKUP, (float) $booking->multiplier);
        // Working totals from Raflora's inventory price (never the AI's ₱450), saved at claim.
        $this->assertSame(6000.0, (float) $booking->raw_materials_sum);
        $this->assertSame(18000.0, (float) $booking->final_quoted_price);
        $line = BookingItem::where('booking_id', $booking->id)->sole();
        $this->assertSame(600.0, (float) $line->quoted_unit_price);
        $this->assertSame(450.0, (float) $line->ai_recommended_price);

        // Admin validates the material and issues the quotation; the client accepts it.
        $line->update(['confirmed_at' => now()]);
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $quotation = app(QuotationIssuanceService::class)->issue($booking->fresh(), $admin->id);
        $booking->fresh()->update(['status' => 'quotation_sent']);

        $this->assertSame('18000.00', $quotation->final_quoted_price);
        $this->assertSame('3.00', $quotation->multiplier);
        $this->assertSame(1800.0, (float) $quotation->items_snapshot[0]['selling_unit_price']);

        $this->actingAs($user)->post(route('bookings.accept', $booking))->assertRedirect();
        $this->assertSame('approved', $booking->fresh()->status);
        $this->assertSame(Quotation::STATUS_ACCEPTED, $quotation->fresh()->status);

        // The client sees the official quoted price; changing the AI estimate markup leaves it alone.
        Setting::setSetting(Setting::KEY_AI_ESTIMATE_MARKUP, 5.0, 'float');
        $this->assertSame('18000.00', $quotation->fresh()->final_quoted_price);
        $this->actingAs($user)->get(route('bookings.show', $booking))->assertOk()->assertSee('₱18,000.00');
    }

    public function test_claimed_package_request_keeps_its_catalog_price_at_one_times(): void
    {
        $package = Package::create(['title' => 'Garden Package', 'description' => 'Garden styling', 'price' => 25000, 'is_active' => true]);
        [$user, $token] = $this->guestRequest('preset', $package->id, null);

        $this->actingAs($user)->post(route('client.claim-guest-booking.claim', ['token' => $token]))->assertRedirect(route('bookings'));

        $booking = Booking::where('guest_email', $user->email)->firstOrFail();
        $this->assertSame(1.0, (float) $booking->multiplier);
        $this->assertSame(25000.0, (float) $booking->final_quoted_price);
    }

    /** @return array{0: User, 1: string} */
    private function guestRequest(string $type, ?int $packageId, ?array $analysis): array
    {
        $token = Str::random(40);
        $email = 'claimer.' . $type . '@example.com';
        TemporaryGuestBooking::create([
            'claim_token_hash' => hash('sha256', $token),
            'guest_name' => 'Claiming Guest',
            'guest_email' => $email,
            'guest_phone' => '09171234567',
            'guest_address' => 'Quezon City',
            'booking_type' => $type,
            'package_id' => $packageId,
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(40)->toDateString(),
            'venue' => 'Garden Hall',
            'analysis_data' => $analysis,
            'expires_at' => Carbon::now()->addHours(20),
        ]);
        $user = User::create(['name' => 'Claiming Guest', 'email' => $email, 'password' => bcrypt('password123'), 'role' => 'client', 'email_verified_at' => now()]);

        return [$user, $token];
    }
}
