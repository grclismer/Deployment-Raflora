<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Booking;
use App\Models\Client;
use App\Models\User;
use App\Models\Payment;
use App\Models\Presentation;
use App\Models\ReturnTracking;
use App\Models\ReturnItem;
use Carbon\Carbon;

// PHASE 4 UI MOCK DATA
// TEMPORARY ONLY
// Represents expected backend state.
// Replace with real backend data in PHASE 5.
// Do not use for production business calculations or persistence.
class MockUIController extends Controller
{
    public function hub()
    {
        return view('mock.hub');
    }

    public function clientScenario($scenario)
    {
        $booking = $this->generateMockBooking($scenario);
        
        if ($scenario === 'bookings_list') {
            return view('client.bookings', ['bookings' => collect([
                $this->generateMockBooking('pending'),
                $this->generateMockBooking('quotation_sent'),
                $this->generateMockBooking('payment_pending'),
                $this->generateMockBooking('confirmed'),
                $this->generateMockBooking('completed')
            ])]);
        }

        return view('client.booking-analysis', [
            'booking' => $booking,
            'analysisMaterials' => [],
            'presentations' => $booking->presentations,
            'bookingMessages' => collect(),
            'totalCost' => $booking->total_obligation,
        ]);
    }

    public function adminScenario($scenario)
    {
        $booking = $this->generateMockBooking($scenario);

        if ($scenario === 'bookings_list') {
            return view('admin.bookings', [
                'bookings' => collect([
                    $this->generateMockBooking('pending'),
                    $this->generateMockBooking('quotation_sent'),
                    $this->generateMockBooking('payment_submitted'),
                    $this->generateMockBooking('confirmed')
                ])
            ]);
        }

        if (in_array($scenario, ['return_review', 'damaged_charge'])) {
            return view('admin.return-tracking-manage', [
                'booking' => $booking,
                'returns' => $booking->returns,
                'damageCharge' => $booking->returns->sum('total_damage_charge')
            ]);
        }

        return view('admin.booking-show', [
            'booking' => $booking,
            'aiAnalysis' => null,
            'staff' => User::where('role', 'staff')->get()
        ]);
    }

    public function staffScenario($scenario)
    {
        $booking = $this->generateMockBooking($scenario);

        if ($scenario === 'assigned_list') {
            return view('staff.dashboard', [
                'upcomingEvents' => collect([$booking]),
                'activeEvents' => collect(),
                'completedEvents' => collect()
            ]);
        }
        
        $returnRecord = null;
        if ($booking->returns && $booking->returns->isNotEmpty()) {
            $returnRecord = $booking->returns->first();
        } else if ($scenario === 'pending_return') {
            $returnItem = new ReturnItem([
                'id' => 9999,
                'inventory_item_id' => 1,
                'quantity_returned' => 1,
                'condition' => 'pending',
                'notes' => ''
            ]);
            $returnItem->setRelation('inventoryItem', new \App\Models\InventoryItem(['name' => 'Mock Crystal Vase', 'unit' => 'pc']));
            
            $returnRecord = new ReturnTracking([
                'id' => 9999,
                'status' => 'pending',
                'notes' => ''
            ]);
            $returnRecord->setRelation('returnItems', collect([$returnItem]));
            $booking->setRelation('returns', collect([$returnRecord]));
            
            $bookingItem = new \App\Models\BookingItem([
                'inventory_item_id' => 1,
                'quantity' => 1,
                'confirmed_at' => Carbon::now()
            ]);
            $bookingItem->setRelation('inventoryItem', new \App\Models\InventoryItem(['name' => 'Mock Crystal Vase', 'unit' => 'pc']));
            $booking->setRelation('bookingItems', collect([$bookingItem]));
        }

        return view('staff.event-show', [
            'booking' => $booking,
            'checklists' => $booking->staffChecklistItems ?? collect(),
            'inventoryItems' => $booking->inventoryItems ?? collect(),
            'returnRecord' => $returnRecord
        ]);
    }

    private function generateMockBooking($scenario)
    {
        $booking = new Booking();
        $booking->id = 9999; // Mock ID
        $booking->client_id = 9999;
        $booking->event_type = 'Wedding';
        $booking->event_date = Carbon::now()->addDays(30);
        $booking->event_time = '14:00';
        $booking->venue = 'The Grand Mock Hotel';
        $booking->event_size = 150;
        $booking->total_quoted = 50000;
        $booking->final_quoted_price = 50000;
        $booking->created_at = Carbon::now()->subDays(2);
        
        // Default mock relations
        $booking->setRelation('client', new Client(['id' => 9999, 'full_name' => 'John Mockingbird', 'email' => 'client@mock.com', 'phone' => '09123456789']));
        $booking->setRelation('presentations', collect());
        $booking->setRelation('returns', collect());
        $booking->setRelation('payments', collect());
        $booking->setRelation('inventoryItems', collect());
        $booking->setRelation('staffChecklistItems', collect());
        $booking->setRelation('bookingItems', collect());

        switch ($scenario) {
            case 'pending': // 1. Booking Submitted / 2. Under Review (Client) | 1. New Booking (Admin)
                $booking->status = 'pending';
                break;
            case 'quotation_sent': // 3. Quotation Ready (Client) | 3. Quotation Preparation (Admin)
                $booking->status = 'quotation_sent';
                $booking->price_valid_until = Carbon::now()->addDays(7);
                break;
            case 'change_requested': // 4. Change Requested / Negotiation
                $booking->status = 'change_requested';
                break;
            case 'approved': // 5. Awaiting Acceptance
                $booking->status = 'approved';
                break;
            case 'admin_approved': // 6. Payment Required (Client) | 5. Awaiting Payment
                $booking->status = 'admin_approved';
                break;
            case 'payment_submitted': // 7. Payment Submitted (Client) | 6. Payment Verification (Admin)
                $booking->status = 'payment_submitted';
                $payment = new Payment([
                    'id' => 9999,
                    'amount' => 25000,
                    'status' => 'pending',
                    'payment_method' => 'gcash',
                    'reference_number' => 'GCASH-MOCK-123'
                ]);
                $booking->setRelation('payments', collect([$payment]));
                break;
            case 'confirmed': // 8. Confirmed (Client) / Event Preparation | 7. Staff Assignment
                $booking->status = 'confirmed';
                $payment = new Payment([
                    'id' => 9999,
                    'amount_paid' => 25000,
                    'status' => 'verified',
                    'payment_method' => 'gcash'
                ]);
                $booking->setRelation('payments', collect([$payment]));
                break;
            case 'event_in_progress': // 9. Event (Staff)
                $booking->status = 'event_in_progress';
                break;
            case 'pending_return': // 4. Return (Staff) | 9. Return Review (Admin)
                $booking->status = 'pending_return';
                break;
            case 'damaged_charge': // 10. Post-Event Charge (Client) | 10. Damaged / Charge Decision (Admin)
                $booking->status = 'pending_resolution'; // Or whichever status indicates pending damage charge payment
                
                $returnItem = new ReturnItem([
                    'id' => 9999,
                    'condition' => 'damaged',
                    'charge_decision' => 'charge',
                    'damage_charge' => 5000,
                    'evidence_image' => null
                ]);
                $returnItem->setRelation('inventoryItem', new \App\Models\InventoryItem(['name' => 'Mock Crystal Vase']));

                $returnTracking = new ReturnTracking([
                    'id' => 9999,
                    'status' => 'assessed',
                    'total_damage_charge' => 5000
                ]);
                $returnTracking->setRelation('returnItems', collect([$returnItem]));

                $booking->setRelation('returns', collect([$returnTracking]));
                break;
            case 'completed': // 11. Completed
                $booking->status = 'completed';
                break;
            default:
                $booking->status = 'pending';
                break;
        }

        // Mock Dynamic Attributes
        $booking->status_display_label = $booking->getStatusDisplayLabelAttribute();
        $booking->total_obligation = $booking->getTotalObligationAttribute();
        $booking->remaining_balance = $booking->getRemainingBalanceAttribute();

        return $booking;
    }
}
