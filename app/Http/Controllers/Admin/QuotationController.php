<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Quotation;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QuotationController extends Controller
{
    /**
     * Display a listing of quotations with status filtering, search, and sorting.
     */
    public function index(Request $request): View
    {
        $allowedStatuses = [
            'active',
            'all',
            Quotation::STATUS_PENDING,
            Quotation::STATUS_ISSUED,
            Quotation::STATUS_ACCEPTED,
            Quotation::STATUS_SUPERSEDED,
        ];

        $rawStatus = (string) $request->input('status', 'active');
        $statusFilter = in_array($rawStatus, $allowedStatuses, true) ? $rawStatus : 'active';

        $allowedSorts = [
            'latest',
            'oldest',
            'amount_high',
            'amount_low',
            'valid_until',
        ];

        $rawSort = (string) $request->input('sort', 'latest');
        $sortOrder = in_array($rawSort, $allowedSorts, true) ? $rawSort : 'latest';

        $searchTerm = trim((string) $request->input('search', ''));

        $query = Quotation::query()->with(['booking.client', 'issuedBy']);

        // 1. Status Filter
        if ($statusFilter === 'active') {
            $query->whereIn('status', [Quotation::STATUS_PENDING, Quotation::STATUS_ISSUED]);
        } elseif ($statusFilter !== 'all') {
            $query->where('status', $statusFilter);
        }

        // 2. Search Filter
        if ($searchTerm !== '') {
            $query->where(function ($q) use ($searchTerm) {
                if (is_numeric($searchTerm)) {
                    $q->orWhere('id', (int) $searchTerm)
                      ->orWhere('booking_id', (int) $searchTerm);
                }

                $q->orWhereHas('booking', function ($bq) use ($searchTerm) {
                    $bq->where('event_type', 'like', "%{$searchTerm}%")
                       ->orWhere('venue', 'like', "%{$searchTerm}%")
                       ->orWhere('guest_name', 'like', "%{$searchTerm}%")
                       ->orWhere('guest_email', 'like', "%{$searchTerm}%")
                       ->orWhereHas('client', function ($cq) use ($searchTerm) {
                           $cq->where('full_name', 'like', "%{$searchTerm}%")
                              ->orWhere('email', 'like', "%{$searchTerm}%");
                       });
                });
            });
        }

        // 3. Sorting
        match ($sortOrder) {
            'oldest' => $query->orderBy('created_at', 'asc')->orderBy('id', 'asc'),
            'amount_high' => $query->orderByDesc('final_quoted_price')->orderByDesc('recommended_price')->orderByDesc('id'),
            'amount_low' => $query->orderBy('final_quoted_price', 'asc')->orderBy('recommended_price', 'asc')->orderBy('id', 'asc'),
            'valid_until' => $query->orderByRaw('valid_until IS NULL, valid_until ASC')->orderByDesc('id'),
            default => $query->orderByDesc('created_at')->orderByDesc('id'),
        };

        // 4. Pagination (preserving query strings)
        $quotations = $query->paginate(15)->withQueryString();

        return view('admin.quotations', [
            'quotations' => $quotations,
            'statusFilter' => $statusFilter,
            'sortOrder' => $sortOrder,
            'searchTerm' => $searchTerm,
        ]);
    }
}
