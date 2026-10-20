<x-admin-layout title="Packages">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-xl sm:text-2xl font-bold text-gray-800 font-serif">Packages</h2>
            <p class="text-sm text-gray-500 mt-1">Manage public booking packages, pricing, and master inventory mappings (BOM).</p>
        </div>
        <div>
            <a href="{{ route('admin.packages.index') }}" class="inline-flex items-center text-sm font-medium text-gray-500 hover:text-gray-700 transition">
                <i class="fa-solid fa-gift mr-2"></i> Active Packages
            </a>
        </div>
    </div>

    <!-- Tabs -->
    <div class="mb-6 border-b border-gray-200">
        <nav class="-mb-px flex space-x-8" aria-label="Tabs">
            <a href="{{ route('admin.packages.index') }}" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                Active Packages
            </a>
            <a href="{{ route('admin.packages.archived') }}" class="border-brand-600 text-brand-700 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                Archived Packages
            </a>
        </nav>
    </div>

    <!-- Search & Filter Toolbar -->
    <div class="bg-white rounded-2xl shadow-xs border border-gray-100 p-4 sm:p-5 mb-6 overflow-visible">
        <form method="GET" action="{{ route('admin.packages.archived') }}" id="archivedPackageFilterForm">
            <input type="hidden" name="filter_expanded" id="archivedPackageFilterExpandedInput" value="{{ request('filter_expanded', '0') }}">

            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <!-- Left: Search input, Show Filters toggle, Search button -->
                <div class="flex flex-wrap items-center gap-2.5 sm:gap-3 flex-1">
                    <!-- Keyword Search Input -->
                    <div class="relative flex-1 min-w-[200px] sm:min-w-[260px] max-w-sm">
                        <span class="absolute inset-y-0 left-3 flex items-center pointer-events-none text-gray-400">
                            <i class="fa-solid fa-magnifying-glass text-xs"></i>
                        </span>
                        <input
                            type="text"
                            name="search"
                            id="archivedPackageSearchInput"
                            value="{{ $currentSearch ?? request('search') }}"
                            placeholder="Search package name, category, or keyword..."
                            class="w-full pl-9 pr-3.5 py-2 bg-gray-50/70 border border-gray-200 rounded-xl text-sm focus:bg-white focus:ring-2 focus:ring-brand-500 focus:border-brand-600 transition shadow-2xs"
                        >
                    </div>

                    @php
                        $hasActiveArchivedFilters = (($currentCategory ?? 'all') !== 'all') || (($currentSort ?? 'latest') !== 'latest');
                        $isArchivedFilterOpen = request('filter_expanded') === '1';
                    @endphp

                    <!-- Show Filters Button -->
                    <button
                        type="button"
                        id="archivedPackageToggleFiltersBtn"
                        onclick="toggleArchivedPackageFilterPanel()"
                        aria-expanded="{{ $isArchivedFilterOpen ? 'true' : 'false' }}"
                        aria-controls="archivedPackageFilterPanel"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 border rounded-xl text-sm font-medium transition shadow-2xs focus:outline-none focus:ring-2 focus:ring-brand-500 cursor-pointer {{ $hasActiveArchivedFilters ? 'border-brand-300 bg-brand-50 text-brand-700 font-semibold' : 'border-gray-200 hover:border-brand-300 bg-white hover:bg-brand-50/50 text-gray-700 hover:text-brand-800' }}"
                    >
                        <i class="fa-solid fa-sliders text-xs {{ $hasActiveArchivedFilters ? 'text-brand-700' : 'text-gray-500' }}"></i>
                        <span id="archivedPackageToggleFiltersText">{{ $isArchivedFilterOpen ? 'Hide Filters' : 'Show Filters' }}</span>
                        @if($hasActiveArchivedFilters)
                            <span class="w-1.5 h-1.5 rounded-full bg-brand-700 inline-block" title="Filters are active"></span>
                        @endif
                        <i id="archivedPackageFiltersChevron" class="fa-solid fa-chevron-down text-[10px] transition-transform duration-200 {{ $isArchivedFilterOpen ? 'rotate-180' : '' }}"></i>
                    </button>

                    <!-- Search Submit Button -->
                    <button
                        type="submit"
                        class="inline-flex items-center gap-1.5 px-4 py-2 bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold rounded-xl transition shadow-2xs focus:outline-none focus:ring-2 focus:ring-brand-500 cursor-pointer"
                    >
                        <i class="fa-solid fa-magnifying-glass text-xs"></i>
                        <span>Search</span>
                    </button>

                    @if(!empty($currentSearch))
                        <a
                            href="{{ route('admin.packages.archived') }}"
                            class="px-3 py-2 text-xs font-semibold text-gray-500 hover:text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-xl transition"
                        >
                            Clear
                        </a>
                    @endif
                </div>
            </div>

            <!-- Collapsible Filter Panel (Category, Sort, Reset) -->
            <div
                id="archivedPackageFilterPanel"
                style="{{ $isArchivedFilterOpen ? 'display: block;' : 'display: none;' }}"
                class="mt-4 pt-4 border-t border-gray-100"
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
                            id="archivedPackageCategoryFilter"
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

                    <!-- Sort Dropdown -->
                    <div class="relative min-w-[170px]">
                        <span class="absolute inset-y-0 left-3 flex items-center pointer-events-none text-gray-400">
                            <i class="fa-solid fa-arrow-down-wide-short text-xs"></i>
                        </span>
                        <select
                            name="sort"
                            id="archivedPackageSortFilter"
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

                    <!-- Clear / Reset Link -->
                    <a
                        href="{{ route('admin.packages.archived') }}"
                        class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-gray-600 hover:text-gray-900 bg-white hover:bg-gray-100 border border-gray-200 rounded-xl transition shadow-2xs sm:ml-auto"
                    >
                        <i class="fa-solid fa-rotate-left text-[11px] text-gray-400"></i>
                        <span>Reset Filters</span>
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Package Grid -->
    @if($packages->count() > 0)
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 md:gap-6 relative">
            @foreach($packages as $package)
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 group hover:shadow-xl hover:shadow-brand-900/5 transition-all duration-300 overflow-hidden flex flex-col relative opacity-75 hover:opacity-100">
                    @php
                        $coverImage = $package->images->first() ? asset('storage/' . $package->images->first()->image_path) : '';
                        $imageCount = $package->images->count();
                    @endphp
                    <div class="relative w-full overflow-hidden bg-slate-100 grayscale hover:grayscale-0 transition-all duration-300" style="aspect-ratio: 3/2;" onclick="openLightbox('{{ $package->id }}')" role="button" tabindex="0">
                        @if($coverImage)
                            <img src="{{ $coverImage }}" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700 ease-in-out cursor-pointer" alt="{{ $package->title }}">
                            <div class="absolute bottom-2 right-2 bg-slate-900/75 backdrop-blur-md text-white text-[10px] font-bold px-2 py-1 rounded-md flex items-center gap-1.5 pointer-events-none">
                                <i class="fa-solid fa-camera"></i> {{ $imageCount }}
                            </div>
                        @else
                            <div class="w-full h-full bg-brand-50 flex items-center justify-center text-brand-300 group-hover:scale-110 transition-transform duration-700 ease-in-out cursor-pointer">
                                <i class="fa-solid fa-gift text-5xl"></i>
                            </div>
                        @endif
                        <div class="absolute top-2 right-2 bg-slate-900/75 backdrop-blur-md text-white text-[10px] font-bold px-2 py-1 rounded-md flex items-center pointer-events-none">
                            Archived
                        </div>
                    </div>
                    <div class="p-4 sm:p-5 flex-1 flex flex-col justify-between">
                        <div class="text-center">
                            @if($package->category)
                                <div class="mb-1"><span class="text-[10px] font-bold tracking-widest uppercase text-brand-700">{{ $package->category }}</span></div>
                            @endif
                            <h3 class="font-bold text-[#1e293b] text-[15px] sm:text-base mb-1 line-clamp-1 group-hover:text-brand-800 transition-colors serif">{{ $package->title }}</h3>
                            <p class="text-lg sm:text-xl font-extrabold text-brand-700 mb-4">₱{{ number_format($package->price, 2) }}</p>
                            
                            @if($package->description)
                                <p class="text-xs text-slate-500 mb-3 line-clamp-2">{{ $package->description }}</p>
                            @endif
                        </div>
                        
                        <!-- Admin Actions -->
                        <div class="flex items-center justify-between pt-3 border-t border-slate-100">
                            <button onclick='openViewModal(@json($package))' class="text-sm font-semibold text-brand-700 hover:text-brand-800 flex items-center gap-1.5 transition-colors" type="button">
                                <i class="fa-regular fa-eye"></i> View
                            </button>
                            <form action="{{ route('admin.packages.restore', $package) }}" method="POST" id="restore-form-{{ $package->id }}" class="inline-block m-0">
                                @csrf
                                <button type="button" onclick="openConfirmModal('restore-form-{{ $package->id }}', 'Restore Package?', 'This package will become available for new bookings again.', 'Restore Package', 'restore', this)" class="text-sm font-semibold text-brand-700 hover:text-brand-800 flex items-center gap-1.5 transition-colors">
                                    <i class="fa-solid fa-rotate-left"></i> Restore
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        
        <div class="mt-8 flex justify-center">
        </div>
    @else
        <!-- Empty State -->
        <div class="text-center py-16 bg-white rounded-2xl shadow-sm border border-slate-100">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-slate-50 mb-4">
                <i class="fa-solid fa-box-archive text-2xl text-slate-400"></i>
            </div>
            @if(!empty($currentSearch) || (($currentCategory ?? 'all') !== 'all'))
                <h3 class="text-lg font-medium text-slate-900">No matching archived packages found</h3>
                <p class="mt-1 text-sm text-slate-500 mb-6">Try adjusting your keyword search or category filter.</p>
                <a href="{{ route('admin.packages.archived') }}" class="inline-flex items-center px-4 py-2 border border-brand-200 text-brand-700 bg-brand-50 hover:bg-brand-100 rounded-xl text-sm font-semibold transition">
                    Clear Filters
                </a>
            @else
                <h3 class="text-lg font-medium text-slate-900">No archived packages</h3>
                <p class="mt-1 text-sm text-slate-500 mb-6">Archived packages will appear here.</p>
            @endif
        </div>
    @endif

    <!-- View Package Modal -->
    <div id="viewModal" class="fixed inset-0 z-[100] hidden flex-col items-center justify-center bg-slate-900/80 backdrop-blur-[2px] transition-opacity duration-300 p-4 sm:p-6" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <!-- Modal panel -->
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-3xl flex flex-col relative overflow-hidden my-auto mx-auto max-h-[90vh]">
            <button onclick="closeModal('viewModal')" class="absolute top-4 right-4 sm:top-5 sm:right-5 z-20 w-12 h-12 rounded-full flex items-center justify-center bg-white text-slate-800 hover:bg-slate-100 shadow-md transition-transform hover:scale-105 border border-slate-100">
                <i class="fa-solid fa-xmark text-xl"></i>
            </button>
            
            <div class="px-6 sm:px-8 py-5 border-b border-gray-100 shrink-0 pr-20">
                <h3 class="text-2xl font-bold text-slate-900 serif">View Package</h3>
            </div>
            
            <div class="px-6 sm:px-8 py-6 space-y-6 overflow-y-auto flex-1 bg-slate-50/50">
                <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm">
                    <h4 class="text-xs font-bold tracking-widest uppercase text-slate-400 mb-4 border-b border-slate-100 pb-2">1. Package Information</h4>
                    <div class="grid grid-cols-2 gap-x-6 gap-y-4">
                        <div>
                            <span class="block text-xs font-medium text-slate-500 mb-1">Package Name</span>
                            <span id="view_title" class="block text-sm font-semibold text-slate-900"></span>
                        </div>
                        <div>
                            <span class="block text-xs font-medium text-slate-500 mb-1">Package Code</span>
                            <span id="view_package_code" class="block text-sm font-mono text-slate-900 bg-slate-100 px-2 py-0.5 rounded w-max"></span>
                        </div>
                        <div>
                            <span class="block text-xs font-medium text-slate-500 mb-1">Category</span>
                            <span id="view_category" class="block text-sm text-slate-700"></span>
                        </div>
                        <div>
                            <span class="block text-xs font-medium text-slate-500 mb-1">Price</span>
                            <span id="view_price" class="block text-sm font-semibold text-brand-700"></span>
                        </div>
                        <div class="col-span-2">
                            <span class="block text-xs font-medium text-slate-500 mb-1">Status</span>
                            <span id="view_status_badge" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"></span>
                        </div>
                    </div>
                </div>

                <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm">
                    <h4 class="text-xs font-bold tracking-widest uppercase text-slate-400 mb-4 border-b border-slate-100 pb-2">2. Description</h4>
                    <p id="view_description" class="text-sm text-slate-700 whitespace-pre-wrap leading-relaxed"></p>
                </div>

                <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm">
                    <h4 class="text-xs font-bold tracking-widest uppercase text-slate-400 mb-4 border-b border-slate-100 pb-2">3. Included in the Package</h4>
                    <p id="view_included_items" class="text-sm text-slate-700 whitespace-pre-wrap leading-relaxed"></p>
                </div>

                <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm">
                    <h4 class="text-xs font-bold tracking-widest uppercase text-slate-400 mb-4 border-b border-slate-100 pb-2">4. Master Inventory Mapping</h4>
                    <div id="view_inventory_container" class="space-y-2">
                        <!-- Populated by JS -->
                    </div>
                </div>
            </div>
            
            <div class="px-6 sm:px-8 py-4 border-t border-gray-100 flex justify-end gap-3 bg-white shrink-0">
                <button type="button" onclick="closeModal('viewModal')" class="px-6 py-2.5 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition shadow-sm">Close</button>
            </div>
        </div>
    </div>

    <!-- Shared Lightbox JavaScript & Markup -->
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

    <script>
        function openModal(id) {
            const modal = document.getElementById(id);
            modal.style.display = 'flex';
            setTimeout(() => {
                modal.classList.remove('hidden');
            }, 10);
            document.body.classList.add('overflow-hidden');
        }
        function closeModal(id) {
            const modal = document.getElementById(id);
            modal.style.display = 'none';
            modal.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }
        function openViewModal(pkg) {
            document.getElementById('view_title').innerText = pkg.title || 'N/A';
            document.getElementById('view_package_code').innerText = pkg.package_code || 'N/A';
            document.getElementById('view_category').innerText = pkg.category || 'N/A';
            document.getElementById('view_price').innerText = '₱' + (parseFloat(pkg.price) || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
            document.getElementById('view_description').innerText = pkg.description || 'No description provided.';
            document.getElementById('view_included_items').innerText = Array.isArray(pkg.included_items) && pkg.included_items.length > 0 ? pkg.included_items.join(', ') : 'No items specified.';
            
            var statusBadge = document.getElementById('view_status_badge');
            if (pkg.is_active) {
                statusBadge.className = 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800';
                statusBadge.innerText = 'Active';
            } else {
                statusBadge.className = 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800';
                statusBadge.innerText = 'Archived / Inactive';
            }

            var invContainer = document.getElementById('view_inventory_container');
            invContainer.innerHTML = '';
            
            var hasInventory = false;
            if (pkg.inventory_items && Array.isArray(pkg.inventory_items)) {
                pkg.inventory_items.forEach(function(item) {
                    if (item.pivot && item.pivot.quantity > 0) {
                        hasInventory = true;
                        const isArchived = Boolean(item.deleted_at);
                        invContainer.innerHTML += `
                            <div class="flex items-center justify-between border-b border-gray-100 pb-2.5 last:border-0 last:pb-0">
                                <div>
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <p class="text-[13px] font-semibold text-gray-800">${item.name}</p>
                                        <span class="font-mono text-[10px] bg-slate-100 text-slate-600 px-1.5 py-0.5 rounded border border-slate-200">${item.item_code || 'N/A'}</span>
                                        ${isArchived ? '<span class="bg-amber-100 text-amber-800 text-[10px] font-bold px-1.5 py-0.5 rounded">Archived</span>' : ''}
                                    </div>
                                    <div class="flex items-center gap-2 text-[11px] text-gray-500 mt-0.5">
                                        <span>${item.category}</span>
                                        <span>•</span>
                                        <span>In Stock: <strong class="text-slate-700">${parseFloat(item.current_stock) || 0}</strong> ${item.unit}</span>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="text-[13px] font-bold text-brand-700">
                                        ${parseFloat(item.pivot.quantity)} <span class="text-gray-500 font-normal">${item.unit}</span>
                                    </div>
                                    <span class="text-[10px] text-gray-400 uppercase tracking-wider">Required</span>
                                </div>
                            </div>
                        `;
                    }
                });
            }
            
            if (!hasInventory) {
                invContainer.innerHTML = '<p class="text-[13px] text-gray-500 italic">No inventory mapping configured.</p>';
            }

            openModal('viewModal');
        }

        // Lightbox Functions
        const packageData = @json($lightboxData);
        
        let currentPackageId = null;
        let currentImageIndex = 0;
        let currentImages = [];

        let lightbox, lightboxTitle, lightboxMainImg, lightboxCounter, lightboxThumbnails, lightboxPrevBtn, lightboxNextBtn, lightboxCategory;

        function openLightbox(id) {
            if (!lightbox) {
                lightbox = document.getElementById('galleryLightbox');
                lightboxTitle = document.getElementById('lightboxTitle');
                lightboxMainImg = document.getElementById('lightboxMainImg');
                lightboxCounter = document.getElementById('lightboxCounter');
                lightboxThumbnails = document.getElementById('lightboxThumbnails');
                lightboxPrevBtn = document.getElementById('lightboxPrevBtn');
                lightboxNextBtn = document.getElementById('lightboxNextBtn');
                lightboxCategory = document.getElementById('lightboxCategory');
            }

            const item = packageData.find(g => g.id === String(id));
            if (!item || !item.images || item.images.length === 0) return;

            currentPackageId = id;
            currentImages = item.images;
            currentImageIndex = 0;
            
            lightboxTitle.textContent = item.title;
            if (lightboxCategory) lightboxCategory.textContent = item.tags[0] || 'Uncategorized';
            
            lightboxThumbnails.innerHTML = '';
            currentImages.forEach((imgUrl, index) => {
                lightboxThumbnails.innerHTML += `
                    <button onclick="goToImage(${index})" class="flex-shrink-0 snap-center w-20 h-20 rounded-md overflow-hidden border-2 transition-all duration-300 ${index === 0 ? 'border-green-600 opacity-100 shadow-sm' : 'border-transparent opacity-50 hover:opacity-100'}" style="width: 80px; height: 80px; border-radius: 8px;">
                        <img src="${imgUrl}" class="w-full h-full object-cover" style="width: 100%; height: 100%; object-fit: cover;">
                    </button>
                `;
            });

            updateLightboxView();
            
            lightbox.classList.remove('hidden');
            setTimeout(() => {
                lightbox.classList.remove('opacity-0');
            }, 10);
            document.body.classList.add('overflow-hidden');
        }

        function closeLightbox() {
            lightbox.classList.add('opacity-0');
            setTimeout(() => {
                lightbox.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
                currentPackageId = null;
            }, 300);
        }

        function updateLightboxView() {
            lightboxMainImg.src = currentImages[currentImageIndex];
            lightboxCounter.textContent = `${currentImageIndex + 1} / ${currentImages.length}`;
            
            lightboxPrevBtn.classList.toggle('hidden', currentImages.length <= 1);
            lightboxNextBtn.classList.toggle('hidden', currentImages.length <= 1);
            
            Array.from(lightboxThumbnails.children).forEach((btn, index) => {
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

        function toggleArchivedPackageFilterPanel() {
            const panel = document.getElementById('archivedPackageFilterPanel');
            const btn = document.getElementById('archivedPackageToggleFiltersBtn');
            const text = document.getElementById('archivedPackageToggleFiltersText');
            const chevron = document.getElementById('archivedPackageFiltersChevron');
            const input = document.getElementById('archivedPackageFilterExpandedInput');
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

        document.addEventListener('keydown', (e) => {
            if (!lightbox || lightbox.classList.contains('hidden')) return;
            if (e.key === 'Escape') closeLightbox();
            if (e.key === 'ArrowLeft') prevImage();
            if (e.key === 'ArrowRight') nextImage();
        });
    </script>
</x-admin-layout>
