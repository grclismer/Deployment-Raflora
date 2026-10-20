<x-app-layout>
    <x-navbar title="HOME" />

    <main class="overflow-x-hidden">
        <!-- Hero Section -->
        <section id="home-hero" class="relative bg-cover bg-center min-h-[560px] md:min-h-[640px]" style="background-image: url('{{ asset("assets/images/background.jpg") }}');">
            <!-- Background Overlay: High contrast on mobile, elegant gradient on tablet/desktop -->
            <div class="absolute inset-0 bg-white/85 sm:bg-transparent sm:[background:linear-gradient(to_right,rgba(255,255,255,0.95)_0%,rgba(255,255,255,0.8)_40%,rgba(255,255,255,0)_100%)]"></div>
            <div class="max-w-6xl mx-auto relative z-10 min-h-[560px] md:min-h-[640px] flex items-center">
                <div class="w-full px-5 sm:px-8 md:px-0 md:w-2/3 py-14 md:py-0">
                    <div class="bg-white/80 sm:bg-transparent backdrop-blur-[2px] sm:backdrop-blur-none p-5 sm:p-0 rounded-2xl sm:rounded-none max-w-xl">
                        <h1 class="font-serif text-3xl sm:text-5xl md:text-6xl leading-tight font-extrabold break-words max-w-xl mb-4 text-[#0F2E58]">
                            Creating Beautiful <br class="hidden sm:block">& Inspiring Events
                        </h1>
                        <p class="text-slate-800 text-base sm:text-lg md:text-xl font-medium max-w-lg mb-8 leading-relaxed">
                            Elegant floral arrangements and memorable event experiences crafted for your special moments.
                        </p>
                        <div class="flex flex-col sm:flex-row flex-wrap gap-4">
                            <a href="{{ route('booking.start') }}" class="inline-flex items-center justify-center bg-[#1E7E34] text-white font-semibold px-6 sm:px-8 py-3 rounded-full shadow hover:bg-[#155b25] hover:-translate-y-1 hover:shadow-lg transition-all duration-300 text-base tracking-wide min-h-[48px] gap-2 group">
                                Book an Event
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 group-hover:translate-x-1 transition-transform duration-300"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" /></svg>
                            </a>
                            <a href="{{ route('packages.index') }}" class="inline-flex items-center justify-center bg-white border border-[#1E7E34] text-[#1E7E34] font-semibold px-6 sm:px-8 py-3 rounded-full shadow-sm hover:bg-[#1E7E34]/5 hover:-translate-y-1 hover:shadow-lg transition-all duration-300 text-base tracking-wide min-h-[48px]">
                                View Packages
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <svg class="absolute -bottom-1 left-0 w-full" viewBox="0 0 1440 120" xmlns="http://www.w3.org/2000/svg"><path fill="#fff" d="M0,64L60,53.3C120,43,240,21,360,10.7C480,0,600,0,720,10.7C840,21,960,43,1080,58.7C1200,75,1320,85,1380,90.7L1440,96L1440,120L1380,120C1320,120,1200,120,1080,120C960,120,840,120,720,120C600,120,480,120,360,120C240,120,120,120,60,120L0,120Z"></path></svg>
        </section>

        <!-- Event Gallery Section (Matches Navbar Order: HOME -> GALLERY -> PACKAGES -> ABOUT) -->
        @php
            $homeGalleryItems = [];
            $rawGalleries = isset($featuredGalleries) && $featuredGalleries->count() > 0 
                ? $featuredGalleries 
                : \App\Models\Gallery::where('is_archived', false)->with('images')->orderBy('event_date', 'desc')->take(5)->get();

            foreach ($rawGalleries as $g) {
                $imgList = $g->images->map(function($img) {
                    return str_starts_with($img->image_path, 'assets/') ? $img->image_path : 'storage/' . ltrim($img->image_path, '/');
                })->values()->toArray();

                $primaryImg = count($imgList) > 0 ? $imgList[0] : 'assets/images/background.jpg';

                $homeGalleryItems[] = [
                    'id' => (string) $g->id,
                    'title' => $g->title,
                    'date' => $g->event_date ? \Carbon\Carbon::parse($g->event_date)->format('F j, Y') : '',
                    'tags' => array_values(array_filter([$g->event_type, $g->theme])),
                    'photos' => count($imgList),
                    'image' => $primaryImg,
                    'images' => $imgList,
                ];
            }
        @endphp

        <section id="gallery" class="relative py-16 sm:py-24 px-4 sm:px-6" style="background-image: url('{{ asset("assets/images/background2.jpg") }}'); background-size: cover; background-position: center;">
            <div class="absolute inset-0 bg-black/70"></div>
            <div class="max-w-[1440px] mx-auto relative z-10">
                
                <!-- Headers -->
                <div class="flex items-center justify-center gap-4 mb-3 mt-4">
                    <div class="h-px w-10 bg-[#C2A359]"></div>
                    <span class="text-[#C2A359] font-bold text-xs tracking-[0.2em] uppercase">Our Work</span>
                    <div class="h-px w-10 bg-[#C2A359]"></div>
                </div>
                <h3 class="text-4xl md:text-5xl font-serif font-bold text-center mb-3 text-white">Event Gallery</h3>
                <p class="text-center text-white/90 mb-12 text-sm md:text-base font-light">A glimpse of the floral arrangements and events we've created.</p>

                <!-- Gallery Grid -->
                @if(count($homeGalleryItems) > 0)
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3 md:gap-4 px-2 xl:px-6">
                        @foreach(array_slice($homeGalleryItems, 0, 5) as $item)
                            <!-- Image Card -->
                            <div onclick="openLightbox('{{ $item['id'] }}')" class="group p-1 border-2 border-white/80 rounded-[16px] bg-white/10 backdrop-blur-sm shadow-2xl transform hover:-translate-y-2 transition duration-300 cursor-pointer {{ $loop->index == 4 ? 'hidden lg:block' : '' }}">
                                <div class="relative overflow-hidden rounded-[12px] h-48 sm:h-56 lg:h-44 xl:h-52">
                                    <img src="{{ asset($item['image']) }}" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110" alt="{{ $item['title'] }}">
                                    <div class="absolute inset-0 bg-black/0 group-hover:bg-black/50 transition duration-300 flex items-center justify-center">
                                        <span class="text-white text-sm sm:text-base font-semibold opacity-0 group-hover:opacity-100 transition px-2 text-center">{{ $item['title'] }}</span>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-12 bg-white/10 rounded-2xl max-w-md mx-auto backdrop-blur-sm border border-white/20">
                        <p class="text-white/90 text-sm font-medium">Event galleries showcase coming soon.</p>
                    </div>
                @endif
                
                <div class="text-center mt-12 relative z-10 mb-4 flex justify-center">
                    <a href="{{ route('gallery') }}" class="inline-flex items-center justify-center bg-white text-[#0F2E58] font-bold px-6 py-2.5 rounded-full shadow-xl hover:bg-gray-100 hover:-translate-y-1 transition-all duration-300 text-sm tracking-wide gap-3 group">
                        View Full Gallery
                        <i class="fa-solid fa-arrow-right-long text-[#1E7E34] group-hover:translate-x-1 transition-transform duration-300"></i>
                    </a>
                </div>
            </div>
        </section>

        <!-- Packages Preview Section -->
        <section id="packages-preview" class="relative py-16 sm:py-20 px-4 sm:px-6 bg-cover bg-center" style="background-image: url('{{ asset("assets/images/background.jpg") }}');">
            <div class="absolute inset-0 backdrop-blur-sm" style="background-color: rgba(0, 0, 0, 0.5);"></div>
            <div class="max-w-6xl mx-auto relative z-10">
                <h3 class="text-4xl sm:text-5xl font-serif font-bold text-center mb-4 text-white shadow-sm">Featured Packages</h3>
                <p class="text-center text-gray-200 text-lg sm:text-xl mb-12 font-medium tracking-wide">Curated floral packages for your special events.</p>
                
                @if(isset($featuredPackages) && $featuredPackages->count() > 0)
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 md:gap-8">
                        @foreach($featuredPackages as $package)
                            <article class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden flex flex-col transition-all duration-300 hover:-translate-y-2 hover:shadow-xl group">
                                @if($package->primary_image_url)
                                    <img src="{{ $package->primary_image_url }}" alt="{{ $package->title }}" class="w-full h-48 object-cover transition-transform duration-500 group-hover:scale-105" />
                                @else
                                    <div class="w-full h-48 bg-gray-100 flex items-center justify-center text-gray-400">
                                        <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                    </div>
                                @endif
                                <div class="p-6 flex flex-col flex-grow items-center text-center">
                                    <h2 class="text-lg font-bold mb-1" style="color: #0F2E58;">{{ $package->title }}</h2>
                                    <p class="text-xl font-extrabold text-[#D31C24] mb-4">₱{{ number_format($package->price, 2) }}</p>
                                    
                                    @php
                                        $pkgData = [
                                            'id' => $package->id,
                                            'title' => $package->title,
                                            'price' => $package->price,
                                            'description' => $package->description,
                                            'included_items' => $package->included_items ?? [],
                                            'image_url' => $package->primary_image_url,
                                            'all_images' => $package->all_image_urls,
                                            'category' => $package->category,
                                            'book_url' => route('booking.start', ['package_id' => $package->id])
                                        ];
                                    @endphp
                                    <div class="mt-auto w-full flex flex-row gap-3">
                                        <button type="button"
                                            class="view-package-btn flex-1 bg-white border border-slate-300 hover:bg-slate-50 hover:-translate-y-0.5 hover:shadow text-slate-700 text-center font-semibold py-2 rounded-lg transition-all duration-300 shadow-sm min-h-[44px]"
                                            data-package="{{ json_encode($pkgData) }}">
                                            View Details
                                        </button>
                                        <a href="{{ route('booking.start', ['package_id' => $package->id]) }}" class="flex-1 bg-[#1E7E34] hover:bg-[#155b25] hover:-translate-y-0.5 hover:shadow text-white text-center font-semibold py-2 rounded-lg transition-all duration-300 shadow-sm min-h-[44px] flex items-center justify-center">
                                            Book Now
                                        </a>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @else
                    <p class="text-center text-slate-300">More exciting packages coming soon.</p>
                @endif
                
                <div class="text-center mt-12">
                    <a href="{{ route('packages.index') }}" class="inline-flex items-center justify-center bg-white border border-[#1E7E34] text-[#1E7E34] font-semibold px-6 sm:px-8 py-2.5 rounded-full shadow-sm hover:bg-[#1E7E34]/5 hover:-translate-y-1 hover:shadow-lg transition-all duration-300 text-base tracking-wide gap-2 group">
                        View All Packages
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 group-hover:translate-x-1 transition-transform duration-300"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" /></svg>
                    </a>
                </div>
            </div>
        </section>

        <!-- About Section -->
        <section id="about" class="relative py-10 sm:py-14 px-4 sm:px-6 overflow-hidden" style="background-image: url('{{ asset("assets/images/background3.jpg") }}'); background-size: cover; background-position: center;">
            <!-- Decorative Floral Element -->
            <img src="{{ asset('assets/images/floral1.png') }}" class="absolute -top-10 -right-10 w-64 opacity-20 transform rotate-45 pointer-events-none" alt="" aria-hidden="true">
            <div class="relative z-10">
                <div class="grid md:grid-cols-2 gap-8 md:gap-12 items-center max-w-5xl mx-auto">
                    <div class="flex justify-center md:justify-end">
                        <img src="{{ asset('assets/images/about_us.jpg') }}" alt="flowers" class="w-72 h-72 sm:w-80 sm:h-80 md:w-96 md:h-96 rounded-3xl object-cover shadow-lg">
                    </div>
                    <div class="flex flex-col items-center md:items-start text-center md:text-left md:pr-8 lg:pr-16">
                        <h2 class="font-serif text-4xl md:text-5xl lg:text-6xl mb-6 font-bold text-[#0F2E58]">Raflora Enterprises</h2>
                        <p class="text-gray-800 leading-relaxed text-xl sm:text-2xl mb-8">We create beautiful floral designs and event setups tailored to each client's vision. Our team handles bookings, material sourcing, and on-site logistics.</p>
                        
                        <a href="{{ route('about') }}" class="inline-flex items-center justify-center bg-white border border-[#1E7E34] text-[#1E7E34] font-semibold px-6 sm:px-8 py-3 rounded-full shadow-sm hover:bg-[#1E7E34]/5 hover:-translate-y-1 hover:shadow-lg transition-all duration-300 text-lg tracking-wide gap-2 group">
                            View Full About
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 group-hover:translate-x-1 transition-transform duration-300"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" /></svg>
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- Footer / Contact Section -->
        <footer class="relative bg-white pt-10 lg:pt-16 pb-6 lg:pb-8" style="background-image: url('{{ asset('assets/images/footer-background.png') }}'); background-size: cover; background-position: center; background-repeat: no-repeat;">
            
            <!-- High-contrast overlay to soften dense floral areas behind content -->
            <div class="absolute inset-0 bg-white/90 sm:bg-white/85 backdrop-blur-[2px]"></div>

            <div class="max-w-[1150px] mx-auto px-8 sm:px-12 lg:px-16 xl:px-20 relative z-10">
                
                <!-- Main Footer Content Grid -->
                <div class="flex flex-wrap lg:flex-nowrap justify-between items-center w-full gap-3 xl:gap-4 pb-8 lg:pb-12">
                    
                    <!-- 1. Raflora branding -->
                    <div class="flex flex-col items-center lg:items-start text-center lg:text-left w-full lg:w-auto lg:max-w-[300px] flex-shrink-0 mb-8 lg:mb-0">
                        <a href="{{ route('home') }}" class="mb-4">
                            <img src="{{ asset('assets/images/raflora-logo-nobackground.png') }}" alt="Raflora Enterprises" class="h-20 lg:h-24 xl:h-28 w-auto object-contain">
                        </a>
                        <p class="text-sm text-slate-700 font-medium leading-relaxed max-w-[240px]">
                            Let's create something beautiful<br>for your special moments.
                        </p>
                    </div>

                    <!-- Divider -->
                    <div class="hidden lg:block w-px bg-slate-300 h-24 xl:h-28 flex-shrink-0"></div>

                    <!-- 2. Contact Us -->
                    <div class="flex flex-col items-center lg:items-start text-center lg:text-left w-1/2 sm:w-1/3 lg:w-auto mb-6 lg:mb-0">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center mb-3" style="background-color: #E8F3EA; color: #1E7E34;">
                            <i class="fa-solid fa-phone text-sm"></i>
                        </div>
                        <h4 class="font-serif font-bold text-base tracking-wide mb-2 text-[#0F2E58]">Contact Us</h4>
                        <p class="text-sm text-slate-700 font-medium">0919 008 9881<br>raflora18@gmail.com</p>
                    </div>

                    <!-- Divider -->
                    <div class="hidden lg:block w-px bg-slate-300 h-24 xl:h-28 flex-shrink-0"></div>

                    <!-- 3. Our Location -->
                    <div class="flex flex-col items-center lg:items-start text-center lg:text-left w-1/2 sm:w-1/3 lg:w-auto mb-6 lg:mb-0">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center mb-3" style="background-color: #E8F3EA; color: #1E7E34;">
                            <i class="fa-solid fa-location-dot text-sm"></i>
                        </div>
                        <h4 class="font-serif font-bold text-base tracking-wide mb-2 text-[#0F2E58]">Our Location</h4>
                        <p class="text-sm text-slate-700 font-medium">Corumi, Masambong,<br>Quezon City, Metro Manila</p>
                    </div>

                    <!-- Divider -->
                    <div class="hidden lg:block w-px bg-slate-300 h-24 xl:h-28 flex-shrink-0"></div>

                    <!-- 4. Business Hours -->
                    <div class="flex flex-col items-center lg:items-start text-center lg:text-left w-1/2 sm:w-1/3 lg:w-auto mb-6 lg:mb-0">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center mb-3" style="background-color: #E8F3EA; color: #1E7E34;">
                            <i class="fa-regular fa-clock text-sm"></i>
                        </div>
                        <h4 class="font-serif font-bold text-base tracking-wide mb-2 text-[#0F2E58]">Business Hours</h4>
                        <p class="text-sm text-slate-700 font-medium">Open 24/7<br>We're always ready<br>to help.</p>
                    </div>

                    <!-- Divider -->
                    <div class="hidden lg:block w-px bg-slate-300 h-24 xl:h-28 flex-shrink-0"></div>

                    <!-- 5. Quick Links -->
                    <div class="flex flex-col items-center lg:items-start text-center lg:text-left w-1/2 sm:w-auto lg:w-auto mb-6 lg:mb-0">
                        <h4 class="font-serif font-bold text-base tracking-wide mb-4 mt-2 lg:mt-0 text-[#0F2E58]">Quick Links</h4>
                        <div class="flex flex-col gap-2 text-sm text-slate-700 font-medium">
                            <div class="flex flex-wrap items-center justify-center lg:justify-start gap-2">
                                <a href="{{ route('home') }}" class="hover:text-[#1E7E34] transition-colors">Home</a>
                                <span class="text-slate-300 hidden xl:inline">|</span>
                                <a href="{{ route('gallery') }}" class="hover:text-[#1E7E34] transition-colors">Gallery</a>
                                <span class="text-slate-300 hidden xl:inline">|</span>
                                <a href="{{ route('packages.index') }}" class="hover:text-[#1E7E34] transition-colors">Packages</a>
                            </div>
                            <div class="flex flex-wrap items-center justify-center lg:justify-start gap-2">
                                <a href="{{ route('about') }}" class="hover:text-[#1E7E34] transition-colors">About</a>
                                <span class="text-slate-300 hidden xl:inline">|</span>
                                <a href="{{ route('booking.start') }}" class="hover:text-[#1E7E34] transition-colors">Booking</a>
                                <span class="text-slate-300 hidden xl:inline">|</span>
                                <a href="#" class="hover:text-[#1E7E34] transition-colors">Help Center</a>
                            </div>
                        </div>
                    </div>

                    <!-- Divider -->
                    <div class="hidden lg:block w-px bg-slate-300 h-24 xl:h-28 flex-shrink-0"></div>

                    <!-- 6. Follow Us -->
                    <div class="flex flex-col items-center lg:items-start text-center lg:text-left w-full sm:w-auto lg:w-auto flex-shrink-0">
                        <h4 class="font-serif font-bold text-base tracking-wide mb-4 mt-2 lg:mt-0 text-[#0F2E58]">Follow Us</h4>
                        <div class="flex gap-3">
                            <a href="#" class="w-10 h-10 rounded-full flex items-center justify-center transition-transform hover:scale-110" style="background-color: #E8F3EA; color: #1E7E34;"><i class="fa-brands fa-facebook-f text-sm"></i></a>
                            <a href="#" class="w-10 h-10 rounded-full flex items-center justify-center transition-transform hover:scale-110" style="background-color: #E8F3EA; color: #1E7E34;"><i class="fa-brands fa-instagram text-sm"></i></a>
                            <a href="#" class="w-10 h-10 rounded-full flex items-center justify-center transition-transform hover:scale-110" style="background-color: #E8F3EA; color: #1E7E34;"><i class="fa-brands fa-tiktok text-sm"></i></a>
                        </div>
                    </div>
                    
                </div>

                <!-- Bottom Bar (Copyright Row) -->
                <div class="pt-6 border-t border-slate-300 flex flex-col md:flex-row justify-between items-center gap-4 text-xs">
                    <div class="text-slate-600 font-semibold">
                        © 2026 Raflora Enterprises. All Rights Reserved.
                    </div>
                    <div class="flex flex-wrap justify-center items-center gap-4 font-bold tracking-widest text-xs text-[#0F2E58]">
                        <span>PEOPLE</span> <span class="text-slate-300">|</span> <span>FLOWERS</span> <span class="text-slate-300">|</span> <span>MEMORIES</span> <span class="text-slate-300">|</span> <span>ALWAYS</span>
                    </div>
                </div>

            </div>
        </footer>
    </main>

    {{-- Package Detail Modal --}}
    <div id="packageDetailModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm" style="display: none;">
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-2xl overflow-hidden flex flex-col max-h-[90vh]">
            <div class="relative">
                <div id="packageModalImage"></div>
                <button type="button" onclick="closePackageModal()" class="absolute top-4 right-4 w-9 h-9 flex items-center justify-center rounded-full bg-white/70 hover:bg-white text-slate-800 transition-all duration-300 shadow-sm">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
                <div class="absolute bottom-4 left-4">
                    <span id="packageModalCategory" class="px-3 py-1 bg-white/90 text-slate-800 text-xs font-bold uppercase tracking-wider rounded-lg shadow-sm"></span>
                </div>
            </div>

            <!-- Optional Thumbnails Row for packages with multiple images -->
            <div id="packageModalThumbnailsWrap" class="px-6 pt-3 pb-1 flex gap-2 overflow-x-auto hidden border-b border-slate-100">
                <div id="packageModalThumbnails" class="flex gap-2"></div>
            </div>
            
            <div class="p-6 md:p-8 overflow-y-auto flex-1">
                <div class="flex flex-col md:flex-row md:justify-between md:items-start gap-4 mb-6">
                    <div>
                        <h2 id="packageModalTitle" class="text-2xl font-bold text-[#0F2E58]"></h2>
                    </div>
                    <div id="packageModalPrice" class="text-2xl font-extrabold text-[#D31C24]"></div>
                </div>

                <div class="space-y-6">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 mb-2 uppercase tracking-wide">Description</h3>
                        <p id="packageModalDescription" class="text-slate-600 text-sm leading-relaxed whitespace-pre-wrap"></p>
                    </div>

                    <div id="packageModalInclusionsWrap">
                        <h3 class="text-sm font-bold text-slate-900 mb-3 uppercase tracking-wide">What's Included</h3>
                        <ul id="packageModalInclusions" class="space-y-2 text-sm text-slate-600">
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="p-6 bg-slate-50 border-t border-slate-100 flex gap-4 justify-end">
                <button type="button" onclick="closePackageModal()" class="px-6 py-2.5 rounded-lg font-semibold text-slate-600 border border-slate-300 hover:bg-slate-200 hover:-translate-y-0.5 hover:shadow-md transition-all duration-300">Close</button>
                <a href="#" id="packageModalBookBtn" class="px-6 py-2.5 rounded-lg font-semibold bg-[#1E7E34] text-white hover:bg-[#155b25] hover:-translate-y-0.5 hover:shadow-md transition-all duration-300 shadow-sm">Book This Package</a>
            </div>
        </div>
    </div>

    <!-- Gallery Lightbox Modal -->
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

            <!-- Content Container -->
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

    <script>
        // ─── Package Detail Modal ───────────────────────────────────────────────────
        function attachViewDetailsListeners() {
            document.querySelectorAll('.view-package-btn').forEach(function(btn) {
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
        
        attachViewDetailsListeners();

        function openPackageModal(pkg) {
            var modal = document.getElementById('packageDetailModal');
            if (!modal) return;

            var imgEl = document.getElementById('packageModalImage');
            if (imgEl) {
                imgEl.innerHTML = pkg.image_url
                    ? '<img id="packageModalMainImg" src="' + pkg.image_url + '" class="w-full h-56 object-cover" alt="' + (pkg.title || '') + '">'
                    : '<div class="w-full h-56 flex items-center justify-center text-slate-400 bg-slate-100"><svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg></div>';
            }

            // Multiple image thumbnails
            var thumbsWrap = document.getElementById('packageModalThumbnailsWrap');
            var thumbsEl = document.getElementById('packageModalThumbnails');
            if (thumbsWrap && thumbsEl) {
                thumbsEl.innerHTML = '';
                var images = Array.isArray(pkg.all_images) ? pkg.all_images : (pkg.image_url ? [pkg.image_url] : []);
                if (images.length > 1) {
                    images.forEach(function(imgUrl, idx) {
                        var thumbBtn = document.createElement('button');
                        thumbBtn.type = 'button';
                        thumbBtn.className = 'w-14 h-14 rounded-lg overflow-hidden border-2 transition-all ' + (idx === 0 ? 'border-[#1E7E34] opacity-100' : 'border-transparent opacity-60 hover:opacity-100');
                        thumbBtn.innerHTML = '<img src="' + imgUrl + '" class="w-full h-full object-cover" alt="thumb">';
                        thumbBtn.addEventListener('click', function() {
                            var mainImg = document.getElementById('packageModalMainImg');
                            if (mainImg) mainImg.src = imgUrl;
                            thumbsEl.querySelectorAll('button').forEach(function(b) {
                                b.classList.remove('border-[#1E7E34]', 'opacity-100');
                                b.classList.add('border-transparent', 'opacity-60');
                            });
                            thumbBtn.classList.add('border-[#1E7E34]', 'opacity-100');
                            thumbBtn.classList.remove('border-transparent', 'opacity-60');
                        });
                        thumbsEl.appendChild(thumbBtn);
                    });
                    thumbsWrap.classList.remove('hidden');
                } else {
                    thumbsWrap.classList.add('hidden');
                }
            }

            var catEl = document.getElementById('packageModalCategory');
            if (catEl) catEl.textContent = pkg.category || '';
            if (catEl && !pkg.category) catEl.parentElement.style.display = 'none';
            else if (catEl) catEl.parentElement.style.display = '';

            var titleEl = document.getElementById('packageModalTitle');
            if (titleEl) titleEl.textContent = pkg.title || '';

            var priceEl = document.getElementById('packageModalPrice');
            if (priceEl) {
                var price = parseFloat(pkg.price);
                priceEl.textContent = isNaN(price) ? '' : '₱' + price.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }

            var descEl = document.getElementById('packageModalDescription');
            if (descEl) descEl.textContent = pkg.description || '';

            var listEl = document.getElementById('packageModalInclusions');
            var wrapEl = document.getElementById('packageModalInclusionsWrap');
            if (listEl) {
                listEl.innerHTML = '';
                var items = Array.isArray(pkg.included_items) ? pkg.included_items : [];
                if (items.length > 0) {
                    items.forEach(function(item) {
                        var li = document.createElement('li');
                        li.className = 'flex items-start gap-2';
                        li.innerHTML = '<span class="text-[#1E7E34] font-bold mt-0.5">✓</span><span>' + item + '</span>';
                        listEl.appendChild(li);
                    });
                    if (wrapEl) wrapEl.style.display = '';
                } else {
                    if (wrapEl) wrapEl.style.display = 'none';
                }
            }

            var bookBtn = document.getElementById('packageModalBookBtn');
            if (bookBtn) bookBtn.href = pkg.book_url || '#';

            modal.style.display = 'flex';
            document.body.classList.add('overflow-hidden');
        }

        function closePackageModal() {
            var modal = document.getElementById('packageDetailModal');
            if (modal) modal.style.display = 'none';
            document.body.classList.remove('overflow-hidden');
        }

        const pkgModal = document.getElementById('packageDetailModal');
        if (pkgModal) {
            pkgModal.addEventListener('click', function(e) {
                if (e.target === this) closePackageModal();
            });
        }

        // ─── Gallery Lightbox Logic ─────────────────────────────────────────────────
        const assetBaseUrl = "{{ asset('') }}";
        const galleryData = @json($homeGalleryItems);

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
            if (lightboxDate) lightboxDate.textContent = item.date || '';
            if (lightboxEventType) lightboxEventType.textContent = (item.tags && item.tags[0]) ? item.tags[0] : 'Unspecified';
            if (lightboxTheme) lightboxTheme.textContent = (item.tags && item.tags[1]) ? item.tags[1] : 'General';
            
            // Render thumbnails
            lightboxThumbnails.innerHTML = '';
            currentImages.forEach((img, index) => {
                lightboxThumbnails.innerHTML += `
                    <button onclick="goToImage(${index})" class="flex-shrink-0 snap-center w-20 h-20 rounded-md overflow-hidden border-2 transition-all duration-300 ${index === 0 ? 'border-[#1E7E34] opacity-100 shadow-sm' : 'border-transparent opacity-50 hover:opacity-100'}" style="width: 80px; height: 80px; border-radius: 8px;">
                        <img src="${assetBaseUrl}${img}" class="w-full h-full object-cover" onerror="this.src='${assetBaseUrl}assets/images/background.jpg'" style="width: 100%; height: 100%; object-fit: cover;">
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
            if(lightbox) lightbox.classList.add('opacity-0');
            setTimeout(() => {
                if(lightbox) lightbox.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
                currentGalleryId = null;
            }, 300);
        }

        function updateLightboxView() {
            lightboxMainImg.src = assetBaseUrl + currentImages[currentImageIndex];
            lightboxCounter.textContent = `${currentImageIndex + 1} / ${currentImages.length}`;
            
            lightboxPrevBtn.classList.toggle('hidden', currentImages.length <= 1);
            lightboxNextBtn.classList.toggle('hidden', currentImages.length <= 1);
            
            Array.from(lightboxThumbnails.children).forEach((btn, index) => {
                if (index === currentImageIndex) {
                    btn.classList.remove('border-transparent', 'opacity-50');
                    btn.classList.add('border-[#1E7E34]', 'opacity-100', 'shadow-sm');
                    btn.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
                } else {
                    btn.classList.add('border-transparent', 'opacity-50');
                    btn.classList.remove('border-[#1E7E34]', 'opacity-100', 'shadow-sm');
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
</x-app-layout>
