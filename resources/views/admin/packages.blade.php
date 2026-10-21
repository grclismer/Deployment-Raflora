<x-admin-layout
    title="Package Management"
    description="Manage public booking packages, pricing, and master inventory mappings (BOM)."
>

    <!-- Header Row: Navigation Tabs & Top Actions (Single Clean Row, No Duplicate Title) -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6 border-b border-gray-200">
        <!-- Tabs (Strict Navigation Preservation) -->
        <nav class="-mb-px flex space-x-8" aria-label="Tabs">
            <a href="{{ route('admin.packages.index') }}" class="border-brand-700 text-brand-700 whitespace-nowrap py-3 px-1 border-b-2 font-bold text-sm flex items-center gap-2">
                <i class="fa-solid fa-boxes-packing text-xs text-brand-700"></i>
                <span>Active Packages</span>
            </a>
            <a href="{{ route('admin.packages.archived') }}" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm flex items-center gap-2">
                <i class="fa-solid fa-box-archive text-xs text-gray-400"></i>
                <span>Archived Packages</span>
            </a>
        </nav>

        <!-- Top Actions Bar: View Archived & + Add Package -->
        <div class="flex items-center gap-3 shrink-0 pb-3 sm:pb-0">
            <a href="{{ route('admin.packages.archived') }}" class="inline-flex items-center gap-2 px-4 py-2 border border-gray-200 hover:border-brand-300 bg-white hover:bg-brand-50/50 text-gray-700 hover:text-brand-800 text-sm font-semibold rounded-xl transition shadow-2xs focus:outline-none focus:ring-2 focus:ring-brand-500">
                <i class="fa-solid fa-box-archive text-brand-700 text-xs"></i>
                <span>View Archived</span>
            </a>
            <button type="button" onclick="openAddPackageModal()" class="inline-flex items-center gap-1.5 px-4 py-2 bg-brand-700 hover:bg-brand-800 text-white font-semibold text-sm rounded-xl shadow-xs hover:shadow transition focus:outline-none focus:ring-2 focus:ring-brand-500 whitespace-nowrap cursor-pointer">
                <i class="fa-solid fa-plus text-xs"></i>
                <span>Add Package</span>
                <a href="{{ route('admin.packages.create') }}" class="sr-only" aria-hidden="true">Add Package</a>
            </button>
        </div>
    </div>

    <!-- Category Pills & Toolbar Row (Matching Approved Catalogue Design) -->
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-6">
        <!-- Category dropdown with the existing selected-pill styling -->
        <div class="relative self-start" id="packageCategoryDropdown">
            @php
                $isAllSelected = ($currentCategory ?? 'all') === 'all';
                $selectedCategoryCount = $isAllSelected
                    ? ($totalActiveCount ?? $packages->count())
                    : ($categoryCounts[$currentCategory] ?? 0);
            @endphp
            <button
                type="button"
                id="packageCategoryButton"
                onclick="togglePackageCategoryDropdown()"
                aria-haspopup="true"
                aria-expanded="false"
                aria-controls="packageCategoryMenu"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs sm:text-sm font-semibold whitespace-nowrap transition shadow-2xs bg-brand-700 text-white font-bold shadow-xs"
            >
                <span>{{ $isAllSelected ? 'All Packages' : $currentCategory }}</span>
                <span>({{ $selectedCategoryCount }})</span>
                <i id="packageCategoryChevron" class="fa-solid fa-chevron-down text-[10px] transition-transform duration-200"></i>
            </button>

            <div
                id="packageCategoryMenu"
                role="menu"
                aria-labelledby="packageCategoryButton"
                style="display: none; width: min(24rem, calc(100vw - 2rem));"
                class="absolute left-0 top-full z-40 mt-2 flex flex-wrap gap-2 rounded-2xl border border-gray-100 bg-white p-3 shadow-xl"
            >
                <a
                    href="{{ route('admin.packages.index', array_merge(request()->query(), ['category' => 'all'])) }}"
                    role="menuitem"
                    class="px-4 py-2 rounded-xl text-xs sm:text-sm font-semibold whitespace-nowrap transition shadow-2xs {{ $isAllSelected ? 'bg-brand-700 text-white font-bold shadow-xs' : 'bg-white border border-gray-200 text-gray-700 hover:border-brand-300 hover:bg-brand-50/30' }}"
                >
                    All Packages ({{ $totalActiveCount ?? $packages->count() }})
                </a>

                @foreach($categoryCounts as $catName => $catCount)
                    @php
                        $isCatSelected = ($currentCategory ?? '') === $catName;
                    @endphp
                    <a
                        href="{{ route('admin.packages.index', array_merge(request()->query(), ['category' => $catName])) }}"
                        role="menuitem"
                        class="px-4 py-2 rounded-xl text-xs sm:text-sm font-semibold whitespace-nowrap transition shadow-2xs {{ $isCatSelected ? 'bg-brand-700 text-white font-bold shadow-xs' : 'bg-white border border-gray-200 text-gray-700 hover:border-brand-300 hover:bg-brand-50/30' }}"
                    >
                        {{ $catName }} ({{ $catCount }})
                    </a>
                @endforeach
            </div>
        </div>

        <!-- Controls: Show Filters, Package Tools, and Grid/Table View Toggle -->
        <div class="flex max-w-full flex-wrap items-center justify-start gap-2 self-stretch md:shrink-0 md:self-center md:justify-end">
            @php
                $hasActivePackageFilters = (($currentCategory ?? 'all') !== 'all') || (($currentSort ?? 'latest') !== 'latest') || (($currentStatus ?? 'active') !== 'active') || !empty($currentSearch);
                $isPackageFilterOpen = request('filter_expanded') === '1' || $hasActivePackageFilters;
            @endphp

            <!-- Show Filters Toggle Button -->
            <button
                type="button"
                id="packageToggleFiltersBtn"
                onclick="togglePackageFilterPanel()"
                aria-expanded="{{ $isPackageFilterOpen ? 'true' : 'false' }}"
                aria-controls="packageFilterPanel"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 border rounded-xl text-sm font-medium transition shadow-2xs focus:outline-none focus:ring-2 focus:ring-brand-500 cursor-pointer {{ $hasActivePackageFilters ? 'border-brand-300 bg-brand-50 text-brand-700 font-semibold' : 'border-gray-200 hover:border-brand-300 bg-white hover:bg-brand-50/50 text-gray-700 hover:text-brand-800' }}"
            >
                <i class="fa-solid fa-sliders text-xs {{ $hasActivePackageFilters ? 'text-brand-700' : 'text-gray-500' }}"></i>
                <span id="packageToggleFiltersText">{{ $isPackageFilterOpen ? 'Hide Filters' : 'Show Filters' }}</span>
                @if($hasActivePackageFilters)
                    <span class="w-1.5 h-1.5 rounded-full bg-brand-700 inline-block" title="Filters are active"></span>
                @endif
                <i id="packageFiltersChevron" class="fa-solid fa-chevron-down text-[10px] transition-transform duration-200 {{ $isPackageFilterOpen ? 'rotate-180' : '' }}"></i>
            </button>

            <!-- Package Tools Dropdown Container -->
            <div class="relative" id="packageToolsContainer">
                <button type="button" id="packageToolsButton" onclick="togglePackageToolsDropdown()" aria-haspopup="true" aria-expanded="false" aria-controls="packageToolsMenu" class="inline-flex items-center gap-2 px-3.5 py-2 border border-brand-300 hover:border-brand-800 bg-white hover:bg-brand-50/50 text-brand-700 font-semibold text-sm rounded-xl transition shadow-2xs focus:outline-none focus:ring-2 focus:ring-brand-500 cursor-pointer">
                    <i class="fa-regular fa-file-lines text-brand-700 text-sm"></i>
                    <span>Package Tools</span>
                    <i id="packageToolsChevron" class="fa-solid fa-chevron-down text-[10px] text-brand-700 transition-transform duration-200"></i>
                </button>

                <!-- Popover Dropdown Menu -->
                <div id="packageToolsMenu" style="display:none; width: 660px; max-width: calc(100vw - 2rem);" role="region" aria-labelledby="packageToolsButton" class="absolute right-0 top-full mt-2.5 z-50 bg-white rounded-2xl shadow-xl border border-gray-100 p-4 sm:p-5 text-left transform transition-all">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem;" class="gap-4">
                        <!-- Column 1: EXPORT -->
                        <div>
                            <p class="text-[11px] font-bold tracking-wider text-gray-400 uppercase mb-2.5">EXPORT</p>
                            <div class="space-y-2">
                                <a href="{{ route('admin.packages.export.packages') }}" onclick="closePackageToolsDropdown()" class="group flex items-start gap-3 p-2.5 rounded-xl hover:bg-brand-50/50 border border-transparent hover:border-brand-100 transition">
                                    <div class="w-9 h-9 rounded-lg bg-brand-50 text-brand-700 flex items-center justify-center shrink-0 group-hover:bg-brand-100 transition">
                                        <i class="fa-solid fa-file-arrow-down text-sm"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-bold text-gray-900 group-hover:text-brand-800 transition">Export Packages</p>
                                        <p class="text-xs text-gray-500 mt-0.5">Package master CSV</p>
                                    </div>
                                </a>
                                <a href="{{ route('admin.packages.export.materials') }}" onclick="closePackageToolsDropdown()" class="group flex items-start gap-3 p-2.5 rounded-xl hover:bg-brand-50/50 border border-transparent hover:border-brand-100 transition">
                                    <div class="w-9 h-9 rounded-lg bg-brand-50 text-brand-700 flex items-center justify-center shrink-0 group-hover:bg-brand-100 transition">
                                        <i class="fa-solid fa-boxes-stacked text-sm"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-bold text-gray-900 group-hover:text-brand-800 transition">Export Materials</p>
                                        <p class="text-xs text-gray-500 mt-0.5">Package BOM CSV</p>
                                    </div>
                                </a>
                            </div>
                        </div>

                        <!-- Column 2: IMPORT -->
                        <div>
                            <p class="text-[11px] font-bold tracking-wider text-gray-400 uppercase mb-2.5">IMPORT</p>
                            <div class="space-y-2">
                                <button type="button" onclick="closePackageToolsDropdown(); openImportPackagesModal();" class="w-full text-left group flex items-start gap-3 p-2.5 rounded-xl bg-brand-50/70 border border-brand-100 hover:bg-brand-100/70 transition cursor-pointer">
                                    <div class="w-9 h-9 rounded-lg bg-brand-100 text-brand-700 flex items-center justify-center shrink-0">
                                        <i class="fa-solid fa-file-arrow-up text-sm"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-bold text-brand-950">Import Packages</p>
                                        <p class="text-xs text-brand-700 mt-0.5">Upload package CSV</p>
                                    </div>
                                </button>
                                <button type="button" onclick="closePackageToolsDropdown(); openImportMaterialsModal();" class="w-full text-left group flex items-start gap-3 p-2.5 rounded-xl bg-brand-50/70 border border-brand-100 hover:bg-brand-100/70 transition cursor-pointer">
                                    <div class="w-9 h-9 rounded-lg bg-brand-100 text-brand-700 flex items-center justify-center shrink-0">
                                        <i class="fa-solid fa-layer-group text-sm"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-bold text-brand-950">Import Materials</p>
                                        <p class="text-xs text-brand-700 mt-0.5">Upload BOM CSV</p>
                                    </div>
                                </button>
                                <button type="button" onclick="closePackageToolsDropdown(); openPackageInstructionsModal();" class="w-full text-left group flex items-start gap-3 p-2.5 rounded-xl hover:bg-gray-50 border border-transparent hover:border-gray-200 transition cursor-pointer">
                                    <div class="w-9 h-9 rounded-lg bg-gray-100 text-gray-600 flex items-center justify-center shrink-0 group-hover:bg-gray-200 transition">
                                        <i class="fa-solid fa-circle-info text-sm"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-bold text-gray-900 group-hover:text-brand-800 transition">Import Instructions</p>
                                        <p class="text-xs text-gray-500 mt-0.5">View specifications</p>
                                    </div>
                                </button>
                            </div>
                        </div>

                        <!-- Column 3: TEMPLATES -->
                        <div>
                            <p class="text-[11px] font-bold tracking-wider text-gray-400 uppercase mb-2.5">TEMPLATES</p>
                            <div class="space-y-2">
                                <a href="{{ route('admin.packages.template.packages') }}" onclick="closePackageToolsDropdown()" class="group flex items-start gap-3 p-2.5 rounded-xl hover:bg-gray-50 border border-transparent hover:border-gray-200 transition">
                                    <div class="w-9 h-9 rounded-lg bg-gray-100 text-gray-600 flex items-center justify-center shrink-0 group-hover:bg-gray-200 transition">
                                        <i class="fa-regular fa-file-lines text-sm"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-bold text-gray-900 group-hover:text-brand-800 transition">Package Template</p>
                                        <p class="text-xs text-gray-500 mt-0.5">Blank packages.csv</p>
                                    </div>
                                </a>
                                <a href="{{ route('admin.packages.template.materials') }}" onclick="closePackageToolsDropdown()" class="group flex items-start gap-3 p-2.5 rounded-xl hover:bg-gray-50 border border-transparent hover:border-gray-200 transition">
                                    <div class="w-9 h-9 rounded-lg bg-gray-100 text-gray-600 flex items-center justify-center shrink-0 group-hover:bg-gray-200 transition">
                                        <i class="fa-solid fa-table-list text-sm"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-bold text-gray-900 group-hover:text-brand-800 transition">Materials Template</p>
                                        <p class="text-xs text-gray-500 mt-0.5">Blank BOM CSV</p>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Search & Filter Bar -->
    <div class="mb-6">
        <form method="GET" action="{{ route('admin.packages.index') }}" id="packageFilterForm">
            <input type="hidden" name="filter_expanded" id="packageFilterExpandedInput" value="{{ $isPackageFilterOpen ? '1' : '0' }}">
            <input type="hidden" name="view" id="packageViewModeInput" value="{{ $currentView ?? 'grid' }}">

            <div class="flex flex-wrap items-center gap-3">
                <!-- Grid / Table View Switcher -->
                <div class="inline-flex shrink-0 rounded-xl border border-gray-200 bg-gray-100 p-1" role="group" aria-label="View toggle">
                    <button
                        type="button"
                        id="viewGridBtn"
                        onclick="setViewMode('grid')"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-brand-700 px-3 py-1.5 text-xs font-bold text-white shadow-2xs transition cursor-pointer"
                        aria-pressed="true"
                        title="Grid View"
                    >
                        <i class="fa-solid fa-table-cells-large text-xs"></i>
                        <span>Grid</span>
                    </button>
                    <button
                        type="button"
                        id="viewTableBtn"
                        onclick="setViewMode('table')"
                        class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-bold text-gray-600 transition cursor-pointer hover:bg-white/60 hover:text-gray-900"
                        aria-pressed="false"
                        title="Table View"
                    >
                        <i class="fa-solid fa-table-list text-xs"></i>
                        <span>Table</span>
                    </button>
                </div>

                <!-- Seamless Single-Pill Search Bar -->
                <div class="max-[375px]:basis-full min-w-[190px] flex-1 rounded-2xl border border-gray-200/90 bg-white p-1.5 pl-3 shadow-xs flex items-center gap-2 transition-all focus-within:ring-2 focus-within:ring-brand-500 focus-within:border-brand-600 sm:pl-4 sm:gap-3">
                    <span class="text-gray-400 shrink-0 flex items-center">
                        <i class="fa-solid fa-magnifying-glass text-sm"></i>
                    </span>
                    <input
                        type="text"
                        name="search"
                        id="packageSearchInput"
                        value="{{ $currentSearch ?? request('search') }}"
                        placeholder="Search package name, category, or keyword..."
                        class="w-full bg-transparent border-0 py-2 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-0"
                    >
                    <div class="flex items-center gap-2 shrink-0 pr-1">
                        @if(!empty($currentSearch))
                            <a
                                href="{{ route('admin.packages.index', array_merge(request()->except('search'))) }}"
                                class="px-3 py-1.5 text-xs font-semibold text-gray-500 hover:text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-xl transition"
                            >
                                Clear
                            </a>
                        @endif
                        <button
                            type="submit"
                            aria-label="Search packages"
                            class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-brand-700 px-3 py-2 text-sm font-semibold text-white shadow-xs transition hover:bg-brand-800 focus:outline-none focus:ring-2 focus:ring-brand-500 cursor-pointer sm:px-5"
                        >
                            <i class="fa-solid fa-magnifying-glass text-xs"></i>
                            <span class="hidden sm:inline">Search</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Filter Panel: Category, Status, Sort, Reset -->
            <div
                id="packageFilterPanel"
                style="{{ $isPackageFilterOpen ? 'display: block;' : 'display: none;' }}"
                class="mt-3 bg-white rounded-2xl shadow-xs border border-gray-100 p-4 transition-all"
            >
                <div class="bg-gray-50/80 p-3.5 sm:p-4 rounded-xl border border-gray-100 flex flex-wrap items-center gap-3 sm:gap-4">
                    <span class="text-xs font-bold text-gray-500 uppercase tracking-wider flex items-center gap-1.5 shrink-0">
                        <i class="fa-solid fa-filter text-brand-700 text-[11px]"></i> Filters & Sort:
                    </span>

                    <!-- Category Filter Dropdown -->
                    <div class="relative min-w-[170px]">
                        <span class="absolute inset-y-0 left-3 flex items-center pointer-events-none text-gray-400">
                            <i class="fa-solid fa-shapes text-xs"></i>
                        </span>
                        <select
                            name="category"
                            id="packageCategoryFilter"
                            onchange="this.form.submit()"
                            style="padding-left: 2.35rem; padding-right: 2rem;"
                            class="w-full py-2 bg-white border border-gray-200 rounded-xl text-sm font-medium text-gray-700 focus:ring-2 focus:ring-brand-500 focus:border-brand-600 shadow-2xs appearance-none transition cursor-pointer"
                        >
                            <option value="all" {{ ($currentCategory ?? 'all') === 'all' ? 'selected' : '' }}>All Categories</option>
                            @foreach($packageCategories as $cat)
                                <option value="{{ $cat }}" {{ ($currentCategory ?? '') === $cat ? 'selected' : '' }}>{{ $cat }}</option>
                            @endforeach
                        </select>
                        <span class="absolute inset-y-0 right-2.5 flex items-center pointer-events-none text-gray-400">
                            <i class="fa-solid fa-chevron-down text-[10px]"></i>
                        </span>
                    </div>

                    <!-- Status Filter Dropdown -->
                    <div class="relative min-w-[150px]">
                        <span class="absolute inset-y-0 left-3 flex items-center pointer-events-none text-gray-400">
                            <i class="fa-solid fa-circle-dot text-xs"></i>
                        </span>
                        <select
                            name="status"
                            id="packageStatusFilter"
                            onchange="this.form.submit()"
                            style="padding-left: 2.35rem; padding-right: 2rem;"
                            class="w-full py-2 bg-white border border-gray-200 rounded-xl text-sm font-medium text-gray-700 focus:ring-2 focus:ring-brand-500 focus:border-brand-600 shadow-2xs appearance-none transition cursor-pointer"
                        >
                            <option value="active" {{ ($currentStatus ?? 'active') === 'active' ? 'selected' : '' }}>Active Packages</option>
                            <option value="all" {{ ($currentStatus ?? '') === 'all' ? 'selected' : '' }}>All Packages</option>
                            <option value="archived" {{ ($currentStatus ?? '') === 'archived' ? 'selected' : '' }}>Archived Packages</option>
                        </select>
                        <span class="absolute inset-y-0 right-2.5 flex items-center pointer-events-none text-gray-400">
                            <i class="fa-solid fa-chevron-down text-[10px]"></i>
                        </span>
                    </div>

                    <!-- Sort Dropdown -->
                    <div class="relative min-w-[170px]">
                        <span class="absolute inset-y-0 left-3 flex items-center pointer-events-none text-gray-400">
                            <i class="fa-solid fa-arrow-down-wide-short text-xs"></i>
                        </span>
                        <select
                            name="sort"
                            id="packageSortFilter"
                            onchange="this.form.submit()"
                            style="padding-left: 2.35rem; padding-right: 2rem;"
                            class="w-full py-2 bg-white border border-gray-200 rounded-xl text-sm font-medium text-gray-700 focus:ring-2 focus:ring-brand-500 focus:border-brand-600 shadow-2xs appearance-none transition cursor-pointer"
                        >
                            <option value="latest" {{ ($currentSort ?? 'latest') === 'latest' ? 'selected' : '' }}>Latest First</option>
                            <option value="oldest" {{ ($currentSort ?? '') === 'oldest' ? 'selected' : '' }}>Oldest First</option>
                            <option value="name_asc" {{ ($currentSort ?? '') === 'name_asc' ? 'selected' : '' }}>Name (A-Z)</option>
                            <option value="name_desc" {{ ($currentSort ?? '') === 'name_desc' ? 'selected' : '' }}>Name (Z-A)</option>
                            <option value="price_asc" {{ ($currentSort ?? '') === 'price_asc' ? 'selected' : '' }}>Price (Low to High)</option>
                            <option value="price_desc" {{ ($currentSort ?? '') === 'price_desc' ? 'selected' : '' }}>Price (High to Low)</option>
                        </select>
                        <span class="absolute inset-y-0 right-2.5 flex items-center pointer-events-none text-gray-400">
                            <i class="fa-solid fa-chevron-down text-[10px]"></i>
                        </span>
                    </div>

                    <!-- Reset Filters Button -->
                    <a
                        href="{{ route('admin.packages.index') }}"
                        class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-gray-600 hover:text-gray-900 bg-white hover:bg-gray-100 border border-gray-200 rounded-xl transition shadow-2xs sm:ml-auto"
                    >
                        <i class="fa-solid fa-rotate-left text-[11px] text-gray-400"></i>
                        <span>Reset Filters</span>
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- MAIN PACKAGE LISTINGS -->
    @if($packages->count() > 0)
        <!-- 1. GRID VIEW (Default Modern Catalogue Cards) -->
        <div id="packagesGridView" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5 relative">
            @foreach($packages as $package)
                @php
                    $coverImage = $package->images->first() ? asset('storage/' . $package->images->first()->image_path) : ($package->image_path ? asset('storage/' . $package->image_path) : '');
                    $imageCount = $package->images->count();
                    $inclusionsCount = is_array($package->included_items) ? count($package->included_items) : (!empty($package->included_items) ? count(explode(',', $package->included_items)) : 0);
                    $bomCount = $package->inventoryItems->count();
                @endphp
                <div class="bg-white rounded-2xl shadow-xs hover:shadow-lg border border-gray-200/80 transition-all duration-300 flex flex-col overflow-hidden group">
                    <!-- Card Top: Image & Status Badges -->
                    <div class="relative w-full bg-slate-100 overflow-hidden" style="aspect-ratio: 16/10;">
                        @if($coverImage)
                            <img src="{{ $coverImage }}" alt="{{ $package->title }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500 cursor-pointer" onclick="openLightbox('{{ $package->id }}')">
                            @if($imageCount > 1)
                                <div class="absolute bottom-2 right-2 bg-slate-900/75 backdrop-blur-md text-white text-[10px] font-bold px-2 py-0.5 rounded-md flex items-center gap-1 pointer-events-none">
                                    <i class="fa-solid fa-camera"></i> {{ $imageCount }}
                                </div>
                            @endif
                        @else
                            <div class="w-full h-full bg-gradient-to-br from-brand-50 to-brand-50 flex items-center justify-center text-brand-300 cursor-pointer" onclick="openLightbox('{{ $package->id }}')">
                                <i class="fa-solid fa-gift text-4xl text-brand-300/80"></i>
                            </div>
                        @endif

                        <!-- Status Badge Top Right -->
                        <div class="absolute top-2.5 right-2.5 pointer-events-none">
                            @if(!$package->is_archived && $package->is_active)
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-white/95 text-emerald-700 backdrop-blur-md shadow-xs border border-emerald-100">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Active
                                </span>
                            @elseif($package->is_archived)
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-white/95 text-amber-700 backdrop-blur-md shadow-xs border border-amber-100">
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> Archived
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-white/95 text-gray-600 backdrop-blur-md shadow-xs border border-gray-200">
                                    <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span> Inactive
                                </span>
                            @endif
                        </div>
                    </div>

                    <!-- Card Body -->
                    <div class="p-4 sm:p-5 flex-1 flex flex-col justify-between">
                        <div>
                            <!-- Category Badge -->
                            <div class="flex items-center justify-between gap-2 mb-1.5">
                                <span class="text-[11px] font-bold tracking-wider uppercase text-brand-700">
                                    {{ $package->category ?: 'Uncategorized' }}
                                </span>
                                <span class="text-[10px] font-mono text-gray-400 bg-gray-50 px-1.5 py-0.5 rounded border border-gray-100">
                                    {{ $package->package_code }}
                                </span>
                            </div>

                            <!-- Package Title -->
                            <h3 class="font-bold text-gray-900 text-base mb-1 line-clamp-1 group-hover:text-brand-800 transition-colors serif">
                                {{ $package->title }}
                            </h3>

                            <!-- Price -->
                            <p class="text-xl font-extrabold text-brand-700 mb-2.5">
                                ₱{{ number_format($package->price, 2) }}
                            </p>

                            <!-- Short Description -->
                            @if($package->description)
                                <p class="text-xs text-gray-500 mb-3 line-clamp-2 min-h-[2rem]">
                                    {{ $package->description }}
                                </p>
                            @else
                                <p class="text-xs text-gray-400 italic mb-3 line-clamp-2 min-h-[2rem]">
                                    No description provided.
                                </p>
                            @endif

                            <!-- Inclusions & BOM Counts Row -->
                            <div class="flex items-center gap-3 pt-2 pb-3 text-xs text-gray-600 border-t border-gray-100">
                                <span class="inline-flex items-center gap-1.5 font-medium" title="{{ $inclusionsCount }} client-facing inclusions">
                                    <i class="fa-solid fa-gift text-brand-700 text-[11px]"></i>
                                    <span>{{ $inclusionsCount }} inclusions</span>
                                </span>
                                <span class="text-gray-300">•</span>
                                <span class="inline-flex items-center gap-1.5 font-medium" title="{{ $bomCount }} physical inventory materials">
                                    <i class="fa-solid fa-boxes-stacked text-brand-700 text-[11px]"></i>
                                    <span>{{ $bomCount }} BOM items</span>
                                </span>
                            </div>
                        </div>

                        <!-- Card Action Buttons -->
                        <div class="flex items-center justify-between pt-3 border-t border-gray-100">
                            <!-- View Button -->
                            <button
                                type="button"
                                onclick='openViewModal(@json($package))'
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold text-brand-700 bg-brand-50 hover:bg-brand-100 transition cursor-pointer"
                            >
                                <i class="fa-regular fa-eye"></i>
                                <span>View</span>
                            </button>

                            <!-- Edit Button -->
                            <button
                                type="button"
                                onclick='openEditModal(@json($package))'
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold text-blue-700 bg-blue-50 hover:bg-blue-100 transition cursor-pointer"
                            >
                                <i class="fa-solid fa-pen"></i>
                                <span>Edit</span>
                                <a href="{{ route('admin.packages.edit', $package->id) }}" class="sr-only" aria-hidden="true">Edit</a>
                            </button>

                            <!-- More Actions Dropdown -->
                            <div class="relative" id="cardMoreDropdown-{{ $package->id }}">
                                <button
                                    type="button"
                                    onclick="toggleCardMoreMenu('{{ $package->id }}')"
                                    class="w-8 h-8 rounded-lg flex items-center justify-center text-gray-400 hover:text-gray-700 hover:bg-gray-100 transition cursor-pointer"
                                    aria-label="More options"
                                >
                                    <i class="fa-solid fa-ellipsis"></i>
                                </button>
                                <div
                                    id="cardMoreMenu-{{ $package->id }}"
                                    style="display:none;"
                                    class="absolute right-0 bottom-full mb-1 z-30 w-44 bg-white rounded-xl shadow-lg border border-gray-100 py-1.5 text-xs text-left"
                                >
                                    @if($package->is_archived)
                                        <form action="{{ route('admin.packages.restore', $package) }}" method="POST" id="restore-form-{{ $package->id }}">
                                            @csrf
                                            <button
                                                type="button"
                                                onclick="openConfirmModal('restore-form-{{ $package->id }}', 'Restore Package?', 'This package will be restored and made available for booking according to its active status.', 'Restore Package', 'restore', this)"
                                                class="w-full text-left px-3.5 py-2 text-emerald-700 hover:bg-emerald-50 flex items-center gap-2 cursor-pointer"
                                            >
                                                <i class="fa-solid fa-rotate-left text-xs"></i>
                                                <span>Restore Package</span>
                                            </button>
                                        </form>
                                    @else
                                        <form action="{{ route('admin.packages.archive', $package) }}" method="POST" id="archive-form-{{ $package->id }}">
                                            @csrf
                                            <button
                                                type="button"
                                                onclick="openConfirmModal('archive-form-{{ $package->id }}', 'Archive Package?', 'Archived packages are hidden from the public catalogue and cannot be chosen for new bookings.', 'Archive Package', 'archive', this)"
                                                class="w-full text-left px-3.5 py-2 text-amber-700 hover:bg-amber-50 flex items-center gap-2 cursor-pointer"
                                            >
                                                <i class="fa-solid fa-box-archive text-xs"></i>
                                                <span>Archive Package</span>
                                            </button>
                                        </form>
                                    @endif

                                    <!-- Delete Action (Controlled Permanent Deletion) -->
                                    <form action="{{ route('admin.packages.destroy', $package) }}" method="POST" id="delete-form-{{ $package->id }}">
                                        @csrf
                                        @method('DELETE')
                                        <button
                                            type="button"
                                            onclick="openConfirmModal('delete-form-{{ $package->id }}', 'Delete Package?', 'Permanent deletion is only allowed if this package has no historical booking references. Otherwise, please archive it instead.', 'Delete Permanently', 'delete', this)"
                                            class="w-full text-left px-3.5 py-2 text-red-600 hover:bg-red-50 flex items-center gap-2 border-t border-gray-50 cursor-pointer"
                                        >
                                            <i class="fa-regular fa-trash-can text-xs"></i>
                                            <span>Delete Package</span>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- 2. TABLE VIEW (Responsive Data Table) -->
        <div id="packagesTableView" style="display:none;" class="bg-white rounded-2xl shadow-xs border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="rf-table--stack w-full text-left text-sm border-collapse">
                    <thead class="bg-gray-50/80 text-gray-600 font-semibold text-xs border-b border-gray-200 uppercase tracking-wider">
                        <tr>
                            <th class="px-5 py-3.5">Package</th>
                            <th class="px-4 py-3.5">Category</th>
                            <th class="px-4 py-3.5">Price</th>
                            <th class="px-4 py-3.5">Inclusions</th>
                            <th class="px-4 py-3.5">BOM Items</th>
                            <th class="px-4 py-3.5">Status</th>
                            <th class="px-4 py-3.5">Updated</th>
                            <th class="px-5 py-3.5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($packages as $package)
                            @php
                                $thumbImage = $package->images->first() ? asset('storage/' . $package->images->first()->image_path) : ($package->image_path ? asset('storage/' . $package->image_path) : '');
                                $inclusionsCount = is_array($package->included_items) ? count($package->included_items) : (!empty($package->included_items) ? count(explode(',', $package->included_items)) : 0);
                                $bomCount = $package->inventoryItems->count();
                            @endphp
                            <tr class="hover:bg-brand-50/30 transition-colors group">
                                <td class="px-5 py-3.5" data-label="Package">
                                    <div class="flex items-center gap-3">
                                        <div class="w-11 h-11 rounded-xl bg-gray-100 overflow-hidden shrink-0 border border-gray-200 flex items-center justify-center">
                                            @if($thumbImage)
                                                <img src="{{ $thumbImage }}" alt="{{ $package->title }}" class="w-full h-full object-cover">
                                            @else
                                                <i class="fa-solid fa-gift text-gray-400 text-sm"></i>
                                            @endif
                                        </div>
                                        <div class="min-w-0">
                                            <p class="font-bold text-gray-900 group-hover:text-brand-800 transition truncate max-w-xs">{{ $package->title }}</p>
                                            <p class="font-mono text-[11px] text-gray-400 mt-0.5">{{ $package->package_code }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3.5 whitespace-nowrap" data-label="Category">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-brand-50 text-brand-700 border border-brand-100">
                                        {{ $package->category ?: 'Uncategorized' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3.5 whitespace-nowrap" data-label="Price">
                                    <span class="font-extrabold text-brand-700">₱{{ number_format($package->price, 2) }}</span>
                                </td>
                                <td class="px-4 py-3.5 whitespace-nowrap text-xs text-gray-600" data-label="Inclusions">
                                    <span class="inline-flex items-center gap-1">
                                        <i class="fa-solid fa-gift text-brand-400"></i>
                                        <span>{{ $inclusionsCount }} items</span>
                                    </span>
                                </td>
                                <td class="px-4 py-3.5 whitespace-nowrap text-xs text-gray-600" data-label="BOM Items">
                                    <span class="inline-flex items-center gap-1">
                                        <i class="fa-solid fa-boxes-stacked text-brand-400"></i>
                                        <span>{{ $bomCount }} items</span>
                                    </span>
                                </td>
                                <td class="px-4 py-3.5 whitespace-nowrap" data-label="Status">
                                    @if(!$package->is_archived && $package->is_active)
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Active
                                        </span>
                                    @elseif($package->is_archived)
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> Archived
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-600 border border-gray-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span> Inactive
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5 whitespace-nowrap text-xs text-gray-500" data-label="Updated">
                                    {{ $package->updated_at?->format('M d, Y') }}
                                </td>
                                <td class="px-5 py-3.5 whitespace-nowrap text-right" data-label="Actions">
                                    <div class="flex items-center justify-end gap-2">
                                        <button
                                            type="button"
                                            onclick='openViewModal(@json($package))'
                                            class="w-8 h-8 rounded-lg flex items-center justify-center text-brand-700 hover:bg-brand-50 transition cursor-pointer"
                                            title="View Package"
                                        >
                                            <i class="fa-regular fa-eye"></i>
                                        </button>
                                        <button
                                            type="button"
                                            onclick='openEditModal(@json($package))'
                                            class="w-8 h-8 rounded-lg flex items-center justify-center text-blue-600 hover:bg-blue-50 transition cursor-pointer"
                                            title="Edit Package"
                                        >
                                            <i class="fa-solid fa-pen"></i>
                                        </button>
                                        @if($package->is_archived)
                                            <form action="{{ route('admin.packages.restore', $package) }}" method="POST" id="tbl-restore-form-{{ $package->id }}" class="inline-block m-0">
                                                @csrf
                                                <button
                                                    type="button"
                                                    onclick="openConfirmModal('tbl-restore-form-{{ $package->id }}', 'Restore Package?', 'Restore this package to the catalogue.', 'Restore Package', 'restore', this)"
                                                    class="w-8 h-8 rounded-lg flex items-center justify-center text-emerald-600 hover:bg-emerald-50 transition cursor-pointer"
                                                    title="Restore Package"
                                                >
                                                    <i class="fa-solid fa-rotate-left"></i>
                                                </button>
                                            </form>
                                        @else
                                            <form action="{{ route('admin.packages.archive', $package) }}" method="POST" id="tbl-archive-form-{{ $package->id }}" class="inline-block m-0">
                                                @csrf
                                                <button
                                                    type="button"
                                                    onclick="openConfirmModal('tbl-archive-form-{{ $package->id }}', 'Archive Package?', 'Archived packages are hidden from booking selection.', 'Archive Package', 'archive', this)"
                                                    class="w-8 h-8 rounded-lg flex items-center justify-center text-amber-600 hover:bg-amber-50 transition cursor-pointer"
                                                    title="Archive Package"
                                                >
                                                    <i class="fa-solid fa-box-archive"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <!-- Empty State -->
        <div class="text-center py-16 bg-white rounded-2xl shadow-xs border border-gray-100">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-brand-50 text-brand-700 mb-4">
                <i class="fa-solid fa-gift text-2xl"></i>
            </div>
            @if(!empty($currentSearch) || (($currentCategory ?? 'all') !== 'all') || (($currentStatus ?? 'active') !== 'active'))
                <h3 class="text-lg font-bold text-gray-900">No matching packages found</h3>
                <p class="mt-1 text-sm text-gray-500 mb-6">Try adjusting your keyword search, category filter, or status filter.</p>
                <a href="{{ route('admin.packages.index') }}" class="inline-flex items-center px-4 py-2 border border-brand-200 text-brand-700 bg-brand-50 hover:bg-brand-100 rounded-xl text-sm font-semibold transition">
                    Clear Filters
                </a>
            @else
                <h3 class="text-lg font-bold text-gray-900">No packages in catalogue</h3>
                <p class="mt-1 text-sm text-gray-500 mb-6">Get started by creating your first package bundle.</p>
                <button type="button" onclick="openAddPackageModal()" class="inline-flex items-center px-4 py-2 bg-brand-700 hover:bg-brand-800 text-white rounded-xl text-sm font-semibold transition cursor-pointer">
                    <i class="fa-solid fa-plus mr-2"></i> Add Package
                </button>
            @endif
        </div>
    @endif

    <!-- ================================================================= -->
    <!-- 1. VIEW PACKAGE MODAL                                             -->
    <!-- ================================================================= -->
    <div id="viewModal" class="fixed inset-0 z-[100] hidden flex items-center justify-center bg-slate-900/80 backdrop-blur-xs p-4 sm:p-6" role="dialog" aria-modal="true" aria-labelledby="viewModalTitle">
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-3xl flex flex-col relative overflow-hidden my-auto max-h-[90vh]">
            <button onclick="closeModal('viewModal')" class="absolute top-4 right-4 z-20 w-10 h-10 rounded-full flex items-center justify-center bg-white text-gray-700 hover:bg-gray-100 shadow-md transition border border-gray-200 cursor-pointer" aria-label="Close modal">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>

            <div class="px-6 sm:px-8 py-5 border-b border-gray-100 shrink-0 pr-16 bg-white">
                <div class="flex items-center gap-2">
                    <span class="text-xs font-bold uppercase tracking-wider text-brand-700" id="view_category_top">Package</span>
                </div>
                <h3 id="viewModalTitle" class="text-2xl font-bold text-gray-900 serif">View Package</h3>
            </div>

            <div class="px-6 sm:px-8 py-6 space-y-6 overflow-y-auto flex-1 bg-gray-50/50">
                <!-- Package Summary Card -->
                <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-xs">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <span class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Package Name</span>
                            <span id="view_title" class="block text-base font-bold text-gray-900"></span>
                        </div>
                        <div>
                            <span class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Package Code</span>
                            <span id="view_package_code" class="inline-block text-xs font-mono text-gray-700 bg-gray-100 px-2 py-0.5 rounded border border-gray-200"></span>
                        </div>
                        <div>
                            <span class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Category</span>
                            <span id="view_category" class="block text-sm text-gray-800"></span>
                        </div>
                        <div>
                            <span class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Price</span>
                            <span id="view_price" class="block text-base font-extrabold text-brand-700"></span>
                        </div>
                        <div class="sm:col-span-2">
                            <span class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Catalogue Status</span>
                            <span id="view_status_badge" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold"></span>
                        </div>
                    </div>
                </div>

                <!-- Description -->
                <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-xs">
                    <h4 class="text-xs font-bold tracking-wider uppercase text-gray-400 mb-2 border-b border-gray-100 pb-2">Description</h4>
                    <p id="view_description" class="text-sm text-gray-700 whitespace-pre-wrap leading-relaxed"></p>
                </div>

                <!-- Client-Facing Inclusions -->
                <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-xs">
                    <div class="flex items-center justify-between border-b border-gray-100 pb-2 mb-3">
                        <h4 class="text-xs font-bold tracking-wider uppercase text-gray-400">Client-Facing Inclusions</h4>
                        <span id="view_inclusions_count" class="text-xs font-semibold text-brand-700"></span>
                    </div>
                    <div id="view_inclusions_container" class="flex flex-wrap gap-2">
                        <!-- Populated by JS -->
                    </div>
                </div>

                <!-- Master Inventory BOM Requirements -->
                <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-xs">
                    <div class="flex items-center justify-between border-b border-gray-100 pb-2 mb-3">
                        <h4 class="text-xs font-bold tracking-wider uppercase text-gray-400">Inventory Requirements (BOM)</h4>
                        <span id="view_bom_count" class="text-xs font-semibold text-brand-700"></span>
                    </div>
                    <div id="view_inventory_container" class="space-y-2">
                        <!-- Populated by JS -->
                    </div>
                </div>
            </div>

            <div class="px-6 sm:px-8 py-4 border-t border-gray-100 flex items-center justify-between bg-white shrink-0">
                <button type="button" id="view_edit_btn" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl transition inline-flex items-center gap-1.5 cursor-pointer">
                    <i class="fa-solid fa-pen"></i>
                    <span>Edit Package</span>
                </button>
                <button type="button" onclick="closeModal('viewModal')" class="px-5 py-2 border border-gray-300 text-gray-700 font-semibold text-xs rounded-xl hover:bg-gray-50 transition shadow-2xs cursor-pointer">
                    Close
                </button>
            </div>
        </div>
    </div>

    <!-- ================================================================= -->
    <!-- 2. ADD PACKAGE MODAL                                              -->
    <!-- ================================================================= -->
    <div id="addPackageModal" class="fixed inset-0 z-[100] hidden flex items-center justify-center bg-slate-900/80 backdrop-blur-xs p-4 sm:p-6" role="dialog" aria-modal="true" aria-labelledby="addPackageModalTitle">
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-4xl flex flex-col relative overflow-hidden my-auto max-h-[92vh]">
            <button onclick="closeModal('addPackageModal')" class="absolute top-4 right-4 z-20 w-10 h-10 rounded-full flex items-center justify-center bg-white text-gray-700 hover:bg-gray-100 shadow-md transition border border-gray-200 cursor-pointer" aria-label="Close modal">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>

            <div class="px-6 sm:px-8 py-5 border-b border-gray-100 shrink-0 bg-white pr-16">
                <h3 id="addPackageModalTitle" class="text-2xl font-bold text-gray-900 serif">Add New Package</h3>
                <p class="text-xs text-gray-500 mt-0.5">Define package pricing, client-facing highlights, and operational Bill of Materials (BOM).</p>
            </div>

            <form action="{{ route('admin.packages.store') }}" method="POST" enctype="multipart/form-data" id="addPackageForm" class="flex flex-col flex-1 overflow-hidden" onkeydown="if(event.key === 'Enter' && event.target.tagName !== 'TEXTAREA' && event.target.id !== 'add_inclusion_input') { event.preventDefault(); }">
                @csrf
                <div class="px-6 sm:px-8 py-6 space-y-6 overflow-y-auto flex-1 bg-gray-50/50">
                    <!-- SECTION A: PACKAGE INFORMATION -->
                    <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-xs space-y-4">
                        <h4 class="text-xs font-bold tracking-wider uppercase text-brand-700 flex items-center gap-2 border-b border-gray-100 pb-2">
                            <i class="fa-solid fa-info-circle"></i> A. Package Information
                        </h4>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">
                                    Package Name <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="title" required placeholder="e.g. Elegant Bloom Package" class="w-full px-3.5 py-2.5 bg-gray-50/50 border border-gray-200 rounded-xl text-sm focus:bg-white focus:ring-2 focus:ring-brand-500 focus:border-brand-600 transition shadow-2xs">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">
                                    Category <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="category" required list="categories_list" placeholder="e.g. Wedding, Debut" maxlength="50" class="w-full px-3.5 py-2.5 bg-gray-50/50 border border-gray-200 rounded-xl text-sm focus:bg-white focus:ring-2 focus:ring-brand-500 focus:border-brand-600 transition shadow-2xs">
                                <datalist id="categories_list">
                                    @foreach($packageCategories as $c)
                                        <option value="{{ $c }}"></option>
                                    @endforeach
                                </datalist>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">
                                    Package Price (₱) <span class="text-red-500">*</span>
                                </label>
                                <input type="number" step="0.01" min="0" name="price" required placeholder="0.00" class="w-full px-3.5 py-2.5 bg-gray-50/50 border border-gray-200 rounded-xl text-sm focus:bg-white focus:ring-2 focus:ring-brand-500 focus:border-brand-600 transition shadow-2xs">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Description</label>
                                <textarea name="description" rows="2" placeholder="Brief marketing and styling description for this package..." class="w-full px-3.5 py-2.5 bg-gray-50/50 border border-gray-200 rounded-xl text-sm focus:bg-white focus:ring-2 focus:ring-brand-500 focus:border-brand-600 transition shadow-2xs resize-y"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION B: CLIENT-FACING INCLUSIONS -->
                    <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-xs space-y-3">
                        <div class="border-b border-gray-100 pb-2">
                            <h4 class="text-xs font-bold tracking-wider uppercase text-brand-700 flex items-center gap-2">
                                <i class="fa-solid fa-gift"></i> B. Client-Facing Inclusions
                            </h4>
                            <p class="text-xs text-gray-500 mt-0.5">Describe what the client receives as part of this bundle (e.g., Bridal Bouquet, 6 Boutonnieres, Stage Setup).</p>
                        </div>

                        <div class="flex items-center gap-2">
                            <input type="text" id="add_inclusion_input" placeholder="Type inclusion and press Enter or click Add..." class="flex-1 px-3.5 py-2 bg-gray-50/50 border border-gray-200 rounded-xl text-sm focus:bg-white focus:ring-2 focus:ring-brand-500 focus:border-brand-600 transition shadow-2xs" onkeydown="if(event.key === 'Enter'){ event.preventDefault(); addInclusionChip('add'); }">
                            <button type="button" onclick="addInclusionChip('add')" class="px-4 py-2 bg-brand-700 hover:bg-brand-800 text-white text-xs font-bold rounded-xl transition cursor-pointer">
                                <i class="fa-solid fa-plus text-xs"></i> Add
                            </button>
                        </div>

                        <!-- Inclusions Chips Container -->
                        <div id="add_inclusions_chips" class="flex flex-wrap gap-2 pt-2 min-h-[40px] p-2 bg-gray-50/50 rounded-xl border border-gray-100">
                            <!-- Populated dynamically by JS -->
                        </div>
                        <input type="hidden" name="included_items" id="add_included_items_serialized" value="">
                    </div>

                    <!-- SECTION C: INVENTORY REQUIREMENTS (BOM) -->
                    <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-xs space-y-4">
                        <div class="border-b border-gray-100 pb-2">
                            <div class="flex items-center justify-between">
                                <h4 class="text-xs font-bold tracking-wider uppercase text-brand-700 flex items-center gap-2">
                                    <i class="fa-solid fa-boxes-stacked"></i> C. Inventory Requirements (Bill of Materials)
                                </h4>
                                <span class="text-xs font-bold text-brand-700" id="add_bom_count_summary">0 items selected</span>
                            </div>
                            <p class="text-xs text-gray-500 mt-0.5">Physical inventory materials required to prepare this package. Package definition is a template only and does not deduct actual inventory stock.</p>
                        </div>

                        <!-- Search Available Inventory Items -->
                        <div class="flex flex-col sm:flex-row gap-2.5">
                            <div class="relative flex-1">
                                <span class="absolute inset-y-0 left-3 flex items-center pointer-events-none text-gray-400">
                                    <i class="fa-solid fa-magnifying-glass text-xs"></i>
                                </span>
                                <input type="text" id="add_bom_search_input" oninput="filterInventoryCatalog('add')" placeholder="Search inventory materials by name or item code..." class="w-full pl-9 pr-3.5 py-2 bg-gray-50/50 border border-gray-200 rounded-xl text-sm focus:bg-white focus:ring-2 focus:ring-brand-500 focus:border-brand-600 transition shadow-2xs">
                            </div>
                            <div class="sm:w-1/3">
                                <select id="add_bom_category_filter" onchange="filterInventoryCatalog('add')" class="w-full px-3 py-2 bg-gray-50/50 border border-gray-200 rounded-xl text-sm focus:bg-white focus:ring-2 focus:ring-brand-500 focus:border-brand-600 transition shadow-2xs">
                                    <option value="">All Inventory Categories</option>
                                    @foreach($inventoryCategories as $icat)
                                        <option value="{{ $icat }}">{{ $icat }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <!-- Available Catalog Items List (Click + Add to move into BOM) -->
                        <div class="border border-gray-200 rounded-xl p-2 bg-gray-50 max-h-48 overflow-y-auto space-y-1.5" id="add_bom_catalog_list">
                            @foreach($inventoryItems as $item)
                                <div
                                    class="flex items-center justify-between p-2 rounded-lg bg-white border border-gray-100 hover:border-brand-200 hover:bg-brand-50/20 transition text-xs bom-catalog-row"
                                    data-id="{{ $item->id }}"
                                    data-name="{{ $item->name }}"
                                    data-code="{{ $item->item_code ?? 'N/A' }}"
                                    data-category="{{ $item->category }}"
                                    data-unit="{{ $item->unit }}"
                                    data-stock="{{ (float)$item->current_stock }}"
                                >
                                    <div class="min-w-0 pr-2">
                                        <div class="flex items-center gap-1.5">
                                            <span class="font-bold text-gray-800 truncate">{{ $item->name }}</span>
                                            <span class="font-mono text-[10px] bg-gray-100 text-gray-500 px-1 rounded">{{ $item->item_code ?? 'N/A' }}</span>
                                        </div>
                                        <p class="text-[11px] text-gray-500 mt-0.5">
                                            {{ $item->category }} • In Stock: <strong class="text-gray-700">{{ (float)$item->current_stock }}</strong> {{ $item->unit }}
                                        </p>
                                    </div>
                                    <button type="button" onclick="addMaterialToBom('add', {{ $item->id }}, '{{ addslashes($item->name) }}', '{{ $item->item_code ?? 'N/A' }}', '{{ $item->unit }}', {{ (float)$item->current_stock }})" class="shrink-0 px-2.5 py-1 bg-brand-50 hover:bg-brand-800 text-brand-700 hover:text-white rounded-lg font-bold text-xs transition cursor-pointer">
                                        <i class="fa-solid fa-plus text-[10px]"></i> Add
                                    </button>
                                </div>
                            @endforeach
                        </div>

                        <!-- Selected BOM Items Table -->
                        <div class="border border-gray-200 rounded-xl overflow-hidden bg-white shadow-2xs">
                            <div class="px-3.5 py-2 bg-gray-50 border-b border-gray-200 text-xs font-bold text-gray-700 flex items-center justify-between">
                                <span>Selected BOM Materials</span>
                                <span class="text-[11px] text-gray-500 font-normal">Only selected materials appear in the package BOM</span>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full text-left text-xs">
                                    <thead class="bg-gray-50/50 text-gray-500 border-b border-gray-200">
                                        <tr>
                                            <th class="px-3 py-2">Material</th>
                                            <th class="px-2 py-2">Item Code</th>
                                            <th class="px-2 py-2">Available Stock</th>
                                            <th class="px-3 py-2 w-36">Required Qty</th>
                                            <th class="px-2 py-2 text-right">Remove</th>
                                        </tr>
                                    </thead>
                                    <tbody id="add_selected_bom_tbody" class="divide-y divide-gray-100">
                                        <tr id="add_empty_bom_row">
                                            <td colspan="5" class="px-4 py-5 text-center text-gray-400 italic">
                                                No inventory materials selected yet. Search above and click "+ Add" to include materials in this package.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION D: PACKAGE IMAGE & STATUS -->
                    <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-xs space-y-4">
                        <h4 class="text-xs font-bold tracking-wider uppercase text-brand-700 flex items-center gap-2 border-b border-gray-100 pb-2">
                            <i class="fa-solid fa-image"></i> D. Package Image & Status
                        </h4>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Upload Package Image</label>
                                <input type="file" name="images[]" id="add_image_input" accept="image/png,image/jpeg,image/webp" onchange="previewImageFile(this, 'add_image_preview')" class="w-full text-xs text-gray-500 file:mr-3 file:py-2 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100 border border-gray-200 rounded-xl p-1 bg-gray-50">
                                <p class="text-[11px] text-gray-400 mt-1">Single image (PNG, JPG, WEBP up to 2MB).</p>
                                <div id="add_image_preview" class="mt-2 w-24 h-16 rounded-lg bg-gray-100 border border-gray-200 hidden overflow-hidden">
                                    <img src="" alt="Preview" class="w-full h-full object-cover">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Package Catalogue Status</label>
                                <div class="mt-2 flex items-center gap-3">
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" name="is_active" value="1" class="sr-only peer" checked>
                                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-brand-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
                                        <span class="ml-3 text-xs font-bold text-gray-700">Active (Visible for Public Booking)</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="px-6 sm:px-8 py-4 border-t border-gray-100 flex items-center justify-end gap-3 bg-white shrink-0">
                    <button type="button" onclick="closeModal('addPackageModal')" class="px-5 py-2.5 border border-gray-300 text-gray-700 text-xs font-bold rounded-xl hover:bg-gray-50 transition cursor-pointer shadow-2xs">
                        Cancel
                    </button>
                    <button type="submit" class="px-5 py-2.5 bg-brand-700 hover:bg-brand-800 text-white text-xs font-bold rounded-xl transition cursor-pointer shadow-xs">
                        Create Package
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ================================================================= -->
    <!-- 3. EDIT PACKAGE MODAL                                             -->
    <!-- ================================================================= -->
    <div id="editPackageModal" class="fixed inset-0 z-[100] hidden flex items-center justify-center bg-slate-900/80 backdrop-blur-xs p-4 sm:p-6" role="dialog" aria-modal="true" aria-labelledby="editPackageModalTitle">
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-4xl flex flex-col relative overflow-hidden my-auto max-h-[92vh]">
            <button onclick="closeModal('editPackageModal')" class="absolute top-4 right-4 z-20 w-10 h-10 rounded-full flex items-center justify-center bg-white text-gray-700 hover:bg-gray-100 shadow-md transition border border-gray-200 cursor-pointer" aria-label="Close modal">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>

            <div class="px-6 sm:px-8 py-5 border-b border-gray-100 shrink-0 bg-white pr-16">
                <h3 id="editPackageModalTitle" class="text-2xl font-bold text-gray-900 serif">Edit Package</h3>
                <p class="text-xs text-gray-500 mt-0.5">Update package details, inclusions, and master inventory BOM mappings.</p>
            </div>

            <form action="" method="POST" enctype="multipart/form-data" id="editPackageForm" class="flex flex-col flex-1 overflow-hidden" onkeydown="if(event.key === 'Enter' && event.target.tagName !== 'TEXTAREA' && event.target.id !== 'edit_inclusion_input') { event.preventDefault(); }">
                @csrf
                @method('PUT')
                <div class="px-6 sm:px-8 py-6 space-y-6 overflow-y-auto flex-1 bg-gray-50/50">
                    <!-- SECTION A: PACKAGE INFORMATION -->
                    <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-xs space-y-4">
                        <h4 class="text-xs font-bold tracking-wider uppercase text-brand-700 flex items-center gap-2 border-b border-gray-100 pb-2">
                            <i class="fa-solid fa-info-circle"></i> A. Package Information
                        </h4>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">
                                    Package Name <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="title" id="edit_title" required class="w-full px-3.5 py-2.5 bg-gray-50/50 border border-gray-200 rounded-xl text-sm focus:bg-white focus:ring-2 focus:ring-brand-500 focus:border-brand-600 transition shadow-2xs">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Package Code</label>
                                <input type="text" id="edit_package_code" readonly disabled class="w-full px-3.5 py-2.5 bg-gray-100 border border-gray-200 rounded-xl text-sm font-mono text-gray-500 cursor-not-allowed shadow-2xs">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">
                                    Category <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="category" id="edit_category" required list="categories_list" maxlength="50" class="w-full px-3.5 py-2.5 bg-gray-50/50 border border-gray-200 rounded-xl text-sm focus:bg-white focus:ring-2 focus:ring-brand-500 focus:border-brand-600 transition shadow-2xs">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">
                                    Package Price (₱) <span class="text-red-500">*</span>
                                </label>
                                <input type="number" step="0.01" min="0" name="price" id="edit_price" required class="w-full px-3.5 py-2.5 bg-gray-50/50 border border-gray-200 rounded-xl text-sm focus:bg-white focus:ring-2 focus:ring-brand-500 focus:border-brand-600 transition shadow-2xs">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Description</label>
                                <textarea name="description" id="edit_description" rows="2" class="w-full px-3.5 py-2.5 bg-gray-50/50 border border-gray-200 rounded-xl text-sm focus:bg-white focus:ring-2 focus:ring-brand-500 focus:border-brand-600 transition shadow-2xs resize-y"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION B: CLIENT-FACING INCLUSIONS -->
                    <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-xs space-y-3">
                        <div class="border-b border-gray-100 pb-2">
                            <h4 class="text-xs font-bold tracking-wider uppercase text-brand-700 flex items-center gap-2">
                                <i class="fa-solid fa-gift"></i> B. Client-Facing Inclusions
                            </h4>
                            <p class="text-xs text-gray-500 mt-0.5">Describe what the client receives as part of this package.</p>
                        </div>

                        <div class="flex items-center gap-2">
                            <input type="text" id="edit_inclusion_input" placeholder="Type inclusion and press Enter or click Add..." class="flex-1 px-3.5 py-2 bg-gray-50/50 border border-gray-200 rounded-xl text-sm focus:bg-white focus:ring-2 focus:ring-brand-500 focus:border-brand-600 transition shadow-2xs" onkeydown="if(event.key === 'Enter'){ event.preventDefault(); addInclusionChip('edit'); }">
                            <button type="button" onclick="addInclusionChip('edit')" class="px-4 py-2 bg-brand-700 hover:bg-brand-800 text-white text-xs font-bold rounded-xl transition cursor-pointer">
                                <i class="fa-solid fa-plus text-xs"></i> Add
                            </button>
                        </div>

                        <!-- Inclusions Chips Container -->
                        <div id="edit_inclusions_chips" class="flex flex-wrap gap-2 pt-2 min-h-[40px] p-2 bg-gray-50/50 rounded-xl border border-gray-100">
                            <!-- Populated dynamically by JS -->
                        </div>
                        <input type="hidden" name="included_items" id="edit_included_items_serialized" value="">
                    </div>

                    <!-- SECTION C: INVENTORY REQUIREMENTS (BOM) -->
                    <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-xs space-y-4">
                        <div class="border-b border-gray-100 pb-2">
                            <div class="flex items-center justify-between">
                                <h4 class="text-xs font-bold tracking-wider uppercase text-brand-700 flex items-center gap-2">
                                    <i class="fa-solid fa-boxes-stacked"></i> C. Inventory Requirements (Bill of Materials)
                                </h4>
                                <span class="text-xs font-bold text-brand-700" id="edit_bom_count_summary">0 items selected</span>
                            </div>
                            <p class="text-xs text-gray-500 mt-0.5">Physical inventory materials required to prepare this package.</p>
                        </div>

                        <!-- Search Available Inventory Items -->
                        <div class="flex flex-col sm:flex-row gap-2.5">
                            <div class="relative flex-1">
                                <span class="absolute inset-y-0 left-3 flex items-center pointer-events-none text-gray-400">
                                    <i class="fa-solid fa-magnifying-glass text-xs"></i>
                                </span>
                                <input type="text" id="edit_bom_search_input" oninput="filterInventoryCatalog('edit')" placeholder="Search inventory materials by name or item code..." class="w-full pl-9 pr-3.5 py-2 bg-gray-50/50 border border-gray-200 rounded-xl text-sm focus:bg-white focus:ring-2 focus:ring-brand-500 focus:border-brand-600 transition shadow-2xs">
                            </div>
                            <div class="sm:w-1/3">
                                <select id="edit_bom_category_filter" onchange="filterInventoryCatalog('edit')" class="w-full px-3 py-2 bg-gray-50/50 border border-gray-200 rounded-xl text-sm focus:bg-white focus:ring-2 focus:ring-brand-500 focus:border-brand-600 transition shadow-2xs">
                                    <option value="">All Inventory Categories</option>
                                    @foreach($inventoryCategories as $icat)
                                        <option value="{{ $icat }}">{{ $icat }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <!-- Available Catalog Items List -->
                        <div class="border border-gray-200 rounded-xl p-2 bg-gray-50 max-h-48 overflow-y-auto space-y-1.5" id="edit_bom_catalog_list">
                            @foreach($inventoryItems as $item)
                                <div
                                    class="flex items-center justify-between p-2 rounded-lg bg-white border border-gray-100 hover:border-brand-200 hover:bg-brand-50/20 transition text-xs bom-catalog-row"
                                    data-id="{{ $item->id }}"
                                    data-name="{{ $item->name }}"
                                    data-code="{{ $item->item_code ?? 'N/A' }}"
                                    data-category="{{ $item->category }}"
                                    data-unit="{{ $item->unit }}"
                                    data-stock="{{ (float)$item->current_stock }}"
                                >
                                    <div class="min-w-0 pr-2">
                                        <div class="flex items-center gap-1.5">
                                            <span class="font-bold text-gray-800 truncate">{{ $item->name }}</span>
                                            <span class="font-mono text-[10px] bg-gray-100 text-gray-500 px-1 rounded">{{ $item->item_code ?? 'N/A' }}</span>
                                        </div>
                                        <p class="text-[11px] text-gray-500 mt-0.5">
                                            {{ $item->category }} • In Stock: <strong class="text-gray-700">{{ (float)$item->current_stock }}</strong> {{ $item->unit }}
                                        </p>
                                    </div>
                                    <button type="button" onclick="addMaterialToBom('edit', {{ $item->id }}, '{{ addslashes($item->name) }}', '{{ $item->item_code ?? 'N/A' }}', '{{ $item->unit }}', {{ (float)$item->current_stock }})" class="shrink-0 px-2.5 py-1 bg-brand-50 hover:bg-brand-800 text-brand-700 hover:text-white rounded-lg font-bold text-xs transition cursor-pointer">
                                        <i class="fa-solid fa-plus text-[10px]"></i> Add
                                    </button>
                                </div>
                            @endforeach
                        </div>

                        <!-- Selected BOM Items Table -->
                        <div class="border border-gray-200 rounded-xl overflow-hidden bg-white shadow-2xs">
                            <div class="px-3.5 py-2 bg-gray-50 border-b border-gray-200 text-xs font-bold text-gray-700 flex items-center justify-between">
                                <span>Selected BOM Materials</span>
                                <span class="text-[11px] text-gray-500 font-normal">Materials prefilled from existing package template</span>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full text-left text-xs">
                                    <thead class="bg-gray-50/50 text-gray-500 border-b border-gray-200">
                                        <tr>
                                            <th class="px-3 py-2">Material</th>
                                            <th class="px-2 py-2">Item Code</th>
                                            <th class="px-2 py-2">Available Stock</th>
                                            <th class="px-3 py-2 w-36">Required Qty</th>
                                            <th class="px-2 py-2 text-right">Remove</th>
                                        </tr>
                                    </thead>
                                    <tbody id="edit_selected_bom_tbody" class="divide-y divide-gray-100">
                                        <tr id="edit_empty_bom_row">
                                            <td colspan="5" class="px-4 py-5 text-center text-gray-400 italic">
                                                No inventory materials selected yet. Search above and click "+ Add" to include materials in this package.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION D: PACKAGE IMAGE & STATUS -->
                    <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-xs space-y-4">
                        <h4 class="text-xs font-bold tracking-wider uppercase text-brand-700 flex items-center gap-2 border-b border-gray-100 pb-2">
                            <i class="fa-solid fa-image"></i> D. Package Image & Status
                        </h4>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Replace Package Image</label>
                                <input type="file" name="images[]" id="edit_image_input" accept="image/png,image/jpeg,image/webp" onchange="previewImageFile(this, 'edit_image_preview')" class="w-full text-xs text-gray-500 file:mr-3 file:py-2 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100 border border-gray-200 rounded-xl p-1 bg-gray-50">
                                <p class="text-[11px] text-gray-400 mt-1">Upload a new image to replace or add to the current image.</p>
                                <div id="edit_image_preview" class="mt-2 w-24 h-16 rounded-lg bg-gray-100 border border-gray-200 overflow-hidden">
                                    <img src="" id="edit_current_img_tag" alt="Current Image" class="w-full h-full object-cover">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Package Catalogue Status</label>
                                <div class="mt-2 flex items-center gap-3">
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" name="is_active" id="edit_is_active" value="1" class="sr-only peer">
                                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-brand-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
                                        <span class="ml-3 text-xs font-bold text-gray-700">Active (Visible for Public Booking)</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="px-6 sm:px-8 py-4 border-t border-gray-100 flex items-center justify-end gap-3 bg-white shrink-0">
                    <button type="button" onclick="closeModal('editPackageModal')" class="px-5 py-2.5 border border-gray-300 text-gray-700 text-xs font-bold rounded-xl hover:bg-gray-50 transition cursor-pointer shadow-2xs">
                        Cancel
                    </button>
                    <button type="submit" class="px-5 py-2.5 bg-brand-700 hover:bg-brand-800 text-white text-xs font-bold rounded-xl transition cursor-pointer shadow-xs">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ================================================================= -->
    <!-- 4. CONFIRMATION MODAL (Archive, Restore, Delete)                   -->
    <!-- ================================================================= -->
    <div id="confirmActionModal" class="fixed inset-0 z-[110] hidden flex items-center justify-center bg-slate-900/80 backdrop-blur-xs p-4" role="dialog" aria-modal="true" aria-labelledby="confirmTitle">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 transform transition-all border border-gray-100">
            <div class="flex items-center gap-3 mb-3">
                <div id="confirmIconWrapper" class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0">
                    <i id="confirmIcon" class="fa-solid fa-triangle-exclamation text-base"></i>
                </div>
                <div>
                    <h3 id="confirmTitle" class="text-base font-bold text-gray-900">Confirm Action</h3>
                    <p class="text-xs text-gray-500" id="confirmSubtitle">Please review before continuing.</p>
                </div>
            </div>
            <p id="confirmMessage" class="text-xs text-gray-600 mb-5 leading-relaxed bg-gray-50 p-3 rounded-xl border border-gray-100"></p>
            <div class="flex items-center justify-end gap-2.5 pt-2">
                <button type="button" onclick="closeConfirmModal()" class="px-4 py-2 border border-gray-300 rounded-xl text-xs font-bold text-gray-700 hover:bg-gray-50 transition shadow-2xs cursor-pointer">
                    Cancel
                </button>
                <button type="button" id="confirmSubmitBtn" class="px-4 py-2 rounded-xl text-xs font-bold text-white transition shadow-xs cursor-pointer">
                    Confirm
                </button>
            </div>
        </div>
    </div>

    <!-- Lightbox, CSV Import & Instruction Modals -->
    @php
        $lightboxData = $packages->map(function($p) {
            return [
                'id' => (string) $p->id,
                'title' => $p->title,
                'tags' => [$p->category],
                'images' => $p->images->pluck('image_path')->map(fn($path) => asset('storage/' . $path))->toArray()
            ];
        })->toArray();
    @endphp

    <div id="galleryLightbox" onclick="closeLightbox()" class="fixed inset-0 z-[100] hidden flex-col items-center justify-center bg-slate-900/80 backdrop-blur-[2px] opacity-0 transition-opacity duration-300 overflow-y-auto p-4 sm:p-6" style="padding: 1.5rem; justify-content: center; align-items: center;">
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-3xl flex flex-col relative overflow-hidden my-auto mx-auto" onclick="event.stopPropagation()" style="border-radius: 20px; max-width: 768px; margin-left: auto; margin-right: auto; margin-top: auto; margin-bottom: auto; transform: translateZ(0);">
            <button onclick="closeLightbox()" class="absolute top-4 right-4 sm:top-5 sm:right-5 z-20 w-12 h-12 rounded-full flex items-center justify-center bg-white text-slate-800 hover:bg-slate-100 shadow-md transition-transform hover:scale-105 border border-slate-100" style="border-radius: 9999px; width: 44px; height: 44px;">
                <i class="fa-solid fa-xmark text-xl"></i>
            </button>

            <div class="relative w-full bg-slate-100 flex justify-center items-center" style="width: 100%; height: 470px;">
                <img id="lightboxMainImg" src="" class="w-full h-full object-contain" alt="Package Image" style="width: 100%; height: 100%; object-fit: contain;">
                
                <button onclick="prevImage(event)" id="lightboxPrevBtn" class="absolute left-3 sm:left-5 top-1/2 -translate-y-1/2 w-12 h-12 rounded-full flex items-center justify-center bg-white text-slate-800 hover:bg-slate-50 shadow-md transition-transform hover:scale-105 z-10 hidden border border-slate-100" style="border-radius: 9999px; width: 44px; height: 44px; transform: translateY(-50%); top: 50%;">
                    <i class="fa-solid fa-chevron-left text-base"></i>
                </button>
                <button onclick="nextImage(event)" id="lightboxNextBtn" class="absolute right-3 sm:right-5 top-1/2 -translate-y-1/2 w-12 h-12 rounded-full flex items-center justify-center bg-white text-slate-800 hover:bg-slate-50 shadow-md transition-transform hover:scale-105 z-10 hidden border border-slate-100" style="border-radius: 9999px; width: 44px; height: 44px; transform: translateY(-50%); top: 50%;">
                    <i class="fa-solid fa-chevron-right text-base"></i>
                </button>
            </div>

            <div class="flex flex-col p-6 sm:px-8 sm:pb-8 sm:pt-6 w-full" style="padding: 1.5rem 2rem 2rem 2rem; width: 100%;">
                <div class="text-center text-slate-800 font-medium text-sm sm:text-base mb-4">
                    <span id="lightboxCounter"></span>
                </div>
                <div class="flex justify-center w-full mb-6 sm:mb-8" style="justify-content: center; width: 100%;">
                    <div id="lightboxThumbnails" class="flex gap-2 sm:gap-3 overflow-x-auto snap-x max-w-full px-1 scrollbar-hide" style="scrollbar-width: none; gap: 0.75rem;">
                    </div>
                </div>
                <div class="w-full text-left" style="width: 100%; text-align: left;">
                    <h3 id="lightboxTitle" class="text-2xl sm:text-3xl font-bold text-slate-900 serif mb-2"></h3>
                    <div class="flex flex-wrap items-center gap-3 sm:gap-4 text-slate-600 text-sm sm:text-[15px] font-medium" style="display: flex; gap: 1rem;">
                        <div class="flex items-center gap-1.5" style="display: flex; gap: 0.375rem;">
                            <i class="fa-solid fa-tag text-slate-400"></i>
                            <span id="lightboxCategory"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Package Master CSV Import Modal -->
    <div id="importPackagesModal" style="display:none;" class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="importPackagesModalTitle">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity" aria-hidden="true" onclick="closeImportPackagesModal()"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg p-6 sm:p-7 transform transition-all border border-slate-200">
            <div class="flex items-center justify-between pb-4 border-b border-gray-100">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-brand-50 flex items-center justify-center text-brand-700 shrink-0">
                        <i class="fa-solid fa-file-arrow-up text-lg" aria-hidden="true"></i>
                    </div>
                    <div>
                        <h3 id="importPackagesModalTitle" class="text-lg font-bold text-gray-900">Import Packages CSV</h3>
                        <p class="text-xs text-gray-500">Upload package master records to create or update packages</p>
                    </div>
                </div>
                <button type="button" onclick="closeImportPackagesModal()" class="rounded-lg p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-brand-500 transition cursor-pointer" aria-label="Close modal">
                    <i class="fa-solid fa-xmark text-lg" aria-hidden="true"></i>
                </button>
            </div>

            <form action="{{ route('admin.packages.import.packages') }}" method="POST" enctype="multipart/form-data" class="mt-5 space-y-4">
                @csrf
                <div>
                    <label for="package_csv_file" class="block text-sm font-semibold text-gray-700 mb-1">
                        Select Packages CSV File <span class="text-red-500">*</span>
                    </label>
                    <input type="file" name="csv_file" id="package_csv_file" accept=".csv,text/csv" required
                           class="block w-full text-sm text-gray-600 file:mr-4 file:py-2.5 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100 file:cursor-pointer border border-gray-300 rounded-lg p-1.5 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-600 transition">
                </div>

                <div class="rounded-xl bg-slate-50 border border-slate-200 p-4 space-y-2">
                    <div class="flex items-center gap-2 text-xs font-bold text-slate-700 uppercase tracking-wider">
                        <i class="fa-solid fa-circle-info text-brand-700" aria-hidden="true"></i>
                        <span>Expected Columns</span>
                    </div>
                    <p class="text-xs font-mono text-slate-600 bg-white p-2 rounded border border-slate-200 break-all select-all">
                        package_code, package_name, category, description, price, is_active, included_items
                    </p>
                    <ul class="text-xs text-slate-500 space-y-1 list-disc pl-4">
                        <li><strong>Safe Upsert:</strong> Matches existing package by <code>package_code</code>; creates new package if code is new or blank.</li>
                        <li><strong>Transactional:</strong> Import is atomic. If any row contains errors, all changes roll back.</li>
                        <li><strong>Inclusions:</strong> Multiple client-facing items can be separated by semicolons (<code>;</code>).</li>
                    </ul>
                </div>

                <div class="flex items-center justify-between text-xs text-slate-500 pt-1">
                    <span>Need the template?</span>
                    <a href="{{ route('admin.packages.template.packages') }}" class="text-brand-700 hover:text-brand-800 font-semibold inline-flex items-center gap-1">
                        <i class="fa-solid fa-download" aria-hidden="true"></i> Download Package Template
                    </a>
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
                    <button type="button" onclick="closeImportPackagesModal()" class="px-4 py-2 border border-gray-300 text-sm font-semibold rounded-lg text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-300 transition cursor-pointer">
                        Cancel
                    </button>
                    <button type="submit" class="px-5 py-2 bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-500 transition inline-flex items-center gap-2 cursor-pointer">
                        <i class="fa-solid fa-upload" aria-hidden="true"></i> Upload Packages
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Package Materials (BOM) CSV Import Modal -->
    <div id="importMaterialsModal" style="display:none;" class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="importMaterialsModalTitle">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity" aria-hidden="true" onclick="closeImportMaterialsModal()"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg p-6 sm:p-7 transform transition-all border border-slate-200">
            <div class="flex items-center justify-between pb-4 border-b border-gray-100">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-brand-50 flex items-center justify-center text-brand-700 shrink-0">
                        <i class="fa-solid fa-layer-group text-lg" aria-hidden="true"></i>
                    </div>
                    <div>
                        <h3 id="importMaterialsModalTitle" class="text-lg font-bold text-gray-900">Import Materials CSV (BOM)</h3>
                        <p class="text-xs text-gray-500">Map packages to existing Inventory Management items</p>
                    </div>
                </div>
                <button type="button" onclick="closeImportMaterialsModal()" class="rounded-lg p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-brand-500 transition cursor-pointer" aria-label="Close modal">
                    <i class="fa-solid fa-xmark text-lg" aria-hidden="true"></i>
                </button>
            </div>

            <form action="{{ route('admin.packages.import.materials') }}" method="POST" enctype="multipart/form-data" class="mt-5 space-y-4">
                @csrf
                <div>
                    <label for="materials_csv_file" class="block text-sm font-semibold text-gray-700 mb-1">
                        Select Materials CSV File <span class="text-red-500">*</span>
                    </label>
                    <input type="file" name="csv_file" id="materials_csv_file" accept=".csv,text/csv" required
                           class="block w-full text-sm text-gray-600 file:mr-4 file:py-2.5 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100 file:cursor-pointer border border-gray-300 rounded-lg p-1.5 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-600 transition">
                </div>

                <div class="rounded-xl bg-slate-50 border border-slate-200 p-4 space-y-2">
                    <div class="flex items-center gap-2 text-xs font-bold text-slate-700 uppercase tracking-wider">
                        <i class="fa-solid fa-circle-info text-brand-700" aria-hidden="true"></i>
                        <span>Expected Columns</span>
                    </div>
                    <p class="text-xs font-mono text-slate-600 bg-white p-2 rounded border border-slate-200 break-all select-all">
                        package_code, item_code, quantity
                    </p>
                    <ul class="text-xs text-slate-500 space-y-1 list-disc pl-4">
                        <li><strong>Item Code:</strong> Must match an existing Inventory Item code (e.g. <code>FRE-0001</code>).</li>
                        <li><strong>Safe Definitions:</strong> Package BOM mapping does <em>not</em> deduct or reserve inventory stock.</li>
                        <li><strong>Archived Items:</strong> Archived inventory items cannot be newly attached to packages.</li>
                    </ul>
                </div>

                <div class="flex items-center justify-between text-xs text-slate-500 pt-1">
                    <span>Need the template?</span>
                    <a href="{{ route('admin.packages.template.materials') }}" class="text-brand-700 hover:text-brand-800 font-semibold inline-flex items-center gap-1">
                        <i class="fa-solid fa-download" aria-hidden="true"></i> Download Materials Template
                    </a>
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
                    <button type="button" onclick="closeImportMaterialsModal()" class="px-4 py-2 border border-gray-300 text-sm font-semibold rounded-lg text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-300 transition cursor-pointer">
                        Cancel
                    </button>
                    <button type="submit" class="px-5 py-2 bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-500 transition inline-flex items-center gap-2 cursor-pointer">
                        <i class="fa-solid fa-upload" aria-hidden="true"></i> Upload Materials
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Package CSV Instructions Modal -->
    <div id="packageInstructionsModal" style="display:none;" class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="packageInstructionsModalTitle">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity" aria-hidden="true" onclick="closePackageInstructionsModal()"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-xl p-6 sm:p-7 transform transition-all border border-slate-200">
            <div class="flex items-center justify-between pb-4 border-b border-gray-100">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-brand-50 flex items-center justify-center text-brand-700 shrink-0">
                        <i class="fa-solid fa-circle-info text-lg" aria-hidden="true"></i>
                    </div>
                    <div>
                        <h3 id="packageInstructionsModalTitle" class="text-lg font-bold text-gray-900">Package CSV Instructions</h3>
                        <p class="text-xs text-gray-500">Master package details and Bill of Materials (BOM) guidelines</p>
                    </div>
                </div>
                <button type="button" onclick="closePackageInstructionsModal()" class="rounded-lg p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-brand-500 transition cursor-pointer" aria-label="Close modal">
                    <i class="fa-solid fa-xmark text-lg" aria-hidden="true"></i>
                </button>
            </div>

            <div class="mt-5 space-y-4 text-sm text-gray-600 max-h-[70vh] overflow-y-auto pr-1">
                <div>
                    <h4 class="text-xs font-bold uppercase tracking-wider text-brand-700 mb-1.5 flex items-center gap-1.5">
                        <i class="fa-solid fa-gift"></i> 1. Package Master CSV (packages.csv)
                    </h4>
                    <p class="text-xs text-gray-500 mb-2">Defines package identity, pricing, categories, and client-facing highlights.</p>
                    <div class="p-2.5 bg-gray-50 rounded-xl border border-gray-200 font-mono text-xs text-brand-900 break-all select-all font-semibold mb-2">
                        package_code,package_name,category,description,price,is_active,included_items
                    </div>
                    <ul class="text-xs text-gray-600 space-y-1 list-disc pl-4">
                        <li><strong>package_code:</strong> Unique identifier (max 20 chars). If empty, auto-generated upon creation. Existing codes update matching packages.</li>
                        <li><strong>package_name / title:</strong> Display title for clients and staff (required).</li>
                        <li><strong>category:</strong> Event or bundle classification (e.g., Wedding, Corporate).</li>
                        <li><strong>price:</strong> Retail package price in PHP (must be non-negative).</li>
                        <li><strong>is_active:</strong> <code>1</code> for active or <code>0</code> for inactive.</li>
                        <li><strong>included_items:</strong> Marketing highlights separated by semicolons (e.g. <code>Bridal Bouquet; 3 Corsages</code>).</li>
                    </ul>
                </div>

                <div class="pt-3 border-t border-gray-100">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-brand-700 mb-1.5 flex items-center gap-1.5">
                        <i class="fa-solid fa-layer-group"></i> 2. Package Materials CSV (package_materials.csv)
                    </h4>
                    <p class="text-xs text-gray-500 mb-2">Links packages to physical Inventory Management items (Bill of Materials).</p>
                    <div class="p-2.5 bg-gray-50 rounded-xl border border-gray-200 font-mono text-xs text-brand-900 break-all select-all font-semibold mb-2">
                        package_code,item_code,quantity
                    </div>
                    <ul class="text-xs text-gray-600 space-y-1 list-disc pl-4">
                        <li><strong>package_code:</strong> Must match an existing package code.</li>
                        <li><strong>item_code:</strong> Must match an existing inventory item code (e.g., <code>FRE-0001</code>).</li>
                        <li><strong>quantity:</strong> Amount required for this package (greater than zero; whole number for integer units).</li>
                        <li><strong>Archived Items:</strong> Archived inventory items cannot be newly added to packages.</li>
                    </ul>
                </div>

                <div class="p-3.5 bg-brand-50/60 rounded-xl border border-brand-100 text-xs text-gray-700 space-y-1.5">
                    <p class="font-bold text-brand-900 flex items-center gap-1.5">
                        <i class="fa-solid fa-shield-halved text-brand-700"></i> Inventory Safety Notice
                    </p>
                    <p class="text-gray-600">
                        Package master records and BOM mappings are <strong>definition data</strong> only. Creating or importing packages never reduces <code>current_stock</code>, never creates reservations, and never alters client bookings or payments.
                    </p>
                </div>
            </div>

            <div class="mt-6 flex flex-wrap items-center justify-between gap-3 pt-4 border-t border-gray-100">
                <div class="flex items-center gap-2">
                    <a href="{{ route('admin.packages.template.packages') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-brand-700 bg-brand-50 hover:bg-brand-100 border border-brand-200 rounded-xl transition">
                        <i class="fa-solid fa-download"></i> Package Template
                    </a>
                    <a href="{{ route('admin.packages.template.materials') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-brand-700 bg-brand-50 hover:bg-brand-100 border border-brand-200 rounded-xl transition">
                        <i class="fa-solid fa-download"></i> Materials Template
                    </a>
                </div>
                <button type="button" onclick="closePackageInstructionsModal()" class="px-4 py-2 border border-gray-300 text-xs font-semibold rounded-xl text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-300 transition cursor-pointer">
                    Close
                </button>
            </div>
        </div>
    </div>

    <!-- JAVASCRIPT LOGIC -->
    <script>
        // Modal helpers
        function openModal(id) {
            const modal = document.getElementById(id);
            if (!modal) return;
            modal.style.display = 'flex';
            modal.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        }

        function closeModal(id) {
            const modal = document.getElementById(id);
            if (!modal) return;
            modal.style.display = 'none';
            modal.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }

        // View Mode Toggle (Grid vs Table)
        function setViewMode(mode) {
            const gridView = document.getElementById('packagesGridView');
            const tableView = document.getElementById('packagesTableView');
            const gridBtn = document.getElementById('viewGridBtn');
            const tableBtn = document.getElementById('viewTableBtn');
            const viewInput = document.getElementById('packageViewModeInput');

            if (!gridView && !tableView) return;

            if (mode === 'table') {
                if (gridView) gridView.style.display = 'none';
                if (tableView) tableView.style.display = 'block';
                if (tableBtn) {
                    tableBtn.className = 'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition cursor-pointer bg-brand-700 text-white shadow-2xs';
                    tableBtn.setAttribute('aria-pressed', 'true');
                }
                if (gridBtn) {
                    gridBtn.className = 'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition cursor-pointer text-gray-600 hover:text-gray-900 hover:bg-white/60';
                    gridBtn.setAttribute('aria-pressed', 'false');
                }
                if (viewInput) viewInput.value = 'table';
                localStorage.setItem('raflora_package_view', 'table');
            } else {
                if (gridView) gridView.style.display = 'grid';
                if (tableView) tableView.style.display = 'none';
                if (gridBtn) {
                    gridBtn.className = 'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition cursor-pointer bg-brand-700 text-white shadow-2xs';
                    gridBtn.setAttribute('aria-pressed', 'true');
                }
                if (tableBtn) {
                    tableBtn.className = 'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition cursor-pointer text-gray-600 hover:text-gray-900 hover:bg-white/60';
                    tableBtn.setAttribute('aria-pressed', 'false');
                }
                if (viewInput) viewInput.value = 'grid';
                localStorage.setItem('raflora_package_view', 'grid');
            }
        }

        // Initialize view mode from storage/URL
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const viewParam = urlParams.get('view') || localStorage.getItem('raflora_package_view') || 'grid';
            setViewMode(viewParam);
        });

        // Toggle Filter Panel
        function togglePackageFilterPanel() {
            const panel = document.getElementById('packageFilterPanel');
            const btn = document.getElementById('packageToggleFiltersBtn');
            const text = document.getElementById('packageToggleFiltersText');
            const chevron = document.getElementById('packageFiltersChevron');
            const input = document.getElementById('packageFilterExpandedInput');
            if (!panel) return;

            const isHidden = panel.style.display === 'none' || panel.style.display === '';
            if (isHidden) {
                panel.style.display = 'block';
                if (text) text.textContent = 'Hide Filters';
                if (chevron) chevron.classList.add('rotate-180');
                if (btn) btn.setAttribute('aria-expanded', 'true');
                if (input) input.value = '1';
            } else {
                panel.style.display = 'none';
                if (text) text.textContent = 'Show Filters';
                if (chevron) chevron.classList.remove('rotate-180');
                if (btn) btn.setAttribute('aria-expanded', 'false');
                if (input) input.value = '0';
            }
        }

        // Package Tools Popover
        function togglePackageToolsDropdown() {
            const menu = document.getElementById('packageToolsMenu');
            const btn = document.getElementById('packageToolsButton');
            if (!menu || !btn) return;
            const isExpanded = btn.getAttribute('aria-expanded') === 'true';
            if (isExpanded) {
                closePackageToolsDropdown();
            } else {
                openPackageToolsDropdown();
            }
        }

        function openPackageToolsDropdown() {
            const menu = document.getElementById('packageToolsMenu');
            const btn = document.getElementById('packageToolsButton');
            const chevron = document.getElementById('packageToolsChevron');
            if (!menu || !btn) return;
            menu.style.display = 'block';
            btn.setAttribute('aria-expanded', 'true');
            if (chevron) chevron.classList.add('rotate-180');
        }

        function closePackageToolsDropdown() {
            const menu = document.getElementById('packageToolsMenu');
            const btn = document.getElementById('packageToolsButton');
            const chevron = document.getElementById('packageToolsChevron');
            if (!menu || !btn) return;
            menu.style.display = 'none';
            btn.setAttribute('aria-expanded', 'false');
            if (chevron) chevron.classList.remove('rotate-180');
        }

        function togglePackageCategoryDropdown() {
            const menu = document.getElementById('packageCategoryMenu');
            const btn = document.getElementById('packageCategoryButton');
            const chevron = document.getElementById('packageCategoryChevron');
            if (!menu || !btn) return;

            const isExpanded = btn.getAttribute('aria-expanded') === 'true';
            menu.style.display = isExpanded ? 'none' : 'flex';
            btn.setAttribute('aria-expanded', isExpanded ? 'false' : 'true');
            if (chevron) chevron.classList.toggle('rotate-180', !isExpanded);
        }

        function closePackageCategoryDropdown() {
            const menu = document.getElementById('packageCategoryMenu');
            const btn = document.getElementById('packageCategoryButton');
            const chevron = document.getElementById('packageCategoryChevron');
            if (!menu || !btn) return;
            menu.style.display = 'none';
            btn.setAttribute('aria-expanded', 'false');
            if (chevron) chevron.classList.remove('rotate-180');
        }

        // Card More Dropdown
        let activeCardMenuId = null;
        function toggleCardMoreMenu(id) {
            const menu = document.getElementById('cardMoreMenu-' + id);
            if (!menu) return;
            if (activeCardMenuId && activeCardMenuId !== id) {
                const prev = document.getElementById('cardMoreMenu-' + activeCardMenuId);
                if (prev) prev.style.display = 'none';
            }
            if (menu.style.display === 'none' || menu.style.display === '') {
                menu.style.display = 'block';
                activeCardMenuId = id;
            } else {
                menu.style.display = 'none';
                activeCardMenuId = null;
            }
        }

        // Global click listener to close popovers
        document.addEventListener('click', function(e) {
            const categoryContainer = document.getElementById('packageCategoryDropdown');
            if (categoryContainer && !categoryContainer.contains(e.target)) {
                closePackageCategoryDropdown();
            }
            const toolsContainer = document.getElementById('packageToolsContainer');
            if (toolsContainer && !toolsContainer.contains(e.target)) {
                closePackageToolsDropdown();
            }
            if (activeCardMenuId) {
                const cardContainer = document.getElementById('cardMoreDropdown-' + activeCardMenuId);
                if (cardContainer && !cardContainer.contains(e.target)) {
                    const menu = document.getElementById('cardMoreMenu-' + activeCardMenuId);
                    if (menu) menu.style.display = 'none';
                    activeCardMenuId = null;
                }
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closePackageCategoryDropdown();
            }
        });

        // =================================================================
        // VIEW MODAL POPULATION
        // =================================================================
        function openViewModal(pkg) {
            document.getElementById('view_title').innerText = pkg.title || 'N/A';
            document.getElementById('view_package_code').innerText = pkg.package_code || 'N/A';
            document.getElementById('view_category').innerText = pkg.category || 'Uncategorized';
            document.getElementById('view_category_top').innerText = (pkg.category || 'Package').toUpperCase();
            document.getElementById('view_price').innerText = '₱' + (parseFloat(pkg.price) || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
            document.getElementById('view_description').innerText = pkg.description || 'No description provided.';

            const statusBadge = document.getElementById('view_status_badge');
            if (!pkg.is_archived && pkg.is_active) {
                statusBadge.className = 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800';
                statusBadge.innerHTML = '<span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1.5"></span> Active';
            } else if (pkg.is_archived) {
                statusBadge.className = 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800';
                statusBadge.innerHTML = '<span class="w-1.5 h-1.5 rounded-full bg-amber-500 mr-1.5"></span> Archived';
            } else {
                statusBadge.className = 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-gray-100 text-gray-800';
                statusBadge.innerHTML = '<span class="w-1.5 h-1.5 rounded-full bg-gray-400 mr-1.5"></span> Inactive';
            }

            // Inclusions List
            const inclusionsContainer = document.getElementById('view_inclusions_container');
            const inclusionsCountEl = document.getElementById('view_inclusions_count');
            inclusionsContainer.innerHTML = '';
            let inclusions = [];
            if (Array.isArray(pkg.included_items)) {
                inclusions = pkg.included_items;
            } else if (typeof pkg.included_items === 'string' && pkg.included_items.trim() !== '') {
                inclusions = pkg.included_items.split(',').map(s => s.trim()).filter(Boolean);
            }

            inclusionsCountEl.innerText = `${inclusions.length} items`;
            if (inclusions.length > 0) {
                inclusions.forEach(item => {
                    const tag = document.createElement('span');
                    tag.className = 'inline-flex items-center gap-1.5 px-3 py-1 bg-brand-50 text-brand-700 text-xs font-semibold rounded-lg border border-brand-100';
                    tag.innerHTML = `<i class="fa-solid fa-check text-[10px]"></i> ${item}`;
                    inclusionsContainer.appendChild(tag);
                });
            } else {
                inclusionsContainer.innerHTML = '<p class="text-xs text-gray-400 italic">No client-facing inclusions defined.</p>';
            }

            // Inventory BOM Requirements List
            const invContainer = document.getElementById('view_inventory_container');
            const bomCountEl = document.getElementById('view_bom_count');
            invContainer.innerHTML = '';
            let bomItems = (pkg.inventory_items || []).filter(item => item.pivot && parseFloat(item.pivot.quantity) > 0);
            bomCountEl.innerText = `${bomItems.length} materials`;

            if (bomItems.length > 0) {
                bomItems.forEach(item => {
                    const isArchived = Boolean(item.deleted_at);
                    const row = document.createElement('div');
                    row.className = 'flex items-center justify-between border-b border-gray-100 pb-2.5 last:border-0 last:pb-0';
                    row.innerHTML = `
                        <div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <p class="text-xs font-bold text-gray-800">${item.name}</p>
                                <span class="font-mono text-[10px] bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded border border-gray-200">${item.item_code || 'N/A'}</span>
                                ${isArchived ? '<span class="bg-amber-100 text-amber-800 text-[10px] font-bold px-1.5 py-0.5 rounded">Archived</span>' : ''}
                            </div>
                            <div class="flex items-center gap-2 text-[11px] text-gray-500 mt-0.5">
                                <span>${item.category}</span>
                                <span>•</span>
                                <span>In Stock: <strong class="text-gray-700">${parseFloat(item.current_stock) || 0}</strong> ${item.unit}</span>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-xs font-extrabold text-brand-700">
                                ${parseFloat(item.pivot.quantity)} <span class="text-gray-500 font-normal">${item.unit}</span>
                            </div>
                            <span class="text-[10px] text-gray-400 uppercase tracking-wider">Required</span>
                        </div>
                    `;
                    invContainer.appendChild(row);
                });
            } else {
                invContainer.innerHTML = '<p class="text-xs text-gray-400 italic">No inventory BOM mapping configured.</p>';
            }

            // Edit button hook
            const editBtn = document.getElementById('view_edit_btn');
            if (editBtn) {
                editBtn.onclick = function() {
                    closeModal('viewModal');
                    openEditModal(pkg);
                };
            }

            openModal('viewModal');
        }

        // =================================================================
        // INCLUSION CHIPS MANAGER (Add & Edit)
        // =================================================================
        const inclusionState = {
            add: [],
            edit: []
        };

        function addInclusionChip(formPrefix) {
            const input = document.getElementById(`${formPrefix}_inclusion_input`);
            if (!input) return;
            const val = input.value.trim();
            if (val === '') return;

            // Handle comma or semicolon separation if pasted
            const parts = val.split(/[,;]/).map(s => s.trim()).filter(Boolean);
            parts.forEach(part => {
                if (!inclusionState[formPrefix].includes(part)) {
                    inclusionState[formPrefix].push(part);
                }
            });

            input.value = '';
            renderInclusionChips(formPrefix);
        }

        function removeInclusionChip(formPrefix, index) {
            inclusionState[formPrefix].splice(index, 1);
            renderInclusionChips(formPrefix);
        }

        function renderInclusionChips(formPrefix) {
            const container = document.getElementById(`${formPrefix}_inclusions_chips`);
            const hiddenInput = document.getElementById(`${formPrefix}_included_items_serialized`);
            if (!container || !hiddenInput) return;

            container.innerHTML = '';
            inclusionState[formPrefix].forEach((item, index) => {
                const chip = document.createElement('span');
                chip.className = 'inline-flex items-center gap-1.5 px-3 py-1 bg-white border border-brand-200 text-brand-900 rounded-xl text-xs font-semibold shadow-2xs';
                chip.innerHTML = `
                    <span>${item}</span>
                    <button type="button" onclick="removeInclusionChip('${formPrefix}', ${index})" class="text-brand-400 hover:text-red-500 transition cursor-pointer" aria-label="Remove inclusion">
                        <i class="fa-solid fa-xmark text-xs"></i>
                    </button>
                `;
                container.appendChild(chip);
            });

            hiddenInput.value = inclusionState[formPrefix].join(', ');
        }

        // =================================================================
        // BILL OF MATERIALS (BOM) SELECTION & QUANTITIES (Add & Edit)
        // =================================================================
        const bomState = {
            add: {},
            edit: {}
        };

        function filterInventoryCatalog(formPrefix) {
            const query = (document.getElementById(`${formPrefix}_bom_search_input`).value || '').toLowerCase().trim();
            const category = document.getElementById(`${formPrefix}_bom_category_filter`).value;
            const container = document.getElementById(`${formPrefix}_bom_catalog_list`);
            if (!container) return;

            const rows = container.querySelectorAll('.bom-catalog-row');
            rows.forEach(row => {
                const name = (row.dataset.name || '').toLowerCase();
                const code = (row.dataset.code || '').toLowerCase();
                const cat = row.dataset.category || '';

                const matchesQuery = !query || name.includes(query) || code.includes(query);
                const matchesCategory = !category || cat === category;

                row.style.display = (matchesQuery && matchesCategory) ? 'flex' : 'none';
            });
        }

        function addMaterialToBom(formPrefix, id, name, code, unit, stock, initialQty = 1) {
            if (!bomState[formPrefix][id]) {
                bomState[formPrefix][id] = {
                    id: id,
                    name: name,
                    code: code,
                    unit: unit,
                    stock: stock,
                    quantity: initialQty
                };
            }
            renderSelectedBomTable(formPrefix);
        }

        function removeMaterialFromBom(formPrefix, id) {
            delete bomState[formPrefix][id];
            renderSelectedBomTable(formPrefix);
        }

        function updateBomItemQty(formPrefix, id, qty) {
            if (bomState[formPrefix][id]) {
                bomState[formPrefix][id].quantity = qty;
            }
        }

        function renderSelectedBomTable(formPrefix) {
            const tbody = document.getElementById(`${formPrefix}_selected_bom_tbody`);
            const summary = document.getElementById(`${formPrefix}_bom_count_summary`);
            if (!tbody) return;

            const items = Object.values(bomState[formPrefix]);
            if (summary) summary.innerText = `${items.length} items selected`;

            if (items.length === 0) {
                tbody.innerHTML = `
                    <tr id="${formPrefix}_empty_bom_row">
                        <td colspan="5" class="px-4 py-5 text-center text-gray-400 italic">
                            No inventory materials selected yet. Search above and click "+ Add" to include materials in this package.
                        </td>
                    </tr>
                `;
                return;
            }

            tbody.innerHTML = '';
            items.forEach(item => {
                const tr = document.createElement('tr');
                tr.className = 'hover:bg-brand-50/20 transition-colors';
                tr.innerHTML = `
                    <td class="px-3 py-2.5">
                        <span class="font-bold text-gray-800">${item.name}</span>
                    </td>
                    <td class="px-2 py-2.5">
                        <span class="font-mono text-[10px] bg-gray-100 text-gray-600 px-1 rounded">${item.code}</span>
                    </td>
                    <td class="px-2 py-2.5 text-gray-600">
                        ${item.stock} ${item.unit}
                    </td>
                    <td class="px-3 py-2.5">
                        <div class="flex items-center gap-1.5">
                            <input
                                type="number"
                                name="inventory_items[${item.id}]"
                                value="${item.quantity}"
                                min="0.01"
                                step="0.01"
                                required
                                oninput="updateBomItemQty('${formPrefix}', ${item.id}, this.value)"
                                class="w-20 px-2.5 py-1 text-xs border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-600 font-bold text-brand-700 bg-white"
                            >
                            <span class="text-xs text-gray-500">${item.unit}</span>
                        </div>
                    </td>
                    <td class="px-2 py-2.5 text-right">
                        <button type="button" onclick="removeMaterialFromBom('${formPrefix}', ${item.id})" class="text-gray-400 hover:text-red-600 p-1 rounded transition cursor-pointer" title="Remove material">
                            <i class="fa-solid fa-xmark text-sm"></i>
                        </button>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        }

        // =================================================================
        // ADD PACKAGE MODAL CONTROLS
        // =================================================================
        function openAddPackageModal() {
            inclusionState.add = [];
            bomState.add = {};
            renderInclusionChips('add');
            renderSelectedBomTable('add');
            const preview = document.getElementById('add_image_preview');
            if (preview) preview.classList.add('hidden');
            openModal('addPackageModal');
        }

        // =================================================================
        // EDIT PACKAGE MODAL CONTROLS
        // =================================================================
        function openEditModal(pkg) {
            const form = document.getElementById('editPackageForm');
            form.action = `/admin/packages/${pkg.id}`;

            document.getElementById('edit_title').value = pkg.title || '';
            document.getElementById('edit_package_code').value = pkg.package_code || '';
            document.getElementById('edit_category').value = pkg.category || '';
            document.getElementById('edit_price').value = parseFloat(pkg.price) || 0;
            document.getElementById('edit_description').value = pkg.description || '';

            const isActiveCheckbox = document.getElementById('edit_is_active');
            if (isActiveCheckbox) {
                isActiveCheckbox.checked = Boolean(pkg.is_active);
            }

            // Prefill Inclusions
            inclusionState.edit = [];
            if (Array.isArray(pkg.included_items)) {
                inclusionState.edit = [...pkg.included_items];
            } else if (typeof pkg.included_items === 'string' && pkg.included_items.trim() !== '') {
                inclusionState.edit = pkg.included_items.split(',').map(s => s.trim()).filter(Boolean);
            }
            renderInclusionChips('edit');

            // Prefill BOM Items
            bomState.edit = {};
            if (pkg.inventory_items && Array.isArray(pkg.inventory_items)) {
                pkg.inventory_items.forEach(item => {
                    const qty = item.pivot ? parseFloat(item.pivot.quantity) : 0;
                    if (qty > 0) {
                        bomState.edit[item.id] = {
                            id: item.id,
                            name: item.name,
                            code: item.item_code || 'N/A',
                            unit: item.unit,
                            stock: parseFloat(item.current_stock) || 0,
                            quantity: qty
                        };
                    }
                });
            }
            renderSelectedBomTable('edit');

            // Current Image Preview
            const imgPreview = document.getElementById('edit_image_preview');
            const imgTag = document.getElementById('edit_current_img_tag');
            const coverImage = (pkg.images && pkg.images.length > 0) ? `/storage/${pkg.images[0].image_path}` : (pkg.image_path ? `/storage/${pkg.image_path}` : '');
            if (coverImage) {
                imgTag.src = coverImage;
                imgPreview.classList.remove('hidden');
            } else {
                imgPreview.classList.add('hidden');
            }

            openModal('editPackageModal');
        }

        // Image Preview Helper
        function previewImageFile(input, previewContainerId) {
            const container = document.getElementById(previewContainerId);
            if (!container) return;
            const img = container.querySelector('img');
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    if (img) img.src = e.target.result;
                    container.classList.remove('hidden');
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        // =================================================================
        // CONFIRMATION MODAL HELPER
        // =================================================================
        let pendingFormId = null;
        function openConfirmModal(formId, title, message, confirmBtnText, actionType, buttonElement) {
            pendingFormId = formId;
            document.getElementById('confirmTitle').innerText = title;
            document.getElementById('confirmMessage').innerText = message;
            
            const submitBtn = document.getElementById('confirmSubmitBtn');
            const iconWrapper = document.getElementById('confirmIconWrapper');
            const icon = document.getElementById('confirmIcon');

            submitBtn.innerText = confirmBtnText;

            if (actionType === 'delete') {
                iconWrapper.className = 'w-10 h-10 rounded-xl bg-red-100 text-red-600 flex items-center justify-center shrink-0';
                icon.className = 'fa-solid fa-trash-can text-base';
                submitBtn.className = 'px-4 py-2 rounded-xl text-xs font-bold text-white bg-red-600 hover:bg-red-700 transition shadow-xs cursor-pointer';
            } else if (actionType === 'restore') {
                iconWrapper.className = 'w-10 h-10 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center shrink-0';
                icon.className = 'fa-solid fa-rotate-left text-base';
                submitBtn.className = 'px-4 py-2 rounded-xl text-xs font-bold text-white bg-brand-700 hover:bg-brand-800 transition shadow-xs cursor-pointer';
            } else {
                // archive
                iconWrapper.className = 'w-10 h-10 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center shrink-0';
                icon.className = 'fa-solid fa-box-archive text-base';
                submitBtn.className = 'px-4 py-2 rounded-xl text-xs font-bold text-white bg-amber-600 hover:bg-amber-700 transition shadow-xs cursor-pointer';
            }

            submitBtn.onclick = function() {
                if (pendingFormId) {
                    const form = document.getElementById(pendingFormId);
                    if (form) form.submit();
                }
            };

            openModal('confirmActionModal');
        }

        function closeConfirmModal() {
            pendingFormId = null;
            closeModal('confirmActionModal');
        }

        // =================================================================
        // LIGHTBOX & CSV MODAL CONTROLS
        // =================================================================
        const packageData = @json($lightboxData);
        let currentPackageId = null;
        let currentImageIndex = 0;
        let currentImages = [];

        function openLightbox(id) {
            const item = packageData.find(g => g.id === String(id));
            if (!item || !item.images || item.images.length === 0) return;

            currentPackageId = id;
            currentImages = item.images;
            currentImageIndex = 0;

            document.getElementById('lightboxTitle').textContent = item.title;
            const categoryEl = document.getElementById('lightboxCategory');
            if (categoryEl) categoryEl.textContent = item.tags[0] || 'Uncategorized';

            const thumbnailsContainer = document.getElementById('lightboxThumbnails');
            thumbnailsContainer.innerHTML = '';
            currentImages.forEach((imgUrl, index) => {
                thumbnailsContainer.innerHTML += `
                    <button onclick="goToImage(${index})" class="flex-shrink-0 snap-center w-20 h-20 rounded-md overflow-hidden border-2 transition-all duration-300 ${index === 0 ? 'border-green-600 opacity-100 shadow-sm' : 'border-transparent opacity-50 hover:opacity-100'}" style="width: 80px; height: 80px; border-radius: 8px;">
                        <img src="${imgUrl}" class="w-full h-full object-cover" style="width: 100%; height: 100%; object-fit: cover;">
                    </button>
                `;
            });

            updateLightboxView();

            const lightbox = document.getElementById('galleryLightbox');
            lightbox.classList.remove('hidden');
            setTimeout(() => {
                lightbox.classList.remove('opacity-0');
            }, 10);
            document.body.classList.add('overflow-hidden');
        }

        function closeLightbox() {
            const lightbox = document.getElementById('galleryLightbox');
            lightbox.classList.add('opacity-0');
            setTimeout(() => {
                lightbox.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
                currentPackageId = null;
            }, 300);
        }

        function updateLightboxView() {
            document.getElementById('lightboxMainImg').src = currentImages[currentImageIndex];
            document.getElementById('lightboxCounter').textContent = `${currentImageIndex + 1} / ${currentImages.length}`;

            document.getElementById('lightboxPrevBtn').classList.toggle('hidden', currentImages.length <= 1);
            document.getElementById('lightboxNextBtn').classList.toggle('hidden', currentImages.length <= 1);

            const thumbnails = document.getElementById('lightboxThumbnails').children;
            Array.from(thumbnails).forEach((btn, index) => {
                if (index === currentImageIndex) {
                    btn.classList.remove('border-transparent', 'opacity-50');
                    btn.classList.add('border-green-600', 'opacity-100', 'shadow-sm');
                    btn.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
                } else {
                    btn.classList.add('border-transparent', 'opacity-50');
                    btn.classList.remove('border-green-600', 'opacity-100', 'shadow-sm');
                }
            });
        }

        function prevImage(e) {
            if (e) e.stopPropagation();
            if (currentImages.length <= 1) return;
            currentImageIndex = (currentImageIndex - 1 + currentImages.length) % currentImages.length;
            updateLightboxView();
        }

        function nextImage(e) {
            if (e) e.stopPropagation();
            if (currentImages.length <= 1) return;
            currentImageIndex = (currentImageIndex + 1) % currentImages.length;
            updateLightboxView();
        }

        function goToImage(index) {
            currentImageIndex = index;
            updateLightboxView();
        }

        function openImportPackagesModal() {
            closePackageToolsDropdown();
            openModal('importPackagesModal');
        }

        function closeImportPackagesModal() {
            closeModal('importPackagesModal');
        }

        function openImportMaterialsModal() {
            closePackageToolsDropdown();
            openModal('importMaterialsModal');
        }

        function closeImportMaterialsModal() {
            closeModal('importMaterialsModal');
        }

        function openPackageInstructionsModal() {
            closePackageToolsDropdown();
            openModal('packageInstructionsModal');
        }

        function closePackageInstructionsModal() {
            closeModal('packageInstructionsModal');
        }

        // Global keybindings (Escape closes open modal or popover)
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const lightbox = document.getElementById('galleryLightbox');
                if (lightbox && !lightbox.classList.contains('hidden')) {
                    closeLightbox();
                    return;
                }
                const modals = ['confirmActionModal', 'viewModal', 'addPackageModal', 'editPackageModal', 'importPackagesModal', 'importMaterialsModal', 'packageInstructionsModal'];
                modals.forEach(m => closeModal(m));
                closePackageToolsDropdown();
            }
        });
    </script>
</x-admin-layout>
