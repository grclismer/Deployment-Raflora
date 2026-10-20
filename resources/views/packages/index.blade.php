<x-app-layout title="Packages">
    <x-navbar title="PACKAGES" />
    <div class="py-8 sm:py-16 bg-slate-50 min-h-screen overflow-x-hidden">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="mb-4">
                @php
                    $prevUrl = url()->previous();
                    $isInternal = Illuminate\Support\Str::startsWith($prevUrl, url('/'));
                    $backUrl = ($isInternal && $prevUrl !== url()->current()) ? $prevUrl : route('home');
                @endphp
                <a href="{{ $backUrl }}" class="inline-flex items-center text-sm font-semibold text-purple-700 hover:text-purple-800 transition">
                    <span aria-hidden="true" class="mr-1">&larr;</span> Back
                </a>
            </div>
            <div class="text-center mb-10">
                <h1 class="text-3xl sm:text-4xl lg:text-5xl font-bold text-slate-900 tracking-tight mb-4">Our Curated Packages</h1>
                <p class="text-base sm:text-lg text-slate-600 max-w-2xl mx-auto">Explore our pre-set floral packages designed for every occasion. Select a package to start your booking effortlessly.</p>
            </div>

            {{-- Compact Filter and Search Bar --}}
            <form id="packagesFilterForm" method="GET" action="{{ route('packages.index') }}" class="mb-8 bg-white px-4 py-3 rounded-2xl shadow-sm border border-slate-200">
                <div class="flex flex-wrap items-center gap-2 md:gap-3">
                    {{-- Search (Full width on mobile, auto on desktop) --}}
                    <div class="relative w-full md:w-auto md:flex-1">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        </div>
                        <input type="text" name="search" id="packageSearch" value="{{ request('search') }}"
                            placeholder="Search packages..."
                            autocomplete="off"
                            class="block w-full pl-9 pr-3 py-2 border border-slate-200 rounded-xl text-sm bg-slate-50 placeholder-slate-400 focus:outline-none focus:bg-white focus:ring-1 focus:ring-purple-500 focus:border-purple-500 transition">
                    </div>

                    {{-- Category Filter (Half width on mobile) --}}
                    <div class="min-w-0 w-[calc(50%-4px)] md:w-auto md:min-w-[140px]">
                        <label for="category" class="sr-only">Category</label>
                        <select id="category" name="category"
                            class="block w-full px-3 py-2 text-sm border border-slate-200 rounded-xl bg-slate-50 focus:outline-none focus:ring-1 focus:ring-purple-500 focus:border-purple-500 transition">
                            <option value="">All Categories</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat }}" {{ request('category') === $cat ? 'selected' : '' }}>{{ $cat }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Sort (Half width on mobile) --}}
                    <div class="min-w-0 flex-1 md:flex-none md:w-auto md:min-w-[160px]">
                        <label for="sort" class="sr-only">Sort by</label>
                        <select id="sort" name="sort"
                            class="block w-full px-3 py-2 text-sm border border-slate-200 rounded-xl bg-slate-50 focus:outline-none focus:ring-1 focus:ring-purple-500 focus:border-purple-500 transition">
                            <option value="price_asc" {{ request('sort', 'price_asc') === 'price_asc' ? 'selected' : '' }}>Price: Low to High</option>
                            <option value="price_desc" {{ request('sort') === 'price_desc' ? 'selected' : '' }}>Price: High to Low</option>
                            <option value="name_asc" {{ request('sort') === 'name_asc' ? 'selected' : '' }}>Name: A–Z</option>
                        </select>
                    </div>

                    {{-- Clear (Full width on mobile, auto on desktop) --}}
                    <div class="w-full sm:w-auto mt-2 sm:mt-0">
                        <button type="button" id="clearFiltersBtn"
                            class="inline-flex w-full items-center justify-center px-4 py-2 border border-slate-200 text-sm font-medium rounded-xl text-slate-600 bg-white hover:bg-slate-50 transition whitespace-nowrap">
                            Clear
                        </button>
                    </div>
                </div>
            </form>

            <div id="packagesContainer">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 sm:gap-4 md:gap-6" id="packagesGrid">
                @forelse($packages as $package)
                    @php
                        $coverImage = $package->primary_image_url;
                        $allImages = $package->all_image_urls;
                        $pkgData = [
                            'id'             => $package->id,
                            'title'          => $package->title,
                            'price'          => $package->price,
                            'category'       => $package->category ?? '',
                            'description'    => $package->description ?? '',
                            'included_items' => $package->included_items ?? [],
                            'image_url'      => $coverImage,
                            'images'         => $allImages,
                            'book_url'       => route('booking.start', ['package_id' => $package->id]),
                        ];
                    @endphp
                    <article class="min-w-0 bg-white rounded-2xl sm:rounded-3xl shadow-sm border border-slate-200 flex flex-col transition hover:shadow-lg">
                        @if($coverImage)
                            <img src="{{ $coverImage }}" alt="{{ $package->title }}" class="w-full h-32 sm:h-48 object-cover rounded-t-2xl sm:rounded-t-3xl" />
                        @else
                            <div class="w-full h-32 sm:h-48 bg-purple-100 flex items-center justify-center text-purple-400 rounded-t-2xl sm:rounded-t-3xl">
                                <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            </div>
                        @endif
                        <div class="p-3 sm:p-5 flex flex-col flex-grow items-center text-center">
                            @if($package->category)
                                <span class="text-[10px] font-bold tracking-wider uppercase text-purple-600 mb-1">{{ $package->category }}</span>
                            @endif
                            <h2 class="text-base sm:text-lg font-bold leading-snug text-slate-900 mb-1">{{ $package->title }}</h2>
                            <p class="text-lg sm:text-xl font-extrabold text-purple-700 mb-3 sm:mb-4">₱{{ number_format($package->price, 2) }}</p>

                            <div class="mt-auto w-full space-y-2">
                                <button type="button"
                                    class="view-package-btn block w-full bg-slate-100 hover:bg-slate-200 text-slate-700 text-center font-semibold px-2 py-2.5 rounded-xl transition shadow-sm text-xs sm:text-sm"
                                    data-package="{{ json_encode($pkgData) }}">
                                    View Details
                                </button>
                                <a href="{{ route('booking.start', ['package_id' => $package->id]) }}"
                                    class="block w-full bg-purple-700 hover:bg-purple-800 text-white text-center font-semibold px-2 py-2.5 rounded-xl leading-tight transition shadow-sm text-xs sm:text-sm">
                                    Book This Package
                                </a>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="col-span-full text-center py-12">
                        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-slate-100 mb-4">
                            <svg class="w-8 h-8 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                        </div>
                        <h3 class="text-lg font-medium text-slate-900">No Packages Found</h3>
                        <p class="mt-1 text-slate-500">Try adjusting your search or filters, or check back later.</p>
                        <div class="mt-6">
                            <a href="{{ route('booking.start') }}" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-purple-600 hover:bg-purple-700">
                                Create Custom Booking
                            </a>
                        </div>
                    </div>
                @endforelse
            </div>

            <div class="mt-8" id="paginationContainer">
                {{ $packages->appends(request()->query())->links('components.pagination') }}
            </div>
            </div>
        </div>
    </div>

    {{-- Shared Package Details Modal — lives at body level (outside overflow-hidden articles) --}}
    <div id="packageDetailModal" style="display:none" role="dialog" aria-modal="true" aria-labelledby="packageModalTitle"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4 overflow-y-auto">
        <div class="relative bg-white rounded-2xl w-full max-w-lg mx-auto p-0 shadow-2xl border border-slate-100 max-h-[90dvh] overflow-y-auto">
            {{-- Close --}}
            <button type="button" onclick="closePackageModal()" aria-label="Close"
                class="absolute top-4 right-4 z-10 text-slate-400 hover:text-slate-600 p-1 rounded-lg hover:bg-slate-100 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>

            {{-- Image --}}
            <div id="packageModalImage" class="overflow-hidden rounded-t-2xl bg-slate-100 h-52"></div>

            {{-- Multiple Image Thumbnails --}}
            <div id="packageModalThumbnailsWrap" class="px-6 pt-2 pb-1 bg-slate-50 border-b border-slate-100 hidden">
                <div id="packageModalThumbnails" class="flex gap-2 overflow-x-auto py-1 scrollbar-hide"></div>
            </div>

            <div class="p-6">
                {{-- Category tag --}}
                <p id="packageModalCategory" class="text-[10px] font-bold tracking-widest uppercase text-purple-600 mb-1"></p>

                {{-- Title & Price --}}
                <div class="flex items-start justify-between gap-4 mb-3">
                    <h3 id="packageModalTitle" class="text-xl font-bold text-slate-800 leading-snug"></h3>
                    <span id="packageModalPrice" class="text-lg font-extrabold text-purple-700 whitespace-nowrap"></span>
                </div>

                {{-- Description --}}
                <p id="packageModalDescription" class="text-sm text-slate-600 mb-4 leading-relaxed"></p>

                {{-- Inclusions --}}
                <div id="packageModalInclusionsWrap" class="border-t border-slate-100 pt-4 mb-6">
                    <h4 class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Package Inclusions</h4>
                    <ul id="packageModalInclusions" class="space-y-1.5 text-sm text-slate-700 max-h-48 overflow-y-auto pr-1"></ul>
                </div>

                {{-- Actions --}}
                <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-100">
                    <button type="button" onclick="closePackageModal()"
                        class="px-4 py-2.5 text-sm font-semibold text-slate-600 bg-slate-100 rounded-xl hover:bg-slate-200 transition-colors">
                        Close
                    </button>
                    <a id="packageModalBookBtn" href="#"
                        class="px-5 py-2.5 text-sm font-semibold text-white bg-purple-700 rounded-xl hover:bg-purple-800 shadow-md transition-all">
                        Book This Package
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // ─── Package Detail Modal ───────────────────────────────────────────────────
        function attachViewDetailsListeners() {
            document.querySelectorAll('.view-package-btn').forEach(function(btn) {
                // Remove old listeners to prevent duplicates if called multiple times
                var newBtn = btn.cloneNode(true);
                btn.parentNode.replaceChild(newBtn, btn);
                newBtn.addEventListener('click', function() {
                    try {
                        var pkg = JSON.parse(this.getAttribute('data-package'));
                        openPackageModal(pkg);
                    } catch (e) {
                        console.error('Error parsing package data', e);
                    }
                });
            });
        }
        
        // Initial attachment
        attachViewDetailsListeners();

        function openPackageModal(pkg) {
            var modal = document.getElementById('packageDetailModal');
            if (!modal) return;

            var images = Array.isArray(pkg.images) && pkg.images.length > 0 ? pkg.images : (pkg.image_url ? [pkg.image_url] : []);
            function renderMainImage(src) {
                var imgEl = document.getElementById('packageModalImage');
                if (imgEl) {
                    imgEl.innerHTML = src
                        ? '<img src="' + src + '" class="w-full h-52 object-cover" alt="' + (pkg.title || '') + '">'
                        : '<div class="w-full h-52 flex items-center justify-center text-slate-400"><svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg></div>';
                }
            }
            renderMainImage(images[0] || null);

            var thumbsWrap = document.getElementById('packageModalThumbnailsWrap');
            var thumbsEl = document.getElementById('packageModalThumbnails');
            if (thumbsWrap && thumbsEl) {
                if (images.length > 1) {
                    thumbsWrap.classList.remove('hidden');
                    thumbsEl.innerHTML = '';
                    images.forEach(function(imgSrc, idx) {
                        var thumbBtn = document.createElement('button');
                        thumbBtn.type = 'button';
                        thumbBtn.className = 'w-14 h-14 rounded-lg overflow-hidden border-2 shrink-0 transition-all ' + (idx === 0 ? 'border-purple-600 ring-1 ring-purple-600' : 'border-slate-200 opacity-60 hover:opacity-100');
                        thumbBtn.innerHTML = '<img src="' + imgSrc + '" class="w-full h-full object-cover" alt="">';
                        thumbBtn.addEventListener('click', function() {
                            renderMainImage(imgSrc);
                            thumbsEl.querySelectorAll('button').forEach(function(b, i) {
                                if (i === idx) {
                                    b.className = 'w-14 h-14 rounded-lg overflow-hidden border-2 shrink-0 transition-all border-purple-600 ring-1 ring-purple-600';
                                } else {
                                    b.className = 'w-14 h-14 rounded-lg overflow-hidden border-2 shrink-0 transition-all border-slate-200 opacity-60 hover:opacity-100';
                                }
                            });
                        });
                        thumbsEl.appendChild(thumbBtn);
                    });
                } else {
                    thumbsWrap.classList.add('hidden');
                    thumbsEl.innerHTML = '';
                }
            }

            // Category
            var catEl = document.getElementById('packageModalCategory');
            if (catEl) catEl.textContent = pkg.category || '';

            // Title
            var titleEl = document.getElementById('packageModalTitle');
            if (titleEl) titleEl.textContent = pkg.title || '';

            // Price
            var priceEl = document.getElementById('packageModalPrice');
            if (priceEl) {
                var price = parseFloat(pkg.price);
                priceEl.textContent = isNaN(price) ? '' : '₱' + price.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }

            // Description
            var descEl = document.getElementById('packageModalDescription');
            if (descEl) descEl.textContent = pkg.description || '';

            // Inclusions
            var listEl = document.getElementById('packageModalInclusions');
            var wrapEl = document.getElementById('packageModalInclusionsWrap');
            if (listEl) {
                listEl.innerHTML = '';
                var items = Array.isArray(pkg.included_items) ? pkg.included_items : [];
                if (items.length > 0) {
                    items.forEach(function(item) {
                        var li = document.createElement('li');
                        li.className = 'flex items-start gap-2';
                        li.innerHTML = '<span class="text-purple-500 font-bold mt-0.5">✓</span><span>' + item + '</span>';
                        listEl.appendChild(li);
                    });
                    if (wrapEl) wrapEl.style.display = '';
                } else {
                    if (wrapEl) wrapEl.style.display = 'none';
                }
            }

            // Book button
            var bookBtn = document.getElementById('packageModalBookBtn');
            if (bookBtn) bookBtn.href = pkg.book_url || '#';

            // Show modal
            modal.style.display = 'flex';
            document.body.classList.add('overflow-hidden');
        }

        function closePackageModal() {
            var modal = document.getElementById('packageDetailModal');
            if (modal) modal.style.display = 'none';
            document.body.classList.remove('overflow-hidden');
        }

        // Close on backdrop click
        document.getElementById('packageDetailModal').addEventListener('click', function(e) {
            if (e.target === this) closePackageModal();
        });

        // ─── Real-time debounced search ─────────────────────────────────────────────
        (function() {
            var searchInput   = document.getElementById('packageSearch');
            var categorySelect = document.getElementById('category');
            var sortSelect    = document.getElementById('sort');
            var form          = document.getElementById('packagesFilterForm');
            var clearBtn      = document.getElementById('clearFiltersBtn');
            var container     = document.getElementById('packagesContainer');
            var debounceTimer = null;
            var activeFetch   = null;

            function fetchResults() {
                var url = new URL(form.action);
                var params = new URLSearchParams(new FormData(form));
                url.search = params.toString();
                
                // Update URL for history
                window.history.replaceState({}, '', url);

                // Add loading indicator
                container.style.opacity = '0.5';

                if (activeFetch) {
                    activeFetch.abort();
                }
                const controller = new AbortController();
                activeFetch = controller;

                fetch(url, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    signal: controller.signal
                })
                .then(function(response) {
                    return response.text();
                })
                .then(function(html) {
                    var parser = new DOMParser();
                    var doc = parser.parseFromString(html, 'text/html');
                    var newContainer = doc.getElementById('packagesContainer');
                    if (newContainer) {
                        container.innerHTML = newContainer.innerHTML;
                        attachViewDetailsListeners();
                        attachPaginationListeners();
                    }
                    container.style.opacity = '1';
                })
                .catch(function(error) {
                    if (error.name !== 'AbortError') {
                        container.style.opacity = '1';
                    }
                });
            }

            function scheduleFetch() {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(fetchResults, 300);
            }

            if (searchInput) {
                searchInput.addEventListener('input', scheduleFetch);
                // Prevent form submission on enter
                searchInput.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        fetchResults();
                    }
                });
            }

            if (categorySelect) categorySelect.addEventListener('change', fetchResults);
            if (sortSelect) sortSelect.addEventListener('change', fetchResults);

            if (clearBtn) {
                clearBtn.addEventListener('click', function() {
                    if (searchInput) searchInput.value = '';
                    if (categorySelect) categorySelect.value = '';
                    if (sortSelect) sortSelect.value = 'price_asc';
                    fetchResults();
                });
            }

            function attachPaginationListeners() {
                document.querySelectorAll('#paginationContainer a').forEach(function(link) {
                    link.addEventListener('click', function(e) {
                        e.preventDefault();
                        var url = new URL(this.href);
                        
                        container.style.opacity = '0.5';
                        
                        if (activeFetch) activeFetch.abort();
                        const controller = new AbortController();
                        activeFetch = controller;

                        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, signal: controller.signal })
                        .then(res => res.text())
                        .then(html => {
                            var parser = new DOMParser();
                            var doc = parser.parseFromString(html, 'text/html');
                            var newContainer = doc.getElementById('packagesContainer');
                            if (newContainer) {
                                container.innerHTML = newContainer.innerHTML;
                                attachViewDetailsListeners();
                                attachPaginationListeners();
                            }
                            container.style.opacity = '1';
                            
                            // Scroll up
                            document.getElementById('packagesFilterForm').scrollIntoView({ behavior: 'smooth' });
                            
                            window.history.replaceState({}, '', url);
                        })
                        .catch(err => {
                            if (err.name !== 'AbortError') container.style.opacity = '1';
                        });
                    });
                });
            }
            
            attachPaginationListeners();
        })();
    </script>
</x-app-layout>
