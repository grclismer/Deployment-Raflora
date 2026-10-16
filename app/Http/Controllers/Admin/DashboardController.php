<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAlert;
use App\Models\AssetReturn;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Client;
use App\Models\InventoryItem;
use App\Models\Package;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Actionable booking statuses that require administrative attention.
     * Preserved from existing dashboard implementation for reporting test compliance.
     */
    protected const ACTIONABLE_STATUSES = [
        'pending',
        'quotation_sent',
        'payment_submitted',
        'payment_pending',
        'approved',
        'admin_approved',
        'change_requested',
        'cancellation_requested',
        'pending_return',
        'pending_resolution',
    ];

    /**
     * Display the operational admin dashboard.
     */
    public function index(Request $request): View
    {
        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();
        $endOfMonth = $now->copy()->endOfMonth();
        $startOfLastMonth = $now->copy()->subMonth()->startOfMonth();
        $endOfLastMonth = $now->copy()->subMonth()->endOfMonth();
        $startOfYear = $now->copy()->startOfYear();
        $endOfYear = $now->copy()->endOfYear();

        // 1. Core Counts & Existing Backwards Compatibility
        $totalBookings = Booking::count();
        $pendingBookings = Booking::whereIn('status', self::ACTIONABLE_STATUSES)->count();
        $totalUsers = User::count();

        // 2. KPI: Bookings This Month vs Last Month
        $bookingsThisMonth = Booking::whereBetween('created_at', [$startOfMonth, $endOfMonth])->count();
        $bookingsLastMonth = Booking::whereBetween('created_at', [$startOfLastMonth, $endOfLastMonth])->count();
        $bookingsGrowth = $bookingsLastMonth > 0
            ? round((($bookingsThisMonth - $bookingsLastMonth) / $bookingsLastMonth) * 100, 1)
            : ($bookingsThisMonth > 0 ? 100.0 : 0.0);

        // 3. KPI: Collected Revenue (Strictly from verified payment amounts)
        $verifiedStatuses = Booking::VERIFIED_PAYMENT_STATUSES ?? ['fully_paid', 'downpayment_received'];
        
        $revenueThisMonth = (float) Payment::whereIn('status', $verifiedStatuses)
            ->where(function ($q) use ($startOfMonth, $endOfMonth) {
                $q->whereBetween('verified_at', [$startOfMonth, $endOfMonth])
                  ->orWhere(function ($sub) use ($startOfMonth, $endOfMonth) {
                      $sub->whereNull('verified_at')
                          ->whereBetween('created_at', [$startOfMonth, $endOfMonth]);
                  });
            })
            ->sum('amount_paid');

        $revenueLastMonth = (float) Payment::whereIn('status', $verifiedStatuses)
            ->where(function ($q) use ($startOfLastMonth, $endOfLastMonth) {
                $q->whereBetween('verified_at', [$startOfLastMonth, $endOfLastMonth])
                  ->orWhere(function ($sub) use ($startOfLastMonth, $endOfLastMonth) {
                      $sub->whereNull('verified_at')
                          ->whereBetween('created_at', [$startOfLastMonth, $endOfLastMonth]);
                  });
            })
            ->sum('amount_paid');

        $revenueGrowth = $revenueLastMonth > 0
            ? round((($revenueThisMonth - $revenueLastMonth) / $revenueLastMonth) * 100, 1)
            : ($revenueThisMonth > 0 ? 100.0 : 0.0);

        $revenueThisYear = (float) Payment::whereIn('status', $verifiedStatuses)
            ->where(function ($q) use ($startOfYear, $endOfYear) {
                $q->whereBetween('verified_at', [$startOfYear, $endOfYear])
                  ->orWhere(function ($sub) use ($startOfYear, $endOfYear) {
                      $sub->whereNull('verified_at')
                          ->whereBetween('created_at', [$startOfYear, $endOfYear]);
                  });
            })
            ->sum('amount_paid');

        $revenueAllTime = (float) Payment::whereIn('status', $verifiedStatuses)->sum('amount_paid');

        // 4. KPI: Active Packages
        $activePackagesCount = Package::where('is_active', true)->where('is_archived', false)->count();

        // 5. KPI: Total Clients (Using Client model records, NOT User count)
        $totalClientsCount = Client::count();
        $clientsThisMonth = Client::whereBetween('created_at', [$startOfMonth, $endOfMonth])->count();
        $clientsLastMonth = Client::whereBetween('created_at', [$startOfLastMonth, $endOfLastMonth])->count();
        $clientsGrowth = $clientsLastMonth > 0
            ? round((($clientsThisMonth - $clientsLastMonth) / $clientsLastMonth) * 100, 1)
            : ($clientsThisMonth > 0 ? 100.0 : 0.0);

        // 6. Revenue Overview: 12-Month Trend (Current Year)
        $monthlyRevenueLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $monthlyRevenueData = array_fill(0, 12, 0.0);

        $yearPayments = Payment::whereIn('status', $verifiedStatuses)
            ->where(function ($q) use ($startOfYear, $endOfYear) {
                $q->whereBetween('verified_at', [$startOfYear, $endOfYear])
                  ->orWhere(function ($sub) use ($startOfYear, $endOfYear) {
                      $sub->whereNull('verified_at')
                          ->whereBetween('created_at', [$startOfYear, $endOfYear]);
                  });
            })
            ->get(['amount_paid', 'verified_at', 'created_at']);

        foreach ($yearPayments as $pmt) {
            $paymentDate = $pmt->verified_at ?? $pmt->created_at;
            if ($paymentDate) {
                $mIdx = (int) Carbon::parse($paymentDate)->format('n') - 1;
                if ($mIdx >= 0 && $mIdx < 12) {
                    $monthlyRevenueData[$mIdx] += (float) $pmt->amount_paid;
                }
            }
        }

        // 7. Bookings by Event Type (Excluding cancelled & declined)
        $eventTypesRaw = Booking::whereNotIn('status', ['cancelled', 'declined'])
            ->select('event_type', DB::raw('count(*) as count'))
            ->groupBy('event_type')
            ->orderByDesc('count')
            ->get();

        $eventTypes = [];
        $eventTypesLabels = [];
        $eventTypesCounts = [];
        $totalEventTypeBookings = 0;
        $paletteColors = ['#be185d', '#ec4899', '#8b5cf6', '#3b82f6', '#10b981', '#f59e0b', '#6b7280'];

        foreach ($eventTypesRaw as $row) {
            $label = ucfirst(str_replace('_', ' ', $row->event_type ?? 'Other'));
            $count = (int) $row->count;
            $eventTypes[] = [
                'name' => $label,
                'count' => $count,
            ];
            $eventTypesLabels[] = $label;
            $eventTypesCounts[] = $count;
            $totalEventTypeBookings += $count;
        }

        foreach ($eventTypes as $index => &$et) {
            $et['percentage'] = $totalEventTypeBookings > 0
                ? round(($et['count'] / $totalEventTypeBookings) * 100, 1)
                : 0.0;
            $et['color'] = $paletteColors[$index % count($paletteColors)];
        }
        unset($et);

        // 8. Upcoming Events (Top 5: event_date >= today, non-terminal, sorted ascending)
        $upcomingEvents = Booking::with('client')
            ->whereDate('event_date', '>=', $now->toDateString())
            ->whereNotIn('status', ['cancelled', 'declined'])
            ->orderBy('event_date', 'asc')
            ->orderBy('event_time', 'asc')
            ->limit(5)
            ->get();

        // 9. Payment Summary
        $paidTotal = (float) Payment::whereIn('status', $verifiedStatuses)->sum('amount_paid');
        $pendingPaymentsTotal = (float) Payment::where('status', 'pending')
            ->whereNull('verified_at')
            ->sum('amount');

        // Sum remaining balance across active, non-terminal bookings
        $balanceDueTotal = (float) Booking::whereNotIn('status', ['cancelled', 'declined', 'completed'])
            ->get()
            ->sum('remaining_balance');

        $paymentSummaryTotal = max(1.0, $paidTotal + $pendingPaymentsTotal + $balanceDueTotal);
        $paymentSummary = [
            'paid' => $paidTotal,
            'paid_pct' => round(($paidTotal / $paymentSummaryTotal) * 100, 1),
            'pending' => $pendingPaymentsTotal,
            'pending_pct' => round(($pendingPaymentsTotal / $paymentSummaryTotal) * 100, 1),
            'balance_due' => $balanceDueTotal,
            'balance_due_pct' => round(($balanceDueTotal / $paymentSummaryTotal) * 100, 1),
        ];

        // 10. Top Packages (Ranked by active booking count)
        $topPackages = Package::withCount(['bookings' => function ($q) {
                $q->whereNotIn('status', ['cancelled', 'declined']);
            }])
            ->orderByDesc('bookings_count')
            ->limit(5)
            ->get();

        // 11. Inventory Status
        $inStockCount = InventoryItem::whereColumn('current_stock', '>', 'min_stock')->count();
        // On-hand low stock (InventoryItem::scopeBelowMinimum), the rule Reports uses too.
        $lowStockCount = InventoryItem::belowMinimum()
            ->where('current_stock', '>', 0)
            ->count();
        $outOfStockCount = InventoryItem::where('current_stock', '<=', 0)->count();
        $totalInventoryItems = InventoryItem::count();

        $inventoryStatus = [
            'total' => $totalInventoryItems,
            'in_stock' => $inStockCount,
            'in_stock_pct' => $totalInventoryItems > 0 ? round(($inStockCount / $totalInventoryItems) * 100, 1) : 0.0,
            'low_stock' => $lowStockCount,
            'low_stock_pct' => $totalInventoryItems > 0 ? round(($lowStockCount / $totalInventoryItems) * 100, 1) : 0.0,
            'out_of_stock' => $outOfStockCount,
            'out_of_stock_pct' => $totalInventoryItems > 0 ? round(($outOfStockCount / $totalInventoryItems) * 100, 1) : 0.0,
        ];

        // 12. Low Stock Items (Top 5 urgent items)
        $lowStockItems = InventoryItem::belowMinimum()
            ->orderBy('current_stock', 'asc')
            ->limit(5)
            ->get();

        // 13. Recent Bookings (Top 5 latest)
        $recentBookings = Booking::with('client')
            ->latest('created_at')
            ->limit(5)
            ->get();

        // 14. Recent Activities (Top 5 from AuditLog, safe from sensitive exposures)
        $recentActivities = AuditLog::with('user')
            ->latest('created_at')
            ->limit(5)
            ->get();

        // 15. Active Alerts (Preserved from existing implementation)
        $activeAlerts = AdminAlert::where('is_read', false)
            ->latest('created_at')
            ->limit(20)
            ->get();

        // 16. Work queues awaiting an admin decision. Booking queues use the same stage
        // definitions as the Bookings page so each count matches the list it links to.
        $attentionQueues = [
            'review' => Booking::whereIn('status', ['pending', 'change_requested', 'cancellation_requested'])->count(),
            'approval' => Booking::whereIn('status', ['approved', 'admin_approved'])->count(),
            'payment' => Booking::where(function ($query) {
                $query->whereIn('status', ['payment_submitted', 'payment_pending'])
                      ->orWhereHas('payments', function ($q) {
                          $q->where('status', 'pending');
                      });
            })->count(),
            'returns' => AssetReturn::where(function ($q) {
                $q->whereIn('status', ['Pending', 'Partially Returned'])
                  ->orWhere('approval_status', 'pending');
            })->count(),
            'stock' => $lowStockCount + $outOfStockCount,
        ];

        return view('admin.dashboard', [
            // Backward compatibility
            'totalBookings' => $totalBookings,
            'pendingBookings' => $pendingBookings,
            'totalUsers' => $totalUsers,
            'activeAlerts' => $activeAlerts,
            'recentBookings' => $recentBookings,

            // KPI Metrics
            'bookingsThisMonth' => $bookingsThisMonth,
            'bookingsLastMonth' => $bookingsLastMonth,
            'bookingsGrowth' => $bookingsGrowth,
            'revenueThisMonth' => $revenueThisMonth,
            'revenueLastMonth' => $revenueLastMonth,
            'revenueGrowth' => $revenueGrowth,
            'revenueThisYear' => $revenueThisYear,
            'revenueAllTime' => $revenueAllTime,
            'activePackagesCount' => $activePackagesCount,
            'totalClientsCount' => $totalClientsCount,
            'clientsGrowth' => $clientsGrowth,

            // Chart Data & Visualizations
            'monthlyRevenueLabels' => $monthlyRevenueLabels,
            'monthlyRevenueData' => $monthlyRevenueData,
            'eventTypes' => $eventTypes,
            'eventTypesLabels' => $eventTypesLabels,
            'eventTypesCounts' => $eventTypesCounts,
            'totalEventTypeBookings' => $totalEventTypeBookings,

            // Operational Sections
            'upcomingEvents' => $upcomingEvents,
            'paymentSummary' => $paymentSummary,
            'topPackages' => $topPackages,
            'inventoryStatus' => $inventoryStatus,
            'lowStockItems' => $lowStockItems,
            'recentActivities' => $recentActivities,
            'attentionQueues' => $attentionQueues,
        ]);
    }
}
