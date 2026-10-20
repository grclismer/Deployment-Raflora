<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Gallery;
use App\Models\GalleryImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class GalleryController extends Controller
{
    public function index(Request $request)
    {
        $query = Gallery::where('is_archived', false)->with('images');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('event_type', 'like', "%{$search}%")
                  ->orWhere('theme', 'like', "%{$search}%");
            });
        }
        
        if ($request->filled('event_type')) {
            $query->where('event_type', $request->event_type);
        }
        
        if ($request->filled('theme')) {
            $query->where('theme', $request->theme);
        }
        
        if ($request->filled('year')) {
            $query->whereYear('event_date', $request->year);
        }

        if ($request->get('sort') === 'oldest') {
            $query->oldest('event_date');
        } else {
            $query->latest('event_date');
        }

        $galleries = $query->paginate(12)->withQueryString();

        // For filters dropdown
        $allGalleries = Gallery::where('is_archived', false)->select('event_type', 'theme', 'event_date')->get();
        $eventTypes = $allGalleries->pluck('event_type')->unique()->sort()->values();
        $themes = $allGalleries->pluck('theme')->unique()->sort()->values();
        $years = $allGalleries->pluck('event_date')->map(function($d) {
            return date('Y', strtotime($d));
        })->unique()->sortDesc()->values();

        return view('admin.gallery.index', compact('galleries', 'eventTypes', 'themes', 'years'));
    }

    public function archived(Request $request)
    {
        $query = Gallery::where('is_archived', true)->with('images');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('event_type', 'like', "%{$search}%")
                  ->orWhere('theme', 'like', "%{$search}%");
            });
        }
        
        if ($request->filled('event_type')) {
            $query->where('event_type', $request->event_type);
        }
        
        if ($request->filled('theme')) {
            $query->where('theme', $request->theme);
        }
        
        if ($request->filled('year')) {
            $query->whereYear('event_date', $request->year);
        }

        if ($request->get('sort') === 'oldest') {
            $query->oldest('event_date');
        } else {
            $query->latest('event_date');
        }

        $galleries = $query->paginate(12)->withQueryString();

        // For filters dropdown
        $allGalleries = Gallery::where('is_archived', true)->select('event_type', 'theme', 'event_date')->get();
        $eventTypes = $allGalleries->pluck('event_type')->unique()->sort()->values();
        $themes = $allGalleries->pluck('theme')->unique()->sort()->values();
        $years = $allGalleries->pluck('event_date')->map(function($d) {
            return date('Y', strtotime($d));
        })->unique()->sortDesc()->values();

        return view('admin.gallery.archived', compact('galleries', 'eventTypes', 'themes', 'years'));
    }

    public function create()
    {
        return view('admin.gallery.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'event_date' => 'required|date|before_or_equal:today',
            'event_type' => 'required|string|max:255',
            'theme' => 'required|string|max:255',
            'images' => 'required|array|min:1',
            'images.*' => 'required|image|mimes:jpeg,png,jpg,gif|max:5120', // 5MB max
        ], [
            'event_date.before_or_equal' => 'The event date cannot be in the future.',
        ]);

        return DB::transaction(function () use ($validated, $request) {
            // Server-side guard: Prevent duplicate creation from rapid spam submissions
            $recentDuplicate = Gallery::where('title', $validated['title'])
                ->where('event_date', $validated['event_date'])
                ->where('event_type', $validated['event_type'])
                ->where('theme', $validated['theme'])
                ->where('created_at', '>=', now()->subSeconds(15))
                ->first();

            if ($recentDuplicate) {
                return redirect()->route('admin.gallery')->with('success', 'Gallery created successfully.');
            }

            $gallery = Gallery::create([
                'title' => $validated['title'],
                'event_date' => $validated['event_date'],
                'event_type' => $validated['event_type'],
                'theme' => $validated['theme'],
            ]);

            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {
                    $path = $image->store('galleries/' . $gallery->id, 'public');

                    GalleryImage::create([
                        'gallery_id' => $gallery->id,
                        'image_path' => $path,
                    ]);
                }
            }

            return redirect()->route('admin.gallery')->with('success', 'Gallery created successfully.');
        });
    }

    public function edit(Gallery $gallery)
    {
        return view('admin.gallery.edit', compact('gallery'));
    }

    public function update(Request $request, Gallery $gallery)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'event_date' => 'required|date|before_or_equal:today',
            'event_type' => 'required|string|max:255',
            'theme' => 'required|string|max:255',
            'images' => 'nullable|array',
            'images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120', // 5MB max
        ], [
            'event_date.before_or_equal' => 'The event date cannot be in the future.',
        ]);

        $gallery->update([
            'title' => $validated['title'],
            'event_date' => $validated['event_date'],
            'event_type' => $validated['event_type'],
            'theme' => $validated['theme'],
        ]);

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = $image->store('galleries/' . $gallery->id, 'public');

                GalleryImage::create([
                    'gallery_id' => $gallery->id,
                    'image_path' => $path,
                ]);
            }
        }

        return redirect()->route('admin.gallery')->with('success', 'Gallery updated successfully.');
    }

    public function destroy(Gallery $gallery)
    {
        $gallery->update(['is_archived' => true]);

        return redirect()->route('admin.gallery')->with('success', 'Gallery archived successfully.');
    }

    public function restore(Gallery $gallery)
    {
        $gallery->update(['is_archived' => false]);

        return redirect()->route('admin.gallery.archived')->with('success', 'Gallery restored successfully.');
    }
}
