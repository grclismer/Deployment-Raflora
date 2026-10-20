<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\Package;
use App\Models\PackageImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PackageController extends Controller
{
    /**
     * Display a listing of the packages.
     */
    public function index(Request $request): View
    {
        $status = (string) $request->input('status', 'active');
        $query = Package::with(['inventoryItems', 'images']);

        if ($status === 'archived') {
            $query->where('is_archived', true);
        } elseif ($status === 'all') {
            // show all packages
        } elseif ($status === 'inactive') {
            $query->where(function ($q) {
                $q->where('is_active', false)->orWhere('is_archived', true);
            });
        } else {
            // default active view
            $query->where('is_archived', false);
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('category', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%")
                      ->orWhere('included_items', 'like', "%{$search}%")
                      ->orWhere('package_code', 'like', "%{$search}%");
                });
            }
        }

        if ($request->filled('category') && $request->input('category') !== 'all') {
            $query->where('category', $request->input('category'));
        }

        $sort = (string) $request->input('sort', 'latest');
        match ($sort) {
            'oldest' => $query->orderBy('created_at', 'asc')->orderBy('id', 'asc'),
            'name_asc' => $query->orderBy('title', 'asc'),
            'name_desc' => $query->orderBy('title', 'desc'),
            'price_asc' => $query->orderBy('price', 'asc'),
            'price_desc' => $query->orderBy('price', 'desc'),
            default => $query->orderBy('created_at', 'desc')->orderBy('id', 'desc'),
        };

        $packages = $query->get();

        $categoryCounts = Package::where('is_archived', false)
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->select('category', DB::raw('count(*) as count'))
            ->groupBy('category')
            ->orderBy('category')
            ->pluck('count', 'category')
            ->toArray();

        $totalActiveCount = Package::where('is_archived', false)->count();
        $packageCategories = collect(array_keys($categoryCounts))->sort()->values();

        $inventoryItems = \App\Models\InventoryItem::orderBy('category')->orderBy('name')->get();
        $inventoryCategories = \App\Models\InventoryItem::select('category')->distinct()->pluck('category')->filter()->values();

        return view('admin.packages', [
            'packages' => $packages,
            'packageCategories' => $packageCategories,
            'categoryCounts' => $categoryCounts,
            'totalActiveCount' => $totalActiveCount,
            'inventoryItems' => $inventoryItems,
            'inventoryCategories' => $inventoryCategories,
            'currentSearch' => $request->input('search', ''),
            'currentCategory' => $request->input('category', 'all'),
            'currentStatus' => $status,
            'currentSort' => $sort,
            'currentView' => $request->input('view', 'grid'),
        ]);
    }

    /**
     * Show the form for creating a new package.
     */
    public function create(): View
    {
        $inventoryItems = \App\Models\InventoryItem::whereNull('deleted_at')->orderBy('category')->orderBy('name')->get();
        $inventoryCategories = $inventoryItems->pluck('category')->filter()->unique()->values();
        
        return view('admin.packages.create', [
            'inventoryItems' => $inventoryItems,
            'inventoryCategories' => $inventoryCategories
        ]);
    }

    /**
     * Display a listing of archived packages.
     */
    public function archived(Request $request): View
    {
        $query = Package::with(['inventoryItems', 'images'])->where('is_archived', true);

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('category', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%")
                      ->orWhere('included_items', 'like', "%{$search}%")
                      ->orWhere('package_code', 'like', "%{$search}%");
                });
            }
        }

        if ($request->filled('category') && $request->input('category') !== 'all') {
            $query->where('category', $request->input('category'));
        }

        $sort = (string) $request->input('sort', 'latest');
        match ($sort) {
            'oldest' => $query->orderBy('created_at', 'asc')->orderBy('id', 'asc'),
            'name_asc' => $query->orderBy('title', 'asc'),
            'name_desc' => $query->orderBy('title', 'desc'),
            'price_asc' => $query->orderBy('price', 'asc'),
            'price_desc' => $query->orderBy('price', 'desc'),
            default => $query->orderBy('created_at', 'desc')->orderBy('id', 'desc'),
        };

        $packages = $query->get();
        $packageCategories = Package::select('category')->distinct()->whereNotNull('category')->where('category', '!=', '')->pluck('category')->sort()->values();
        $inventoryItems = \App\Models\InventoryItem::orderBy('category')->orderBy('name')->get();
        $inventoryCategories = \App\Models\InventoryItem::select('category')->distinct()->pluck('category')->filter()->values();

        return view('admin.packages-archived', [
            'packages' => $packages,
            'packageCategories' => $packageCategories,
            'inventoryItems' => $inventoryItems,
            'inventoryCategories' => $inventoryCategories,
            'currentSearch' => $request->input('search', ''),
            'currentCategory' => $request->input('category', 'all'),
            'currentSort' => $sort,
        ]);
    }

    /**
     * Show the form for editing the specified package.
     */
    public function edit(Package $package): View
    {
        $package->load(['inventoryItems', 'images']);
        $attachedIds = $package->inventoryItems->pluck('id')->toArray();
        $inventoryItems = \App\Models\InventoryItem::withTrashed()
            ->where(function ($query) use ($attachedIds) {
                $query->whereNull('deleted_at')
                      ->orWhereIn('id', $attachedIds);
            })
            ->orderBy('category')
            ->orderBy('name')
            ->get();
        $inventoryCategories = $inventoryItems->pluck('category')->filter()->unique()->values();
        
        return view('admin.packages.edit', [
            'package' => $package,
            'inventoryItems' => $inventoryItems,
            'inventoryCategories' => $inventoryCategories
        ]);
    }

    /**
     * Store a newly created package in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'required|string|max:50',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'included_items' => 'nullable',
            'images' => 'nullable|array',
            'images.*' => 'nullable|image|max:2048',
            'is_active' => 'nullable',
            'inventory_items' => 'nullable|array',
            'inventory_items.*' => [
                'required',
                'numeric',
                function ($attribute, $value, $fail) {
                    if ($value <= 0) {
                        $fail('Quantity must be greater than zero.');
                        return;
                    }
                    $parts = explode('.', $attribute);
                    $itemId = $parts[1];
                    $item = \App\Models\InventoryItem::withTrashed()->find($itemId);
                    if (!$item || $item->trashed()) {
                        $fail('The selected inventory item is invalid or has been archived.');
                        return;
                    }
                    $integerUnits = ['pcs', 'stems', 'bunches', 'rolls', 'blocks', 'sets', 'units'];
                    if (in_array(strtolower($item->unit), $integerUnits)) {
                        if (floor($value) != $value) {
                            $fail("Quantity must be a whole number for {$item->unit}.");
                        }
                    }
                }
            ],
        ]);

        $rawInclusions = $request->input('included_items');
        if (is_array($rawInclusions)) {
            $itemsText = array_values(array_filter(array_map('trim', $rawInclusions)));
        } elseif (is_string($rawInclusions)) {
            $trimmed = trim($rawInclusions);
            if (str_starts_with($trimmed, '[')) {
                $decoded = json_decode($trimmed, true);
                $itemsText = is_array($decoded) ? array_values(array_filter(array_map('trim', $decoded))) : [];
            } else {
                $itemsText = array_values(array_filter(array_map('trim', explode(',', $trimmed))));
            }
        } else {
            $itemsText = [];
        }

        $isActive = $request->has('is_active') ? $request->boolean('is_active') : true;
        $uploadedFiles = [];

        try {
            DB::beginTransaction();

            $package = Package::create([
                'title' => $validated['title'],
                'category' => $validated['category'] ?? null,
                'description' => $validated['description'] ?? null,
                'price' => $validated['price'],
                'included_items' => $itemsText,
                'is_active' => $isActive,
                'is_archived' => false,
            ]);

            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {
                    $imagePath = $image->store('packages', 'public');
                    $uploadedFiles[] = $imagePath;
                    \App\Models\PackageImage::create([
                        'package_id' => $package->id,
                        'image_path' => $imagePath,
                    ]);
                }
            }

            // Sync physical inventory BOM mapping
            if (!empty($validated['inventory_items'])) {
                $syncData = [];
                foreach ($validated['inventory_items'] as $itemId => $quantity) {
                    if ($quantity > 0) {
                        $syncData[$itemId] = ['quantity' => $quantity];
                    }
                }
                $package->inventoryItems()->sync($syncData);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            foreach ($uploadedFiles as $path) {
                Storage::disk('public')->delete($path);
            }
            throw $e;
        }

        return back()->with('success', 'Package created successfully.');
    }

    /**
     * Update the specified package in storage.
     */
    public function update(Request $request, Package $package): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'required|string|max:50',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'included_items' => 'nullable',
            'images' => 'nullable|array',
            'images.*' => 'nullable|image|max:2048',
            'remove_images' => 'nullable|string',
            'is_active' => 'nullable',
            'inventory_items' => 'nullable|array',
            'inventory_items.*' => [
                'required',
                'numeric',
                function ($attribute, $value, $fail) use ($package) {
                    if ($value <= 0) {
                        $fail('Quantity must be greater than zero.');
                        return;
                    }
                    $parts = explode('.', $attribute);
                    $itemId = $parts[1];
                    $item = \App\Models\InventoryItem::withTrashed()->find($itemId);
                    if (!$item) {
                        $fail('Invalid inventory item.');
                        return;
                    }
                    $wasAttached = $package->inventoryItems->contains('id', (int) $itemId);
                    if ($item->trashed() && !$wasAttached) {
                        $fail('Archived inventory items cannot be added to packages.');
                        return;
                    }
                    $integerUnits = ['pcs', 'stems', 'bunches', 'rolls', 'blocks', 'sets', 'units'];
                    if (in_array(strtolower($item->unit), $integerUnits)) {
                        if (floor($value) != $value) {
                            $fail("Quantity must be a whole number for {$item->unit}.");
                        }
                    }
                }
            ],
        ]);

        $rawInclusions = $request->input('included_items');
        if (is_array($rawInclusions)) {
            $itemsText = array_values(array_filter(array_map('trim', $rawInclusions)));
        } elseif (is_string($rawInclusions)) {
            $trimmed = trim($rawInclusions);
            if (str_starts_with($trimmed, '[')) {
                $decoded = json_decode($trimmed, true);
                $itemsText = is_array($decoded) ? array_values(array_filter(array_map('trim', $decoded))) : [];
            } else {
                $itemsText = array_values(array_filter(array_map('trim', explode(',', $trimmed))));
            }
        } else {
            $itemsText = [];
        }

        $isActive = $request->has('is_active') ? $request->boolean('is_active') : $package->is_active;

        $hasChanges = false;
        $newItemsText = implode(',', $itemsText);
        $oldItemsText = implode(',', $package->included_items ?? []);

        if (
            $package->title !== $validated['title'] ||
            $package->category !== ($validated['category'] ?? null) ||
            $package->description !== ($validated['description'] ?? null) ||
            (float) $package->price !== (float) $validated['price'] ||
            $oldItemsText !== $newItemsText ||
            (bool) $package->is_active !== (bool) $isActive
        ) {
            $hasChanges = true;
        }

        // Check BOM changes
        $newSyncData = [];
        if (isset($validated['inventory_items'])) {
            foreach ($validated['inventory_items'] as $itemId => $quantity) {
                if ($quantity > 0) {
                    $newSyncData[$itemId] = ['quantity' => $quantity];
                }
            }
        }

        $oldSyncData = [];
        foreach ($package->inventoryItems as $item) {
            $oldSyncData[$item->id] = ['quantity' => $item->pivot->quantity];
        }

        // Compare BOM
        $bomChanged = false;
        if (count($newSyncData) !== count($oldSyncData)) {
            $bomChanged = true;
        } else {
            foreach ($newSyncData as $id => $data) {
                if (!isset($oldSyncData[$id]) || (float) $oldSyncData[$id]['quantity'] !== (float) $data['quantity']) {
                    $bomChanged = true;
                    break;
                }
            }
        }

        if ($bomChanged) {
            $hasChanges = true;
        }

        if ($request->filled('remove_images') || $request->hasFile('images')) {
            $hasChanges = true;
        }

        if (!$hasChanges) {
            return back()->with('info', 'No changes were made to this package.');
        }

        $uploadedFiles = [];
        $imagesToDeleteOnSuccess = [];

        try {
            DB::beginTransaction();

            $package->title = $validated['title'];
            $package->category = $validated['category'] ?? null;
            $package->description = $validated['description'] ?? null;
            $package->price = $validated['price'];
            $package->included_items = $itemsText;
            $package->is_active = $isActive;
            $package->save();

            if ($request->filled('remove_images')) {
                $removeParam = $request->remove_images;
                if (str_starts_with(trim($removeParam), '[')) {
                    $removeImageIds = json_decode($removeParam, true) ?: [];
                } else {
                    $removeImageIds = array_filter(explode(',', $removeParam));
                }
                $imagesToRemove = \App\Models\PackageImage::whereIn('id', $removeImageIds)
                    ->where('package_id', $package->id)
                    ->get();

                foreach ($imagesToRemove as $img) {
                    $imagesToDeleteOnSuccess[] = $img->image_path;
                    $img->delete();
                }
            }

            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {
                    $imagePath = $image->store('packages', 'public');
                    $uploadedFiles[] = $imagePath;
                    \App\Models\PackageImage::create([
                        'package_id' => $package->id,
                        'image_path' => $imagePath,
                    ]);
                }
            }

            if ($bomChanged) {
                $package->inventoryItems()->sync($newSyncData);
            }

            DB::commit();

            foreach ($imagesToDeleteOnSuccess as $delPath) {
                Storage::disk('public')->delete($delPath);
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            foreach ($uploadedFiles as $path) {
                Storage::disk('public')->delete($path);
            }
            throw $e;
        }

        return back()->with('success', 'Package updated successfully.');
    }

    /**
     * Archive the specified package.
     */
    public function archive(Package $package): RedirectResponse
    {
        $package->is_archived = true;
        $package->is_active = false;
        $package->save();

        return back()->with('success', 'Package archived successfully.');
    }

    /**
     * Restore the specified archived package.
     */
    public function restore(Package $package): RedirectResponse
    {
        $package->is_archived = false;
        $package->is_active = true;
        $package->save();

        return back()->with('success', 'Package restored successfully.');
    }

    /**
     * Permanently delete the specified package if safe.
     */
    public function destroy(Package $package): RedirectResponse
    {
        if ($package->bookings()->exists()) {
            return back()->with('error', 'Cannot permanently delete package "' . $package->title . '" because historical bookings reference it. Please archive the package instead to protect historical booking records.');
        }

        DB::transaction(function () use ($package) {
            foreach ($package->images as $img) {
                Storage::disk('public')->delete($img->image_path);
                $img->delete();
            }
            if ($package->image_path) {
                Storage::disk('public')->delete($package->image_path);
            }
            $package->inventoryItems()->detach();
            $package->delete();
        });

        return back()->with('success', 'Package permanently deleted.');
    }

    /**
     * Download the standard CSV template for package master imports.
     */
    public function downloadPackageTemplate(): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="packages_template.csv"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['package_code', 'package_name', 'category', 'description', 'price', 'is_active', 'included_items']);
            fputcsv($handle, ['PKG-WED-001', 'Classic Wedding Package', 'Wedding', 'Elegant floral setup for bridal and entourage', '25000.00', 1, 'Bridal bouquet; Groom boutonniere; 3 Bridesmaid bouquets; 5 Boutonnieres']);
            fputcsv($handle, ['PKG-CORP-001', 'Corporate Executive Setup', 'Corporate', 'Premium floral styling for executive tables', '15000.00', 1, '1 Presidential table centerpiece; 4 VIP centerpieces']);
            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Download the standard CSV template for package materials (BOM) imports.
     */
    public function downloadMaterialsTemplate(): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="package_materials_template.csv"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['package_code', 'item_code', 'quantity']);
            fputcsv($handle, ['PKG-WED-001', 'FRE-0001', 50]);
            fputcsv($handle, ['PKG-WED-001', 'FRE-0002', 30]);
            fputcsv($handle, ['PKG-CORP-001', 'GRE-0001', 15]);
            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Export non-archived packages as a CSV file.
     */
    public function exportPackagesCsv(): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="packages_export_' . now()->format('Y-m-d') . '.csv"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $packages = Package::where('is_archived', false)->orderBy('package_code')->get();

        return response()->stream(function () use ($packages) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['package_code', 'package_name', 'category', 'description', 'price', 'is_active', 'included_items']);

            foreach ($packages as $package) {
                $packageCode = $this->sanitizeCsvFormula((string) $package->package_code);
                $packageName = $this->sanitizeCsvFormula((string) $package->title);
                $category = $this->sanitizeCsvFormula((string) $package->category);
                $description = $this->sanitizeCsvFormula((string) ($package->description ?? ''));
                $price = number_format((float) $package->price, 2, '.', '');
                $isActive = $package->is_active ? 1 : 0;

                $included = '';
                if (is_array($package->included_items)) {
                    $included = implode('; ', $package->included_items);
                } elseif (is_string($package->included_items)) {
                    $included = $package->included_items;
                }
                $included = $this->sanitizeCsvFormula($included);

                fputcsv($handle, [
                    $packageCode,
                    $packageName,
                    $category,
                    $description,
                    $price,
                    $isActive,
                    $included,
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Export package BOM relationships as a CSV file.
     */
    public function exportMaterialsCsv(): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="package_materials_export_' . now()->format('Y-m-d') . '.csv"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $packages = Package::with(['inventoryItems' => function ($query) {
            $query->orderBy('item_code');
        }])->where('is_archived', false)->orderBy('package_code')->get();

        return response()->stream(function () use ($packages) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['package_code', 'item_code', 'quantity']);

            foreach ($packages as $package) {
                foreach ($package->inventoryItems as $item) {
                    if (!empty($item->item_code)) {
                        $packageCode = $this->sanitizeCsvFormula((string) $package->package_code);
                        $itemCode = $this->sanitizeCsvFormula((string) $item->item_code);
                        $qty = (float) $item->pivot->quantity;

                        fputcsv($handle, [
                            $packageCode,
                            $itemCode,
                            $qty,
                        ]);
                    }
                }
            }

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Import packages from an uploaded CSV file with transactional upsert.
     */
    public function importPackagesCsv(Request $request): RedirectResponse
    {
        $request->validate([
            'csv_file' => 'required|file',
        ]);

        $file = $request->file('csv_file');

        if (!$file->isValid()) {
            return redirect()->route('admin.packages.index')
                ->with('error', 'Package CSV import failed. No changes were made.')
                ->withErrors(['csv_file' => 'Uploaded file is invalid or corrupted.']);
        }

        $extension = strtolower($file->getClientOriginalExtension());
        if ($extension !== 'csv') {
            return redirect()->route('admin.packages.index')
                ->with('error', 'Package CSV import failed. No changes were made.')
                ->withErrors(['csv_file' => 'The uploaded file must be a CSV file (.csv).']);
        }

        $filePath = $file->getRealPath();
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            return redirect()->route('admin.packages.index')
                ->with('error', 'Package CSV import failed. No changes were made.')
                ->withErrors(['csv_file' => 'Could not read the uploaded CSV file.']);
        }

        $rawHeader = fgetcsv($handle);
        if ($rawHeader === false || empty($rawHeader)) {
            fclose($handle);
            return redirect()->route('admin.packages.index')
                ->with('error', 'Package CSV import failed. No changes were made.')
                ->withErrors(['csv_file' => 'The uploaded CSV file is empty.']);
        }

        if (isset($rawHeader[0])) {
            $rawHeader[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $rawHeader[0]);
        }

        $header = array_map(fn($col) => trim(strtolower((string) $col)), $rawHeader);
        $expectedHeader1 = ['package_code', 'package_name', 'category', 'description', 'price', 'is_active', 'included_items'];
        $expectedHeader2 = ['package_code', 'title', 'category', 'description', 'price', 'is_active', 'included_items'];

        if ($header !== $expectedHeader1 && $header !== $expectedHeader2) {
            fclose($handle);
            return redirect()->route('admin.packages.index')
                ->with('error', 'Package CSV import failed. No changes were made.')
                ->withErrors(['csv_file' => 'Invalid CSV header. Expected: ' . implode(',', $expectedHeader1)]);
        }

        $rows = [];
        $seenCodes = [];
        $rowNumber = 1;

        while (($data = fgetcsv($handle)) !== false) {
            $rowNumber++;

            if (empty($data) || (count($data) === 1 && $data[0] === null)) {
                continue;
            }
            if (count(array_filter($data, fn($v) => trim((string)$v) !== '')) === 0) {
                continue;
            }

            if (count($data) !== 7) {
                fclose($handle);
                return redirect()->route('admin.packages.index')
                    ->with('error', 'Package CSV import failed. No changes were made.')
                    ->withErrors(['csv_file' => "Import failed on row {$rowNumber}: Row has " . count($data) . " columns, expected 7."]);
            }

            // package_code
            $packageCode = trim($this->stripCsvFormula((string) $data[0]));
            if (mb_strlen($packageCode) > 20) {
                fclose($handle);
                return redirect()->route('admin.packages.index')
                    ->with('error', 'Package CSV import failed. No changes were made.')
                    ->withErrors(['csv_file' => "Import failed on row {$rowNumber}: package_code cannot exceed 20 characters."]);
            }
            if ($packageCode !== '') {
                $codeKey = mb_strtolower($packageCode);
                if (isset($seenCodes[$codeKey])) {
                    fclose($handle);
                    return redirect()->route('admin.packages.index')
                        ->with('error', 'Package CSV import failed. No changes were made.')
                        ->withErrors(['csv_file' => "Import failed on row {$rowNumber}: Duplicate package code '{$packageCode}' found in the CSV file (first seen on row {$seenCodes[$codeKey]})."]);
                }
                $seenCodes[$codeKey] = $rowNumber;
            }

            // package_name / title
            $title = trim($this->stripCsvFormula((string) $data[1]));
            if ($title === '') {
                fclose($handle);
                return redirect()->route('admin.packages.index')
                    ->with('error', 'Package CSV import failed. No changes were made.')
                    ->withErrors(['csv_file' => "Import failed on row {$rowNumber}: package_name is required."]);
            }
            if (mb_strlen($title) > 255) {
                fclose($handle);
                return redirect()->route('admin.packages.index')
                    ->with('error', 'Package CSV import failed. No changes were made.')
                    ->withErrors(['csv_file' => "Import failed on row {$rowNumber}: package_name cannot exceed 255 characters."]);
            }

            // category
            $category = trim($this->stripCsvFormula((string) $data[2]));
            if ($category === '') {
                fclose($handle);
                return redirect()->route('admin.packages.index')
                    ->with('error', 'Package CSV import failed. No changes were made.')
                    ->withErrors(['csv_file' => "Import failed on row {$rowNumber}: category is required."]);
            }
            if (mb_strlen($category) > 50) {
                fclose($handle);
                return redirect()->route('admin.packages.index')
                    ->with('error', 'Package CSV import failed. No changes were made.')
                    ->withErrors(['csv_file' => "Import failed on row {$rowNumber}: category cannot exceed 50 characters."]);
            }

            // description
            $description = trim($this->stripCsvFormula((string) $data[3]));

            // price
            $priceRaw = trim((string) $data[4]);
            if ($priceRaw === '') {
                fclose($handle);
                return redirect()->route('admin.packages.index')
                    ->with('error', 'Package CSV import failed. No changes were made.')
                    ->withErrors(['csv_file' => "Import failed on row {$rowNumber}: price is required."]);
            }
            if (!is_numeric($priceRaw)) {
                fclose($handle);
                return redirect()->route('admin.packages.index')
                    ->with('error', 'Package CSV import failed. No changes were made.')
                    ->withErrors(['csv_file' => "Import failed on row {$rowNumber}: price must be a number."]);
            }
            if ((float) $priceRaw < 0) {
                fclose($handle);
                return redirect()->route('admin.packages.index')
                    ->with('error', 'Package CSV import failed. No changes were made.')
                    ->withErrors(['csv_file' => "Import failed on row {$rowNumber}: price cannot be negative."]);
            }
            $price = (float) $priceRaw;

            // is_active
            $activeRaw = strtolower(trim((string) $data[5]));
            if (in_array($activeRaw, ['1', 'true'], true)) {
                $isActive = true;
            } elseif (in_array($activeRaw, ['0', 'false'], true)) {
                $isActive = false;
            } else {
                fclose($handle);
                return redirect()->route('admin.packages.index')
                    ->with('error', 'Package CSV import failed. No changes were made.')
                    ->withErrors(['csv_file' => "Import failed on row {$rowNumber}: is_active must be 1, 0, true, or false."]);
            }

            // included_items
            $inclusionsRaw = trim($this->stripCsvFormula((string) $data[6]));
            $inclusions = [];
            if ($inclusionsRaw !== '') {
                $delim = str_contains($inclusionsRaw, ';') ? ';' : (str_contains($inclusionsRaw, '|') ? '|' : ',');
                $inclusions = array_values(array_filter(array_map('trim', explode($delim, $inclusionsRaw))));
            }

            $rows[] = [
                'package_code' => $packageCode,
                'title' => $title,
                'category' => $category,
                'description' => $description !== '' ? $description : null,
                'price' => $price,
                'is_active' => $isActive,
                'included_items' => $inclusions,
            ];
        }

        fclose($handle);

        if (empty($rows)) {
            return redirect()->route('admin.packages.index')
                ->with('error', 'Package CSV import failed. No changes were made.')
                ->withErrors(['csv_file' => 'The uploaded CSV file contains no data rows.']);
        }

        $createdCount = 0;
        $updatedCount = 0;

        try {
            DB::transaction(function () use ($rows, &$createdCount, &$updatedCount) {
                foreach ($rows as $row) {
                    $package = null;
                    if ($row['package_code'] !== '') {
                        $package = Package::where('package_code', $row['package_code'])->first();
                    }

                    if ($package) {
                        $package->update([
                            'title' => $row['title'],
                            'category' => $row['category'],
                            'description' => $row['description'],
                            'price' => $row['price'],
                            'is_active' => $row['is_active'],
                            'included_items' => $row['included_items'],
                        ]);
                        $updatedCount++;
                    } else {
                        $createData = [
                            'title' => $row['title'],
                            'category' => $row['category'],
                            'description' => $row['description'],
                            'price' => $row['price'],
                            'is_active' => $row['is_active'],
                            'included_items' => $row['included_items'],
                        ];
                        if ($row['package_code'] !== '') {
                            $createData['package_code'] = $row['package_code'];
                        }
                        Package::create($createData);
                        $createdCount++;
                    }
                }
            });
        } catch (\Throwable $e) {
            return redirect()->route('admin.packages.index')
                ->with('error', 'Package CSV import failed due to a database error. All changes were rolled back.')
                ->withErrors(['csv_file' => 'Import transaction failed: ' . $e->getMessage()]);
        }

        return redirect()->route('admin.packages.index')
            ->with('success', "Package CSV imported successfully. {$createdCount} package(s) created, {$updatedCount} updated.");
    }

    /**
     * Import package materials (BOM) from an uploaded CSV file with transactional synchronization.
     */
    public function importMaterialsCsv(Request $request): RedirectResponse
    {
        $request->validate([
            'csv_file' => 'required|file',
        ]);

        $file = $request->file('csv_file');

        if (!$file->isValid()) {
            return redirect()->route('admin.packages.index')
                ->with('error', 'Package materials CSV import failed. No changes were made.')
                ->withErrors(['csv_file' => 'Uploaded file is invalid or corrupted.']);
        }

        $extension = strtolower($file->getClientOriginalExtension());
        if ($extension !== 'csv') {
            return redirect()->route('admin.packages.index')
                ->with('error', 'Package materials CSV import failed. No changes were made.')
                ->withErrors(['csv_file' => 'The uploaded file must be a CSV file (.csv).']);
        }

        $filePath = $file->getRealPath();
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            return redirect()->route('admin.packages.index')
                ->with('error', 'Package materials CSV import failed. No changes were made.')
                ->withErrors(['csv_file' => 'Could not read the uploaded CSV file.']);
        }

        $rawHeader = fgetcsv($handle);
        if ($rawHeader === false || empty($rawHeader)) {
            fclose($handle);
            return redirect()->route('admin.packages.index')
                ->with('error', 'Package materials CSV import failed. No changes were made.')
                ->withErrors(['csv_file' => 'The uploaded CSV file is empty.']);
        }

        if (isset($rawHeader[0])) {
            $rawHeader[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $rawHeader[0]);
        }

        $header = array_map(fn($col) => trim(strtolower((string) $col)), $rawHeader);
        $expectedHeader = ['package_code', 'item_code', 'quantity'];

        if ($header !== $expectedHeader) {
            fclose($handle);
            return redirect()->route('admin.packages.index')
                ->with('error', 'Package materials CSV import failed. No changes were made.')
                ->withErrors(['csv_file' => 'Invalid CSV header. Expected: ' . implode(',', $expectedHeader)]);
        }

        $packageBomMap = [];
        $seenPairs = [];
        $rowNumber = 1;
        $integerUnits = ['pcs', 'stems', 'bunches', 'rolls', 'blocks', 'sets', 'units'];

        while (($data = fgetcsv($handle)) !== false) {
            $rowNumber++;

            if (empty($data) || (count($data) === 1 && $data[0] === null)) {
                continue;
            }
            if (count(array_filter($data, fn($v) => trim((string)$v) !== '')) === 0) {
                continue;
            }

            if (count($data) !== 3) {
                fclose($handle);
                return redirect()->route('admin.packages.index')
                    ->with('error', 'Package materials CSV import failed. No changes were made.')
                    ->withErrors(['csv_file' => "Import failed on row {$rowNumber}: Row has " . count($data) . " columns, expected 3."]);
            }

            // package_code
            $packageCode = trim($this->stripCsvFormula((string) $data[0]));
            if ($packageCode === '') {
                fclose($handle);
                return redirect()->route('admin.packages.index')
                    ->with('error', 'Package materials CSV import failed. No changes were made.')
                    ->withErrors(['csv_file' => "Import failed on row {$rowNumber}: package_code is required."]);
            }

            $package = Package::where('package_code', $packageCode)->first();
            if (!$package) {
                fclose($handle);
                return redirect()->route('admin.packages.index')
                    ->with('error', 'Package materials CSV import failed. No changes were made.')
                    ->withErrors(['csv_file' => "Import failed on row {$rowNumber}: Unknown package code '{$packageCode}'."]);
            }

            // item_code
            $itemCode = trim($this->stripCsvFormula((string) $data[1]));
            if ($itemCode === '') {
                fclose($handle);
                return redirect()->route('admin.packages.index')
                    ->with('error', 'Package materials CSV import failed. No changes were made.')
                    ->withErrors(['csv_file' => "Import failed on row {$rowNumber}: item_code is required."]);
            }

            $item = InventoryItem::withTrashed()->where('item_code', $itemCode)->first();
            if (!$item) {
                fclose($handle);
                return redirect()->route('admin.packages.index')
                    ->with('error', 'Package materials CSV import failed. No changes were made.')
                    ->withErrors(['csv_file' => "Import failed on row {$rowNumber}: Unknown inventory item code '{$itemCode}'."]);
            }

            // Duplicate pair check within file
            $pairKey = mb_strtolower($packageCode) . '::' . mb_strtolower($itemCode);
            if (isset($seenPairs[$pairKey])) {
                fclose($handle);
                return redirect()->route('admin.packages.index')
                    ->with('error', 'Package materials CSV import failed. No changes were made.')
                    ->withErrors(['csv_file' => "Import failed on row {$rowNumber}: Duplicate package and material mapping ('{$packageCode}' - '{$itemCode}') found in CSV (first seen on row {$seenPairs[$pairKey]})."]);
            }
            $seenPairs[$pairKey] = $rowNumber;

            // Archived item policy check
            if ($item->trashed()) {
                $wasAttached = $package->inventoryItems()->where('inventory_item_id', $item->id)->exists();
                if (!$wasAttached) {
                    fclose($handle);
                    return redirect()->route('admin.packages.index')
                        ->with('error', 'Package materials CSV import failed. No changes were made.')
                        ->withErrors(['csv_file' => "Import failed on row {$rowNumber}: Archived inventory item '{$itemCode}' cannot be newly added to package '{$packageCode}'."]);
                }
            }

            // quantity
            $quantityRaw = trim((string) $data[2]);
            if ($quantityRaw === '') {
                fclose($handle);
                return redirect()->route('admin.packages.index')
                    ->with('error', 'Package materials CSV import failed. No changes were made.')
                    ->withErrors(['csv_file' => "Import failed on row {$rowNumber}: quantity is required."]);
            }
            if (!is_numeric($quantityRaw)) {
                fclose($handle);
                return redirect()->route('admin.packages.index')
                    ->with('error', 'Package materials CSV import failed. No changes were made.')
                    ->withErrors(['csv_file' => "Import failed on row {$rowNumber}: quantity must be a number."]);
            }
            $quantity = (float) $quantityRaw;
            if ($quantity <= 0) {
                fclose($handle);
                return redirect()->route('admin.packages.index')
                    ->with('error', 'Package materials CSV import failed. No changes were made.')
                    ->withErrors(['csv_file' => "Import failed on row {$rowNumber}: Quantity must be greater than zero."]);
            }

            if (in_array(strtolower($item->unit), $integerUnits) && floor($quantity) != $quantity) {
                fclose($handle);
                return redirect()->route('admin.packages.index')
                    ->with('error', 'Package materials CSV import failed. No changes were made.')
                    ->withErrors(['csv_file' => "Import failed on row {$rowNumber}: Quantity must be a whole number for {$item->unit}."]);
            }

            $packageBomMap[$package->id][$item->id] = ['quantity' => $quantity];
        }

        fclose($handle);

        if (empty($packageBomMap)) {
            return redirect()->route('admin.packages.index')
                ->with('error', 'Package materials CSV import failed. No changes were made.')
                ->withErrors(['csv_file' => 'The uploaded CSV file contains no data rows.']);
        }

        $totalMappings = 0;
        $packageCount = count($packageBomMap);

        try {
            DB::transaction(function () use ($packageBomMap, &$totalMappings) {
                foreach ($packageBomMap as $packageId => $syncData) {
                    $pkg = Package::find($packageId);
                    if ($pkg) {
                        $pkg->inventoryItems()->sync($syncData);
                        $totalMappings += count($syncData);
                    }
                }
            });
        } catch (\Throwable $e) {
            return redirect()->route('admin.packages.index')
                ->with('error', 'Package materials CSV import failed due to a database error. All changes were rolled back.')
                ->withErrors(['csv_file' => 'Import transaction failed: ' . $e->getMessage()]);
        }

        return redirect()->route('admin.packages.index')
            ->with('success', "Package materials imported successfully. {$totalMappings} material mapping(s) synchronized across {$packageCount} package(s).");
    }

    /**
     * Mitigate CSV spreadsheet formula injection on export.
     */
    private function sanitizeCsvFormula(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        if (preg_match('/^[=\+\-@]/', $value)) {
            return "'" . $value;
        }
        return $value;
    }

    /**
     * Strip leading apostrophe from cell values if added for formula injection defense.
     */
    private function stripCsvFormula(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        $value = trim($value);
        if (str_starts_with($value, "'") && strlen($value) > 1 && in_array($value[1], ['=', '+', '-', '@'], true)) {
            return substr($value, 1);
        }
        return $value;
    }
}

