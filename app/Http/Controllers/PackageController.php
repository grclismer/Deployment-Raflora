<?php

namespace App\Http\Controllers;

use App\Models\Package;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PackageController extends Controller
{
    /**
     * Display a listing of active packages.
     */
    public function index(Request $request): View
    {
        $query = Package::where('is_active', true)
            ->where('is_archived', false)
            ->with('images');

        // Search by title or description
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filter by category
        if ($category = $request->input('category')) {
            $query->where('category', $category);
        }

        // Apply sorting
        $sort = $request->input('sort', 'price_asc');
        switch ($sort) {
            case 'price_desc':
                $query->orderBy('price', 'desc');
                break;
            case 'name_asc':
                $query->orderBy('title', 'asc');
                break;
            case 'price_asc':
            default:
                $query->orderBy('price', 'asc');
                break;
        }

        $packages = $query->paginate(8)->withQueryString();

        $categories = Package::where('is_active', true)->where('is_archived', false)
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->pluck('category')
            ->sort()
            ->values();

        return view('packages.index', [
            'packages' => $packages,
            'search' => $search,
            'category' => $category,
            'sort' => $sort,
            'categories' => $categories,
        ]);
    }
}
