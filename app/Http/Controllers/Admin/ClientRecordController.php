<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Client;
use Illuminate\Http\Request;

class ClientRecordController extends Controller
{
    /**
     * Display a listing of client records with search, activity filtering, and sorting.
     */
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $activity = (string) $request->query('activity', 'all');
        $sort = (string) $request->query('sort', 'name_asc');

        $query = Client::withCount('bookings')
            ->with(['bookings' => function ($q) {
                $q->latest();
            }]);

        // Search: name, email, or phone
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Booking Activity Filter
        if ($activity === 'has_bookings') {
            $query->has('bookings');
        } elseif ($activity === 'no_bookings') {
            $query->doesntHave('bookings');
        }

        // Sorting
        switch ($sort) {
            case 'name_desc':
                $query->orderBy('full_name', 'desc');
                break;
            case 'latest_activity':
                $latestBookingSub = Booking::select('created_at')
                    ->whereColumn('bookings.client_id', 'clients.id')
                    ->latest()
                    ->limit(1);
                $query->orderByDesc($latestBookingSub)->orderBy('full_name', 'asc');
                break;
            case 'most_bookings':
                $query->orderByDesc('bookings_count')->orderBy('full_name', 'asc');
                break;
            case 'name_asc':
            default:
                $query->orderBy('full_name', 'asc');
                break;
        }

        $hasActiveFilters = ($activity !== 'all' && !empty($activity)) || ($sort !== 'name_asc' && !empty($sort));

        // Five clients per page; the page links keep the search, filter and sort.
        $clients = $query->paginate(5)->withQueryString();

        return view('admin.client-records', [
            'clients' => $clients,
            'currentSearch' => $search,
            'currentActivity' => $activity,
            'currentSort' => $sort,
            'hasActiveFilters' => $hasActiveFilters,
        ]);
    }
}
