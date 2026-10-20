<x-admin-layout title="Galleries">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-xl sm:text-2xl font-bold text-gray-800 font-serif">Galleries</h2>
            <p class="text-sm text-gray-500 mt-1">Manage galleries displayed on the guest gallery page.</p>
        </div>
        <div>
            <a href="{{ route('admin.gallery.archived') }}" class="inline-flex items-center text-sm font-medium text-gray-500 hover:text-gray-700 transition">
                <i class="fa-solid fa-box-archive mr-2"></i> Archived Galleries
            </a>
        </div>
    </div>
    <!-- Tabs -->
    <div class="mb-6 border-b border-gray-200">
        <nav class="-mb-px flex space-x-8" aria-label="Tabs">
            <a href="{{ route('admin.gallery') }}" class="border-brand-600 text-brand-700 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                Active Galleries
            </a>
        </nav>
    </div>

    <!-- Search & Filter Toolbar -->
    <div class="bg-white rounded-2xl shadow-xs border border-gray-100 p-4 sm:p-5 mb-6 overflow-visible">
        <form method="GET" action="{{ route('admin.gallery') }}" id="filterForm">
            <input type="hidden" name="filter_expanded" id="galleryFilterExpandedInput" value="{{ request('filter_expanded', '0') }}">

            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <!-- Left: Search and Primary Controls -->
                <div class="flex flex-wrap items-center gap-2.5 sm:gap-3 flex-1">
                    <!-- Search Input -->
                    <div class="relative flex-1 min-w-[200px] sm:min-w-[260px] max-w-sm">
                        <span class="absolute inset-y-0 left-3 flex items-center pointer-events-none text-gray-400">
                            <i class="fa-solid fa-magnifying-glass text-xs"></i>
                        </span>
                        <input
                            type="text"
                            name="search"
                            value="{{ request('search') }}"
                            placeholder="Search event type, theme, or keyword..."
                            class="w-full pl-9 pr-3.5 py-2 bg-gray-50/70 border border-gray-200 rounded-xl text-sm focus:bg-white focus:ring-2 focus:ring-brand-500 focus:border-brand-600 transition shadow-2xs"
                        >
                    </div>

                    @php
                        $hasActiveGalleryFilters = request('event_type') || request('theme') || request('year') || (request('sort') && request('sort') !== 'latest');
                        $isGalleryFilterOpen = request('filter_expanded') === '1';
                    @endphp

                    <!-- Show Filters Button -->
                    <button
                        type="button"
                        id="galleryToggleFiltersBtn"
                        onclick="toggleGalleryFilterPanel()"
                        aria-expanded="{{ $isGalleryFilterOpen ? 'true' : 'false' }}"
                        aria-controls="galleryFilterPanel"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 border rounded-xl text-sm font-medium transition shadow-2xs focus:outline-none focus:ring-2 focus:ring-brand-500 cursor-pointer {{ $hasActiveGalleryFilters ? 'border-brand-300 bg-brand-50 text-brand-700 font-semibold' : 'border-gray-200 hover:border-brand-300 bg-white hover:bg-brand-50/50 text-gray-700 hover:text-brand-800' }}"
                    >
                        <i class="fa-solid fa-sliders text-xs {{ $hasActiveGalleryFilters ? 'text-brand-700' : 'text-gray-500' }}"></i>
                        <span id="galleryToggleFiltersText">{{ $isGalleryFilterOpen ? 'Hide Filters' : 'Show Filters' }}</span>
                        @if($hasActiveGalleryFilters)
                            <span class="w-1.5 h-1.5 rounded-full bg-brand-700 inline-block" title="Filters are active"></span>
                        @endif
                        <i id="galleryFiltersChevron" class="fa-solid fa-chevron-down text-[10px] transition-transform duration-200 {{ $isGalleryFilterOpen ? 'rotate-180' : '' }}"></i>
                    </button>

                    <!-- Search Button -->
                    <button
                        type="submit"
                        class="inline-flex items-center gap-1.5 px-4 py-2 bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold rounded-xl transition shadow-2xs focus:outline-none focus:ring-2 focus:ring-brand-500 cursor-pointer"
                    >
                        <i class="fa-solid fa-magnifying-glass text-xs"></i>
                        <span>Search</span>
                    </button>

                    @if(request('search'))
                        <a
                            href="{{ route('admin.gallery') }}"
                            class="px-3 py-2 text-xs font-semibold text-gray-500 hover:text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-xl transition"
                        >
                            Clear
                        </a>
                    @endif
                </div>

                <!-- Right: Action Controls -->
                <div class="flex items-center gap-2.5 shrink-0 self-end lg:self-center">
                    <a href="{{ route('admin.gallery.create') }}" class="btn-primary inline-flex items-center gap-2 whitespace-nowrap">
                        <i class="fa-solid fa-plus text-xs"></i>
                        <span>Add Gallery</span>
                    </a>
                </div>
            </div>

            <!-- Collapsible Filter Panel (Event Type, Theme, Year, Sort, Clear/Reset) -->
            <div
                id="galleryFilterPanel"
                style="{{ $isGalleryFilterOpen ? 'display: block;' : 'display: none;' }}"
                class="mt-4 pt-4 border-t border-gray-100"
            >
                <div class="bg-gray-50/80 p-3.5 sm:p-4 rounded-xl border border-gray-100 flex flex-wrap items-center gap-3 sm:gap-4">
                    <span class="text-xs font-bold text-gray-500 uppercase tracking-wider flex items-center gap-1.5 shrink-0">
                        <i class="fa-solid fa-filter text-brand-700 text-[11px]"></i> Filters & Sort:
                    </span>

                    <!-- Event Type -->
                    <div class="relative min-w-[150px]">
                        <select
                            name="event_type"
                            class="w-full py-2 px-3 bg-white border border-gray-200 rounded-xl text-sm font-medium text-gray-700 focus:ring-2 focus:ring-brand-500 focus:border-brand-600 shadow-2xs transition cursor-pointer"
                            onchange="document.getElementById('filterForm').submit()"
                        >
                            <option value="">All Event Types</option>
                            @foreach($eventTypes as $type)
                                <option value="{{ $type }}" {{ request('event_type') == $type ? 'selected' : '' }}>{{ $type }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Theme -->
                    <div class="relative min-w-[150px]">
                        <select
                            name="theme"
                            class="w-full py-2 px-3 bg-white border border-gray-200 rounded-xl text-sm font-medium text-gray-700 focus:ring-2 focus:ring-brand-500 focus:border-brand-600 shadow-2xs transition cursor-pointer"
                            onchange="document.getElementById('filterForm').submit()"
                        >
                            <option value="">All Themes</option>
                            @foreach($themes as $theme)
                                <option value="{{ $theme }}" {{ request('theme') == $theme ? 'selected' : '' }}>{{ $theme }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Year -->
                    <div class="relative min-w-[130px]">
                        <select
                            name="year"
                            class="w-full py-2 px-3 bg-white border border-gray-200 rounded-xl text-sm font-medium text-gray-700 focus:ring-2 focus:ring-brand-500 focus:border-brand-600 shadow-2xs transition cursor-pointer"
                            onchange="document.getElementById('filterForm').submit()"
                        >
                            <option value="">All Years</option>
                            @foreach($years as $year)
                                <option value="{{ $year }}" {{ request('year') == $year ? 'selected' : '' }}>{{ $year }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Sort -->
                    <div class="relative min-w-[140px]">
                        <select
                            name="sort"
                            class="w-full py-2 px-3 bg-white border border-gray-200 rounded-xl text-sm font-medium text-gray-700 focus:ring-2 focus:ring-brand-500 focus:border-brand-600 shadow-2xs transition cursor-pointer"
                            onchange="document.getElementById('filterForm').submit()"
                        >
                            <option value="latest" {{ request('sort', 'latest') == 'latest' ? 'selected' : '' }}>Latest First</option>
                            <option value="oldest" {{ request('sort') == 'oldest' ? 'selected' : '' }}>Oldest First</option>
                        </select>
                    </div>

                    <!-- Clear / Reset -->
                    <a
                        href="{{ route('admin.gallery') }}"
                        class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-gray-600 hover:text-gray-900 bg-white hover:bg-gray-100 border border-gray-200 rounded-xl transition shadow-2xs sm:ml-auto"
                    >
                        <i class="fa-solid fa-rotate-left text-[11px] text-gray-400"></i>
                        <span>Reset Filters</span>
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Gallery Grid -->
    @if($galleries->count() > 0)
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 md:gap-6 relative">
            @foreach($galleries as $gallery)
                @php
                    $firstImg = $gallery->images->first();
                    $coverImage = $firstImg 
                        ? (str_starts_with($firstImg->image_path, 'assets/') ? asset($firstImg->image_path) : asset('storage/' . $firstImg->image_path)) 
                        : asset('assets/images/background.jpg');
                    $imageCount = $gallery->images->count();
                @endphp
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 group hover:shadow-xl hover:shadow-brand-900/5 transition-all duration-300 overflow-hidden flex flex-col relative">
                    <div class="relative w-full overflow-hidden bg-slate-100" style="aspect-ratio: 3/2;" onclick="openLightbox('{{ $gallery->id }}')" role="button" tabindex="0">
                        <img src="{{ $coverImage }}" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700 ease-in-out cursor-pointer" alt="{{ $gallery->title }}">
                        <div class="absolute bottom-2 right-2 bg-slate-900/75 backdrop-blur-md text-white text-[10px] font-bold px-2 py-1 rounded-md flex items-center gap-1.5 pointer-events-none">
                            <i class="fa-solid fa-camera"></i> {{ $imageCount }}
                        </div>
                    </div>
                    <div class="p-4 sm:p-5 flex-1 flex flex-col justify-between">
                        <div>
                            <h3 class="font-bold text-[#1e293b] text-[15px] sm:text-base mb-1.5 line-clamp-1 group-hover:text-brand-800 transition-colors serif">{{ $gallery->title }}</h3>
                            <div class="flex items-center text-slate-500 text-[11px] sm:text-xs font-medium mb-3">
                                <i class="fa-regular fa-calendar mr-1.5"></i> {{ \Carbon\Carbon::parse($gallery->event_date)->format('F j, Y') }}
                            </div>
                            <div class="flex gap-1.5 flex-wrap mb-4">
                                <span class="px-2.5 py-1 bg-brand-100 text-brand-800 rounded-full text-[10px] font-semibold tracking-wide">{{ $gallery->event_type }}</span>
                                <span class="px-2.5 py-1 bg-brand-100 text-brand-800 rounded-full text-[10px] font-semibold tracking-wide">{{ $gallery->theme }}</span>
                            </div>
                        </div>
                        
                        <!-- Admin Actions -->
                        <div class="flex items-center justify-between pt-3 border-t border-slate-100">
                            <button onclick="openLightbox('{{ $gallery->id }}')" class="text-sm font-semibold text-brand-700 hover:text-brand-800 flex items-center gap-1.5 transition-colors">
                                <i class="fa-regular fa-eye"></i> View
                            </button>
                            <a href="{{ route('admin.gallery.edit', $gallery) }}" class="text-sm font-semibold text-blue-500 hover:text-blue-700 flex items-center gap-1.5 transition-colors">
                                <i class="fa-solid fa-pen"></i> Edit
                            </a>
                            <form action="{{ route('admin.gallery.destroy', $gallery) }}" method="POST" id="archive-form-{{ $gallery->id }}" class="inline-block">
                                @csrf
                                @method('DELETE')
                                <button type="button" onclick="openConfirmModal('archive-form-{{ $gallery->id }}', 'Archive Gallery?', 'Archived galleries will no longer appear on the Guest Gallery.', 'Archive Gallery', 'archive', this)" class="text-sm font-semibold text-orange-500 hover:text-orange-700 flex items-center gap-1.5 transition-colors">
                                    <i class="fa-solid fa-box-archive"></i> Archive
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        
        <!-- Pagination -->
        <div class="mt-8 flex justify-center">
            {{ $galleries->links() }}
        </div>
    @else
        <!-- Empty State -->
        <div class="text-center py-16 bg-white rounded-2xl shadow-sm border border-slate-100">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-slate-50 mb-4">
                <i class="fa-regular fa-image text-2xl text-slate-400"></i>
            </div>
            <h3 class="text-lg font-medium text-slate-900">No Galleries Found</h3>
            <p class="mt-1 text-sm text-slate-500 mb-6">There are no galleries matching your criteria.</p>
            <a href="{{ route('admin.gallery') }}" class="inline-flex items-center px-4 py-2 border border-slate-200 shadow-sm text-sm font-medium rounded-md text-slate-700 bg-white hover:bg-slate-50 transition">
                Clear Filters
            </a>
        </div>
    @endif

    <!-- Shared Lightbox JavaScript & Markup (Adapted from Guest Gallery) -->
    @php
        $lightboxData = $galleries->map(function($g) {
            return [
                'id' => (string) $g->id,
                'title' => $g->title,
                'date' => \Carbon\Carbon::parse($g->event_date)->format('F j, Y'),
                'tags' => [$g->event_type, $g->theme],
                'images' => $g->images->pluck('image_path')->map(fn($path) => str_starts_with($path, 'assets/') ? asset($path) : asset('storage/' . $path))->toArray()
            ];
        })->toArray();
    @endphp

    <script>
        function toggleGalleryFilterPanel() {
            const panel = document.getElementById('galleryFilterPanel');
            const btn = document.getElementById('galleryToggleFiltersBtn');
            const text = document.getElementById('galleryToggleFiltersText');
            const chevron = document.getElementById('galleryFiltersChevron');
            const input = document.getElementById('galleryFilterExpandedInput');
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

        const galleryData = @json($lightboxData);
        
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

            const item = galleryData.find(g => g.id === String(id));
            if (!item || !item.images || item.images.length === 0) return;

            currentGalleryId = id;
            currentImages = item.images;
            currentImageIndex = 0;
            
            lightboxTitle.textContent = item.title;
            if (lightboxDate) lightboxDate.textContent = item.date;
            if (lightboxEventType) lightboxEventType.textContent = item.tags[0] || 'Unspecified';
            if (lightboxTheme) lightboxTheme.textContent = item.tags[1] || 'General';
            
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
                currentGalleryId = null;
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

        document.addEventListener('keydown', (e) => {
            if (!lightbox || lightbox.classList.contains('hidden')) return;
            if (e.key === 'Escape') closeLightbox();
            if (e.key === 'ArrowLeft') prevImage();
            if (e.key === 'ArrowRight') nextImage();
        });
    </script>

    <!-- Lightbox Modal Markup (Identical to Guest Gallery) -->
    <div id="galleryLightbox" onclick="closeLightbox()" class="fixed inset-0 z-[100] hidden flex-col items-center justify-center bg-slate-900/80 backdrop-blur-[2px] opacity-0 transition-opacity duration-300 overflow-y-auto p-4 sm:p-6" style="padding: 1.5rem; justify-content: center; align-items: center;">
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-3xl flex flex-col relative overflow-hidden my-auto mx-auto" onclick="event.stopPropagation()" style="border-radius: 20px; max-width: 768px; margin-left: auto; margin-right: auto; margin-top: auto; margin-bottom: auto; transform: translateZ(0);">
            <button onclick="closeLightbox()" class="absolute top-4 right-4 sm:top-5 sm:right-5 z-20 w-12 h-12 rounded-full flex items-center justify-center bg-white text-slate-800 hover:bg-slate-100 shadow-md transition-transform hover:scale-105 border border-slate-100" style="border-radius: 9999px; width: 44px; height: 44px;">
                <i class="fa-solid fa-xmark text-xl"></i>
            </button>

            <div class="relative w-full bg-slate-100 flex justify-center items-center" style="width: 100%; height: 470px;">
                <img id="lightboxMainImg" src="" class="w-full h-full object-contain" alt="Gallery Image" style="width: 100%; height: 100%; object-fit: contain;">
                
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
</x-admin-layout>
