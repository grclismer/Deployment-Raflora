<x-app-layout>
    <x-navbar title="GALLERY" />
    <main class="relative bg-slate-50/50 pb-24">
        @php
            $galleryRecords = [];
            try {
                $galleryRecords = \App\Models\Gallery::where('is_archived', false)->with('images')->orderBy('event_date', 'desc')->get()->map(function($gallery) {
                    $images = $gallery->images->pluck('image_path')->map(function($path) {
                        return str_starts_with($path, 'assets/') ? $path : 'storage/' . ltrim($path, '/');
                    })->toArray();
                    return [
                        'id' => (string) $gallery->id,
                        'title' => $gallery->title,
                        'date' => \Carbon\Carbon::parse($gallery->event_date)->format('F j, Y'),
                        'tags' => [$gallery->event_type, $gallery->theme],
                        'photos' => count($images),
                        'image' => count($images) > 0 ? $images[0] : 'assets/images/background.jpg',
                        'images' => $images,
                    ];
                })->toArray();
            } catch (\Exception $e) {
                // Ignore if tables don't exist yet during some edge cases
            }
        @endphp
        <!-- Hero Section -->
        <div class="relative w-full overflow-hidden bg-[#fdfbf9]">
            <!-- Background Image -->
            <div class="absolute inset-0">
                <img src="{{ asset('assets/images/background.jpg') }}" class="w-full h-full object-cover object-center" alt="Floral Background">
                <div class="absolute inset-0" style="background: linear-gradient(to right, rgba(255,255,255,0.95) 0%, rgba(255,255,255,0.8) 35%, rgba(255,255,255,0) 100%);"></div>
                {{-- On narrow screens the copy spans the full width, so keep it readable over the photo. --}}
                <div class="absolute inset-0 bg-white/75 lg:hidden"></div>
            </div>
            
            <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-20 pb-32">
                <div class="flex flex-col lg:flex-row items-center justify-between gap-12">
                    <!-- Left Text -->
                    <div class="max-w-2xl">
                        <p class="text-xs font-bold tracking-[0.2em] text-slate-500 uppercase mb-4">Our Gallery</p>
                        <h1 class="serif text-4xl sm:text-5xl md:text-[3.5rem] font-bold text-[#1e293b] leading-[1.1] mb-6">Beautiful Moments,<br>Made with Flowers</h1>
                        <p class="text-base sm:text-lg text-slate-600 max-w-lg leading-relaxed">Explore our collection of beautifully crafted floral designs and event setups from past celebrations. Get inspired for your special event.</p>
                    </div>
                    
                    <!-- Right Stats Card (Data-driven) -->
                    @if(count($galleryRecords) > 0)
                    <div class="hidden lg:flex bg-white/95 backdrop-blur-md rounded-2xl p-5 shadow-xl border border-white/60 items-center gap-4">
                        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-slate-50 text-slate-700 border border-slate-100">
                            <i class="fa-solid fa-camera text-lg"></i>
                        </div>
                        <div>
                            <div class="text-2xl font-extrabold text-slate-900">{{ count($galleryRecords) }}</div>
                            <div class="text-xs font-semibold text-slate-500">Event {{ count($galleryRecords) === 1 ? 'Gallery' : 'Galleries' }}</div>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-20 -mt-10">
            <div class="bg-white rounded-2xl shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-slate-100 p-3 sm:p-4 flex flex-col md:flex-row gap-3 md:items-center justify-between">
                <!-- Search Input -->
                <div class="flex-1 relative w-full md:w-auto">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </div>
                    <input type="text" id="gallerySearch" placeholder="Search event type, theme, or keyword..." class="w-full pl-9 pr-3 py-2 border border-slate-200 rounded-xl text-sm bg-slate-50 placeholder-slate-400 focus:outline-none focus:bg-white focus:ring-1 focus:ring-purple-500 focus:border-purple-500 transition">
                </div>
                
                <!-- Dropdowns -->
                <div class="flex flex-wrap items-center gap-2 sm:gap-3 w-full md:w-auto mt-2 md:mt-0">
                    <div class="w-[calc(50%-4px)] md:w-auto min-w-[130px]">
                        <select id="eventTypeFilter" class="block w-full px-3 py-2 text-sm border border-slate-200 rounded-xl bg-slate-50 focus:outline-none focus:ring-1 focus:ring-purple-500 focus:border-purple-500 transition font-semibold text-slate-700 cursor-pointer">
                            <option value="">All Event Types</option>
                            @php
                                $eventTypes = collect($galleryRecords)->pluck('tags.0')->unique()->sort()->values();
                            @endphp
                            @foreach($eventTypes as $type)
                                <option value="{{ $type }}">{{ $type }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="w-[calc(50%-4px)] md:w-auto min-w-[130px]">
                        <select id="themeFilter" class="block w-full px-3 py-2 text-sm border border-slate-200 rounded-xl bg-slate-50 focus:outline-none focus:ring-1 focus:ring-purple-500 focus:border-purple-500 transition font-semibold text-slate-700 cursor-pointer">
                            <option value="">All Themes</option>
                            @php
                                $themes = collect($galleryRecords)->pluck('tags.1')->unique()->sort()->values();
                            @endphp
                            @foreach($themes as $theme)
                                <option value="{{ $theme }}">{{ $theme }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="w-[calc(50%-4px)] md:w-auto min-w-[120px]">
                        <select id="yearFilter" class="block w-full px-3 py-2 text-sm border border-slate-200 rounded-xl bg-slate-50 focus:outline-none focus:ring-1 focus:ring-purple-500 focus:border-purple-500 transition font-semibold text-slate-700 cursor-pointer">
                            <option value="">All Years</option>
                            @php
                                $years = collect($galleryRecords)->pluck('date')->map(fn($d) => date('Y', strtotime($d)))->unique()->sortDesc()->values();
                            @endphp
                            @foreach($years as $year)
                                <option value="{{ $year }}">{{ $year }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="w-[calc(50%-4px)] md:w-auto min-w-[120px]">
                        <select id="sortFilter" class="block w-full px-3 py-2 text-sm border border-slate-200 rounded-xl bg-slate-50 focus:outline-none focus:ring-1 focus:ring-purple-500 focus:border-purple-500 transition font-semibold text-slate-700 cursor-pointer">
                            <option value="latest">Latest First</option>
                            <option value="oldest">Oldest First</option>
                        </select>
                    </div>
                    
                    <div class="w-full sm:w-auto mt-2 sm:mt-0">
                        <button type="button" id="clearGalleryFilters" class="inline-flex w-full items-center justify-center px-4 py-2 border border-slate-200 text-sm font-medium rounded-xl text-slate-600 bg-white hover:bg-slate-50 transition whitespace-nowrap">
                            Clear
                        </button>
                    </div>
                </div>
            </div>
        </div>



        <!-- Gallery Grid -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-16 relative">
            <div id="galleryLoading" class="hidden absolute inset-0 z-10 bg-slate-50/50 backdrop-blur-sm flex justify-center pt-20">
                <div class="inline-block h-8 w-8 animate-spin rounded-full border-4 border-solid border-purple-600 border-r-transparent align-[-0.125em] motion-reduce:animate-[spin_1.5s_linear_infinite]"></div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-6" id="galleryGrid">
                <!-- JS Populated Cards -->
            </div>

            <!-- Empty State -->
            <div id="galleryEmptyState" class="hidden col-span-full text-center py-12">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-slate-100 mb-4">
                    <svg class="w-8 h-8 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                </div>
                <h3 class="text-lg font-medium text-slate-900">No Galleries Found</h3>
                <p class="mt-1 text-slate-500">Try adjusting your search or filters.</p>
                <div class="mt-6">
                    <button type="button" onclick="document.getElementById('clearGalleryFilters').click()" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-purple-600 hover:bg-purple-700">
                        Clear Filters
                    </button>
                </div>
            </div>

            <!-- Pagination -->
            <div class="flex justify-center items-center gap-1.5 sm:gap-2 mt-16" id="galleryPagination">
                <!-- JS Populated Pagination -->
            </div>
        </div>
    </main>

    <script>
        const galleryData = @json($galleryRecords);
        const assetBaseUrl = "{{ asset('') }}";
        
        let filteredData = [...galleryData];
        let currentPage = 1;
        const itemsPerPage = 8;
        let debounceTimer = null;

        const searchInput = document.getElementById('gallerySearch');
        const eventTypeFilter = document.getElementById('eventTypeFilter');
        const themeFilter = document.getElementById('themeFilter');
        const yearFilter = document.getElementById('yearFilter');
        const sortFilter = document.getElementById('sortFilter');
        const clearBtn = document.getElementById('clearGalleryFilters');
        
        const grid = document.getElementById('galleryGrid');
        const pagination = document.getElementById('galleryPagination');
        const emptyState = document.getElementById('galleryEmptyState');
        const loadingState = document.getElementById('galleryLoading');

        function applyFilters() {
            // Show loading briefly to mimic Packages AJAX
            loadingState.classList.remove('hidden');
            grid.style.opacity = '0.5';

            setTimeout(() => {
                const search = searchInput.value.toLowerCase();
                const eventType = eventTypeFilter.value;
                const theme = themeFilter.value;
                const year = yearFilter.value;
                const sort = sortFilter.value;

                filteredData = galleryData.filter(item => {
                    // Search (case-insensitive partial)
                    const searchStr = `${item.title} ${item.tags.join(' ')}`.toLowerCase();
                    if (search && !searchStr.includes(search)) return false;
                    
                    // Event Type (First Tag)
                    if (eventType && item.tags[0] !== eventType) return false;
                    
                    // Theme (Second Tag)
                    if (theme && item.tags[1] !== theme) return false;
                    
                    // Year
                    const itemYear = new Date(item.date).getFullYear().toString();
                    if (year && itemYear !== year) return false;
                    
                    return true;
                });

                // Sort
                filteredData.sort((a, b) => {
                    const dateA = new Date(a.date);
                    const dateB = new Date(b.date);
                    return sort === 'latest' ? dateB - dateA : dateA - dateB;
                });

                currentPage = 1; // Reset page on filter
                renderGrid();
                renderPagination();
                
                loadingState.classList.add('hidden');
                grid.style.opacity = '1';
            }, 300); // 300ms debounce simulation
        }

        function scheduleFilters() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(applyFilters, 300);
        }

        function renderGrid() {
            grid.innerHTML = '';
            if (filteredData.length === 0) {
                grid.classList.add('hidden');
                emptyState.classList.remove('hidden');
                return;
            }

            grid.classList.remove('hidden');
            emptyState.classList.add('hidden');

            const start = (currentPage - 1) * itemsPerPage;
            const paginatedItems = filteredData.slice(start, start + itemsPerPage);

            paginatedItems.forEach(item => {
                let tagsHtml = item.tags.map(tag => `<span class="px-2.5 py-1 bg-[#f3e8ff] text-[#6b21a8] rounded-full text-[10px] font-semibold tracking-wide">${tag}</span>`).join('');
                
                grid.innerHTML += `
                <div onclick="openLightbox('${item.id}')" class="bg-white rounded-2xl shadow-sm border border-slate-100 group cursor-pointer hover:shadow-xl hover:shadow-purple-900/5 transition-all duration-300 overflow-hidden flex flex-col">
                    <div class="relative w-full overflow-hidden bg-slate-100" style="aspect-ratio: 3/2;">
                        <img src="${assetBaseUrl}${item.image}" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700 ease-in-out" alt="${item.title}">
                        <div class="absolute bottom-2 right-2 bg-slate-900/75 backdrop-blur-md text-white text-[10px] font-bold px-2 py-1 rounded-md flex items-center gap-1.5">
                            <i class="fa-solid fa-camera"></i> ${item.photos}
                        </div>
                    </div>
                    <div class="p-4 sm:p-5 flex-1 flex flex-col justify-between">
                        <h3 class="font-bold text-[#1e293b] text-[15px] sm:text-base mb-1.5 line-clamp-1 group-hover:text-purple-700 transition-colors serif">${item.title}</h3>
                        
                        <div class="flex justify-between items-end mt-1">
                            <div class="flex flex-col gap-2.5">
                                <div class="flex items-center text-slate-500 text-[11px] sm:text-xs font-medium">
                                    <i class="fa-regular fa-calendar mr-1.5"></i> ${item.date}
                                </div>
                                <div class="flex gap-1.5 flex-wrap">
                                    ${tagsHtml}
                                </div>
                            </div>
                            <button class="w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-[#f3e8ff] text-[#6b21a8] flex items-center justify-center group-hover:bg-[#6b21a8] group-hover:text-white transition-colors duration-300 shadow-sm shrink-0 mb-0.5">
                                <i class="fa-solid fa-chevron-right text-[10px]"></i>
                            </button>
                        </div>
                    </div>
                </div>`;
            });
        }

        function renderPagination() {
            pagination.innerHTML = '';
            const totalPages = Math.ceil(filteredData.length / itemsPerPage);
            if (totalPages <= 1) return;

            // Prev Button
            const prevDisabled = currentPage === 1;
            pagination.innerHTML += `
                <button onclick="goToPage(${currentPage - 1})" ${prevDisabled ? 'disabled' : ''} class="w-10 h-10 rounded-xl border border-slate-200 flex items-center justify-center ${prevDisabled ? 'text-slate-300 bg-slate-50 cursor-not-allowed' : 'text-slate-500 hover:bg-slate-50 hover:border-slate-300 bg-white'} transition-colors">
                    <i class="fa-solid fa-chevron-left text-xs"></i>
                </button>
            `;

            for (let i = 1; i <= totalPages; i++) {
                if (i === currentPage) {
                    pagination.innerHTML += `<button class="w-10 h-10 rounded-xl bg-purple-600 text-white font-bold flex items-center justify-center shadow-md shadow-purple-600/20">${i}</button>`;
                } else {
                    pagination.innerHTML += `<button onclick="goToPage(${i})" class="w-10 h-10 rounded-xl border border-transparent flex items-center justify-center text-slate-700 hover:bg-slate-100 font-semibold transition-colors">${i}</button>`;
                }
            }

            // Next Button
            const nextDisabled = currentPage === totalPages;
            pagination.innerHTML += `
                <button onclick="goToPage(${currentPage + 1})" ${nextDisabled ? 'disabled' : ''} class="w-10 h-10 rounded-xl border border-slate-200 flex items-center justify-center ${nextDisabled ? 'text-slate-300 bg-slate-50 cursor-not-allowed' : 'text-slate-500 hover:bg-slate-50 hover:border-slate-300 bg-white'} transition-colors">
                    <i class="fa-solid fa-chevron-right text-xs"></i>
                </button>
            `;
        }

        function goToPage(page) {
            const totalPages = Math.ceil(filteredData.length / itemsPerPage);
            if (page >= 1 && page <= totalPages) {
                currentPage = page;
                // Scroll to top of grid
                document.getElementById('galleryGrid').scrollIntoView({ behavior: 'smooth', block: 'start' });
                
                loadingState.classList.remove('hidden');
                grid.style.opacity = '0.5';
                
                setTimeout(() => {
                    renderGrid();
                    renderPagination();
                    loadingState.classList.add('hidden');
                    grid.style.opacity = '1';
                }, 200);
            }
        }

        // Attach events
        searchInput.addEventListener('input', scheduleFilters);
        searchInput.addEventListener('keydown', e => { if(e.key === 'Enter') e.preventDefault(); });
        eventTypeFilter.addEventListener('change', applyFilters);
        themeFilter.addEventListener('change', applyFilters);
        yearFilter.addEventListener('change', applyFilters);
        sortFilter.addEventListener('change', applyFilters);
        
        clearBtn.addEventListener('click', () => {
            searchInput.value = '';
            eventTypeFilter.value = '';
            themeFilter.value = '';
            yearFilter.value = '';
            sortFilter.value = 'latest';
            applyFilters();
        });

        // Initial render
        applyFilters();

        // --- Lightbox Logic ---
        let currentGalleryId = null;
        let currentImageIndex = 0;
        let currentImages = [];

        let lightbox, lightboxTitle, lightboxMainImg, lightboxCounter, lightboxThumbnails, lightboxPrevBtn, lightboxNextBtn, lightboxDate, lightboxEventType, lightboxTheme;

        function openLightbox(id) {
            if (!lightbox) {
                lightbox = document.getElementById('galleryLightbox');
                lightboxTitle = document.getElementById('lightboxTitle');
                lightboxMainImg = document.getElementById('lightboxMainImg');
                lightboxCounter = document.getElementById('lightboxCounter');
                lightboxThumbnails = document.getElementById('lightboxThumbnails');
                lightboxPrevBtn = document.getElementById('lightboxPrevBtn');
                lightboxNextBtn = document.getElementById('lightboxNextBtn');
                lightboxDate = document.getElementById('lightboxDate');
                lightboxEventType = document.getElementById('lightboxEventType');
                lightboxTheme = document.getElementById('lightboxTheme');
            }

            const item = galleryData.find(g => String(g.id) === String(id));
            if (!item || !item.images || item.images.length === 0) return;

            currentGalleryId = id;
            currentImages = item.images;
            currentImageIndex = 0;
            
            lightboxTitle.textContent = item.title;
            if (lightboxDate) lightboxDate.textContent = item.date;
            if (lightboxEventType) lightboxEventType.textContent = item.tags[0] || 'Unspecified';
            if (lightboxTheme) lightboxTheme.textContent = item.tags[1] || 'General';
            
            // Render thumbnails
            lightboxThumbnails.innerHTML = '';
            currentImages.forEach((img, index) => {
                lightboxThumbnails.innerHTML += `
                    <button onclick="goToImage(${index})" class="flex-shrink-0 snap-center w-20 h-20 rounded-md overflow-hidden border-2 transition-all duration-300 ${index === 0 ? 'border-green-600 opacity-100 shadow-sm' : 'border-transparent opacity-50 hover:opacity-100'}" style="width: 80px; height: 80px; border-radius: 8px;">
                        <img src="${assetBaseUrl}${img}" class="w-full h-full object-cover" onerror="this.src='${assetBaseUrl}assets/images/background.jpg'" style="width: 100%; height: 100%; object-fit: cover;">
                    </button>
                `;
            });

            updateLightboxView();
            
            lightbox.classList.remove('hidden');
            // Allow display block to render before opacity transition
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
                currentGalleryId = null;
            }, 300);
        }

        function updateLightboxView() {
            lightboxMainImg.src = assetBaseUrl + currentImages[currentImageIndex];
            lightboxCounter.textContent = `${currentImageIndex + 1} / ${currentImages.length}`;
            
            lightboxPrevBtn.classList.toggle('hidden', currentImages.length <= 1);
            lightboxNextBtn.classList.toggle('hidden', currentImages.length <= 1);
            
            // Update thumbnails active state
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

        // Keyboard navigation
        document.addEventListener('keydown', (e) => {
            if (!lightbox || lightbox.classList.contains('hidden')) return;
            if (e.key === 'Escape') closeLightbox();
            if (e.key === 'ArrowLeft') prevImage();
            if (e.key === 'ArrowRight') nextImage();
        });
    </script>
        </div>
        <!-- Lightbox Modal -->
        <div id="galleryLightbox" onclick="closeLightbox()" class="fixed inset-0 z-50 hidden flex-col items-center justify-center bg-slate-900/60 backdrop-blur-[2px] opacity-0 transition-opacity duration-300 overflow-y-auto p-4 sm:p-6" style="padding: 1.5rem; justify-content: center; align-items: center;">
            
            <div class="bg-white rounded-3xl shadow-2xl w-full max-w-3xl flex flex-col relative overflow-hidden my-auto mx-auto" onclick="event.stopPropagation()" style="border-radius: 20px; max-width: 768px; margin-left: auto; margin-right: auto; margin-top: auto; margin-bottom: auto; transform: translateZ(0);">
                
                <!-- Close Button -->
                <button onclick="closeLightbox()" class="absolute top-4 right-4 sm:top-5 sm:right-5 z-20 w-12 h-12 rounded-full flex items-center justify-center bg-white text-slate-800 hover:bg-slate-100 shadow-md transition-transform hover:scale-105 border border-slate-100" style="border-radius: 9999px; width: 44px; height: 44px;">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>

                <!-- Main Image Area (Edge-to-edge) -->
                <div class="relative w-full bg-slate-100 flex justify-center items-center" style="width: 100%; height: 470px;">
                    <img id="lightboxMainImg" src="" class="w-full h-full object-contain" alt="Gallery Image" style="width: 100%; height: 100%; object-fit: contain;">
                    
                    <!-- Navigation Buttons -->
                    <button onclick="prevImage(event)" id="lightboxPrevBtn" class="absolute left-3 sm:left-5 top-1/2 -translate-y-1/2 w-12 h-12 rounded-full flex items-center justify-center bg-white text-slate-800 hover:bg-slate-50 shadow-md transition-transform hover:scale-105 z-10 hidden border border-slate-100" style="border-radius: 9999px; width: 44px; height: 44px; transform: translateY(-50%); top: 50%;">
                        <i class="fa-solid fa-chevron-left text-base"></i>
                    </button>
                    <button onclick="nextImage(event)" id="lightboxNextBtn" class="absolute right-3 sm:right-5 top-1/2 -translate-y-1/2 w-12 h-12 rounded-full flex items-center justify-center bg-white text-slate-800 hover:bg-slate-50 shadow-md transition-transform hover:scale-105 z-10 hidden border border-slate-100" style="border-radius: 9999px; width: 44px; height: 44px; transform: translateY(-50%); top: 50%;">
                        <i class="fa-solid fa-chevron-right text-base"></i>
                    </button>
                </div>

                <!-- Content Container (Padding only here) -->
                <div class="flex flex-col p-6 sm:px-8 sm:pb-8 sm:pt-6 w-full" style="padding: 1.5rem 2rem 2rem 2rem; width: 100%;">
                    
                    <!-- Counter -->
                    <div class="text-center text-slate-800 font-medium text-sm sm:text-base mb-4">
                        <span id="lightboxCounter"></span>
                    </div>

                    <!-- Thumbnails -->
                    <div class="flex justify-center w-full mb-6 sm:mb-8" style="justify-content: center; width: 100%;">
                        <div id="lightboxThumbnails" class="flex gap-2 sm:gap-3 overflow-x-auto snap-x max-w-full px-1 scrollbar-hide" style="scrollbar-width: none; gap: 0.75rem;">
                        </div>
                    </div>

                    <!-- Event Information -->
                    <div class="w-full text-left" style="width: 100%; text-align: left;">
                        <h3 id="lightboxTitle" class="text-2xl sm:text-3xl font-bold text-slate-900 serif mb-2"></h3>
                        <div class="flex flex-wrap items-center gap-3 sm:gap-4 text-slate-600 text-sm sm:text-[15px] font-medium" style="display: flex; gap: 1rem;">
                            <div class="flex items-center gap-1.5" style="display: flex; gap: 0.375rem;">
                                <i class="fa-regular fa-calendar text-slate-400"></i>
                                <span id="lightboxDate"></span>
                            </div>
                            <span class="text-slate-300">|</span>
                            <div class="flex items-center gap-1.5" style="display: flex; gap: 0.375rem;">
                                <i class="fa-solid fa-tag text-slate-400"></i>
                                <span id="lightboxEventType"></span>
                            </div>
                            <span class="text-slate-300">|</span>
                            <div class="flex items-center gap-1.5" style="display: flex; gap: 0.375rem;">
                                <i class="fa-brands fa-pagelines text-slate-400"></i>
                                <span id="lightboxTheme"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</x-app-layout>
