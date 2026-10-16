<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use App\Models\Booking;
use App\Models\InventoryItem;
use App\Models\Payment;
use App\Models\AuditLog;
use Carbon\Carbon;

class ReportController extends Controller
{
    public function index()
    {
        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();
        $endOfMonth = $now->copy()->endOfMonth();

        // Operational confirmed/active statuses
        $confirmedStatuses = [
            'downpayment_received',
            'confirmed',
            'in_preparation',
            'event_in_progress',
            'event_completed',
            'completed',
            'pending_return',
            'pending_resolution',
        ];

        // Prospective pipeline statuses (active inquiries & contracted work awaiting execution)
        $pipelineStatuses = [
            'pending',
            'quotation_sent',
            'change_requested',
            'approved',
            'admin_approved',
            'payment_pending',
            'payment_submitted',
            'downpayment_received',
            'confirmed',
            'in_preparation',
            'event_in_progress',
        ];

        $monthBookings = Booking::whereBetween('created_at', [$startOfMonth, $endOfMonth]);
        $bookingsThisMonth = (clone $monthBookings)->count();

        // Confirmed bookings created in this reporting period
        $confirmedBookingsThisMonth = (clone $monthBookings)->whereIn('status', $confirmedStatuses)->get(['total_quoted', 'final_quoted_price']);
        $confirmedThisMonth = $confirmedBookingsThisMonth->count();

        // Verified revenue: strictly based on qualifying verified payment records within the reporting period
        $verifiedRevenue = (float) Payment::whereNotNull('verified_at')
            ->whereIn('status', ['verified', 'fully_paid', 'downpayment_received'])
            ->whereBetween('verified_at', [$startOfMonth, $endOfMonth])
            ->sum('amount_paid');
        $revenueEstimate = $verifiedRevenue; // Compatibility alias

        // Average contract value of confirmed bookings created this month
        $totalConfirmedContractValue = $confirmedBookingsThisMonth->sum(
            fn ($booking) => (float) ($booking->final_quoted_price > 0 ? $booking->final_quoted_price : ($booking->total_quoted ?? 0))
        );
        $averageBookingValue = $confirmedThisMonth > 0 ? $totalConfirmedContractValue / $confirmedThisMonth : 0.0;

        // On-hand low stock (InventoryItem::scopeBelowMinimum), the rule the Dashboard uses too.
        $stockAlerts = InventoryItem::belowMinimum()->count();

        // Quoted pipeline value across active prospective bookings
        $pipelineValue = (float) Booking::whereIn('status', $pipelineStatuses)
            ->get(['total_quoted', 'final_quoted_price'])
            ->sum(fn ($b) => (float) ($b->final_quoted_price > 0 ? $b->final_quoted_price : ($b->total_quoted ?? 0)));

        // Six-month trend: verified revenue cash-flow and booking inquiry volume
        $sixMonthsAgo = $now->copy()->subMonths(5)->startOfMonth();

        $trendBookings = Booking::whereBetween('created_at', [$sixMonthsAgo, $endOfMonth])
            ->whereNotIn('status', ['cancelled', 'declined'])
            ->get(['created_at']);

        $trendPayments = Payment::whereNotNull('verified_at')
            ->whereIn('status', ['verified', 'fully_paid', 'downpayment_received'])
            ->whereBetween('verified_at', [$sixMonthsAgo, $endOfMonth])
            ->get(['verified_at', 'amount_paid']);

        $salesLabels = [];
        $salesData = [];
        $bookingTrend = [];

        for ($i = 5; $i >= 0; $i--) {
            $month = $now->copy()->subMonths($i)->startOfMonth();
            $key = $month->format('Y-m');
            $salesLabels[] = $month->format('M Y');
            $salesData[$key] = 0.0;
            $bookingTrend[$key] = 0;
        }

        foreach ($trendBookings as $booking) {
            $key = Carbon::parse($booking->created_at)->format('Y-m');
            if (array_key_exists($key, $bookingTrend)) {
                $bookingTrend[$key]++;
            }
        }

        foreach ($trendPayments as $payment) {
            $key = Carbon::parse($payment->verified_at)->format('Y-m');
            if (array_key_exists($key, $salesData)) {
                $salesData[$key] += (float) $payment->amount_paid;
            }
        }

        // Booking status distribution
        $statusCounts = Booking::select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->orderByDesc('total')
            ->get();
        $statusLabels = $statusCounts->map(fn ($status) => ucfirst(str_replace('_', ' ', $status->status ?? 'pending')))->values()->all();
        $statusData = $statusCounts->pluck('total')->map(fn ($total) => (int) $total)->values()->all();

        // Top requested materials: confirmed line items on active bookings only, excluding soft-deleted items
        $topMaterials = DB::table('booking_items')
            ->join('bookings', 'booking_items.booking_id', '=', 'bookings.id')
            ->join('inventory_items', 'booking_items.inventory_item_id', '=', 'inventory_items.id')
            ->whereNotNull('booking_items.confirmed_at')
            ->whereNotIn('bookings.status', ['cancelled', 'declined'])
            ->whereNull('inventory_items.deleted_at')
            ->where('booking_items.quantity', '>', 0)
            ->select('inventory_items.name', DB::raw('SUM(booking_items.quantity) as total_qty'))
            ->groupBy('inventory_items.id', 'inventory_items.name')
            ->orderByDesc('total_qty')
            ->limit(5)
            ->get();

        $materialLabels = $topMaterials->pluck('name')->toArray();
        $materialData = $topMaterials->pluck('total_qty')->map(fn ($qty) => (float) $qty)->toArray();

        $lowStockItems = InventoryItem::belowMinimum()
            ->orderByRaw('(current_stock - min_stock) asc')->limit(5)
            ->get(['name', 'current_stock', 'min_stock', 'unit']);

        $auditActivities = AuditLog::with('user')->latest()->limit(5)->get();
        $activities = $auditActivities->map(function ($log) {
            $details = $log->details;
            $formattedDetails = 'No additional details';

            if (!empty($details)) {
                if (is_string($details)) {
                    $formattedDetails = $details;
                } elseif (is_array($details)) {
                    $message = $details['message'] ?? null;
                    $otherDetails = collect($details)->except(['message'])->map(function ($val, $key) {
                        $valStr = is_scalar($val) ? (string) $val : json_encode($val);
                        return ucfirst(str_replace('_', ' ', $key)) . ': ' . $valStr;
                    })->implode(', ');

                    if ($message && $otherDetails) {
                        $formattedDetails = $message . ' (' . $otherDetails . ')';
                    } elseif ($message) {
                        $formattedDetails = (string) $message;
                    } elseif ($otherDetails) {
                        $formattedDetails = $otherDetails;
                    }
                }
            }

            return [
                'date' => optional($log->created_at)->format('M d, Y h:i A') ?? 'N/A',
                'user' => optional($log->user)->name ?? 'System',
                'action' => ucwords(str_replace('_', ' ', $log->action ?? 'Activity')),
                'details' => $formattedDetails,
            ];
        });

        if ($activities->isEmpty()) {
            $activities = Booking::with('client')->latest('updated_at')->limit(5)->get()->map(function ($booking) {
                return [
                    'date' => optional($booking->updated_at)->format('M d, Y h:i A') ?? 'N/A',
                    'user' => $booking->client?->full_name ?? $booking->guest_name ?? 'Guest',
                    'action' => 'Booking updated',
                    'details' => 'Booking #' . $booking->id . ' is ' . ($booking->status_display_label ?? 'Pending'),
                ];
            });
        }

        $reportPeriod = $startOfMonth->format('F Y');

        return view('admin.reports', compact(
            'bookingsThisMonth',
            'verifiedRevenue',
            'revenueEstimate',
            'averageBookingValue',
            'confirmedThisMonth',
            'pipelineValue',
            'reportPeriod',
            'stockAlerts',
            'salesLabels',
            'salesData',
            'bookingTrend',
            'statusLabels',
            'statusData',
            'materialLabels',
            'materialData',
            'lowStockItems',
            'activities'
        ));
    }
    
    public function aiAnalysis()
    {
        // Fetch recent bookings that have AI analysis data
        $aiBookings = Booking::whereNotNull('ai_analysis_data')
            ->orderBy('updated_at', 'desc')
            ->limit(10)
            ->get();
            
        return view('admin.ai-analysis', compact('aiBookings'));
    }

}
