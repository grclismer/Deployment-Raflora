@props(['active' => null, 'title' => null])

<header class="sticky top-0 z-40 bg-white/95 shadow-md ring-1 ring-slate-200/70 backdrop-blur-sm relative">
    <!-- Main nav bar container -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3 sm:py-4 flex justify-between items-center gap-3">
        <!-- Left: Logo & Links -->
        <div class="flex min-w-0 items-center gap-6 sm:gap-8">
            <a href="{{ route('home') }}" class="flex min-w-0 shrink items-center gap-1.5 sm:gap-3 group" aria-label="Raflora home">
                <img src="{{ asset('assets/images/raflora_flower_emblem_transparent.png') }}" alt="Raflora Emblem" class="h-9 sm:h-10 md:h-12 w-auto shrink-0 object-contain group-hover:rotate-12 transition-transform duration-300">
                <img src="{{ asset('assets/images/raflora_text_wordmark_transparent.png') }}" alt="Raflora Wordmark" class="h-6 sm:h-8 md:h-9 w-auto min-w-0 object-contain mt-1 max-[359px]:hidden">
            </a>

            {{-- Desktop navigation: always hidden on small screens, visible on md+ --}}
            <div class="hidden md:flex items-center gap-6 text-sm">
                @if(Route::currentRouteName() === 'home')
                    <a href="#home-hero" data-target="home-hero" class="scrollspy-link hover:text-[#1E7E34] hover:border-[#1E7E34] font-semibold text-[#1E7E34] border-b-2 border-[#1E7E34] pb-1 transition-all duration-200">HOME</a>
                    <a href="#gallery" data-target="gallery" class="scrollspy-link hover:text-[#1E7E34] hover:border-[#1E7E34] font-semibold text-slate-700 border-b-2 border-transparent pb-1 transition-all duration-200">GALLERY</a>
                    <a href="#packages-preview" data-target="packages-preview" class="scrollspy-link hover:text-[#1E7E34] hover:border-[#1E7E34] font-semibold text-slate-700 border-b-2 border-transparent pb-1 transition-all duration-200">PACKAGES</a>
                    <a href="#about" data-target="about" class="scrollspy-link hover:text-[#1E7E34] hover:border-[#1E7E34] font-semibold text-slate-700 border-b-2 border-transparent pb-1 transition-all duration-200">ABOUT</a>
                @else
                    <a href="{{ route('home') }}" class="hover:text-[#1E7E34] hover:border-[#1E7E34] font-semibold text-slate-700 border-b-2 border-transparent pb-1 transition-all duration-200">HOME</a>
                    <a href="{{ route('gallery') }}" class="hover:text-[#1E7E34] hover:border-[#1E7E34] font-semibold {{ Route::currentRouteName() === 'gallery' ? 'text-[#1E7E34] border-[#1E7E34]' : 'text-slate-700 border-transparent' }} border-b-2 pb-1 transition-all duration-200">GALLERY</a>
                    <a href="{{ route('packages.index') }}" class="hover:text-[#1E7E34] hover:border-[#1E7E34] font-semibold {{ Route::currentRouteName() === 'packages.index' ? 'text-[#1E7E34] border-[#1E7E34]' : 'text-slate-700 border-transparent' }} border-b-2 pb-1 transition-all duration-200">PACKAGES</a>
                    <a href="{{ route('about') }}" class="hover:text-[#1E7E34] hover:border-[#1E7E34] font-semibold {{ Route::currentRouteName() === 'about' ? 'text-[#1E7E34] border-[#1E7E34]' : 'text-slate-700 border-transparent' }} border-b-2 pb-1 transition-all duration-200">ABOUT</a>
                @endif
                @guest
                    <a href="{{ route('booking.start') }}" class="hover:text-[#1E7E34] hover:border-[#1E7E34] font-semibold {{ in_array(Route::currentRouteName(), ['booking.start', 'guest.booking.create']) ? 'text-[#1E7E34] border-[#1E7E34]' : 'text-slate-700 border-transparent' }} border-b-2 pb-1 transition-all duration-200">BOOKING</a>
                @else
                    @if(auth()->user()->role === 'admin')
                        <a href="{{ route('admin.dashboard') }}" class="hover:text-[#1E7E34] hover:border-[#1E7E34] font-semibold {{ str_starts_with(Route::currentRouteName() ?? '', 'admin.') ? 'text-[#1E7E34] border-[#1E7E34]' : 'text-slate-700 border-transparent' }} border-b-2 pb-1 transition-all duration-200">ADMIN DASHBOARD</a>
                    @elseif(auth()->user()->role === 'staff')
                        <a href="{{ route('staff.dashboard') }}" class="hover:text-[#1E7E34] hover:border-[#1E7E34] font-semibold {{ str_starts_with(Route::currentRouteName() ?? '', 'staff.') ? 'text-[#1E7E34] border-[#1E7E34]' : 'text-slate-700 border-transparent' }} border-b-2 pb-1 transition-all duration-200">STAFF DASHBOARD</a>
                    @else
                        <a href="{{ route('booking.start') }}" class="hover:text-[#1E7E34] hover:border-[#1E7E34] font-semibold {{ in_array(Route::currentRouteName(), ['booking.start', 'bookings.create']) ? 'text-[#1E7E34] border-[#1E7E34]' : 'text-slate-700 border-transparent' }} border-b-2 pb-1 transition-all duration-200">BOOK EVENT</a>
                        <a href="{{ route('bookings') }}" class="hover:text-[#1E7E34] hover:border-[#1E7E34] font-semibold {{ in_array(Route::currentRouteName(), ['bookings', 'bookings.show', 'bookings.analysis']) ? 'text-[#1E7E34] border-[#1E7E34]' : 'text-slate-700 border-transparent' }} border-b-2 pb-1 transition-all duration-200">MY BOOKINGS</a>
                    @endif
                @endguest
            </div>
        </div>

        <!-- Right: User/Profile Controls -->
        <div class="flex shrink-0 items-center gap-2 sm:gap-4 text-sm">
            @if(auth()->check() || session('dev_user'))
                <div class="hidden sm:flex flex-col items-end mr-2">
                    <p class="text-sm font-semibold text-slate-900 leading-tight">{{ auth()->user()->name ?? 'Raflora Client' }}</p>
                </div>
                <x-avatar-dropdown />
            @else
                <div class="hidden md:flex items-center gap-4">
                    <a href="{{ route('login') }}" class="font-semibold text-[#1E7E34] border border-[#1E7E34] rounded-full px-5 py-2 hover:bg-[#1E7E34]/10 hover:-translate-y-0.5 hover:shadow-sm transition-all duration-300">Log In</a>
                </div>
            @endif

            <!-- Mobile Menu Button: only visible on mobile (below md) via JS-checked viewport -->
            <button id="mobileMenuToggle" type="button"
                class="inline-flex items-center justify-center p-2 rounded-md text-slate-600 hover:text-slate-900 hover:bg-slate-100 hover:scale-105 active:scale-95 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-[#1E7E34]"
                aria-expanded="false"
                aria-controls="mobileMenuPanel"
                style="display:none"
            >
                <span class="sr-only">Open main menu</span>
                <svg id="mobileMenuIconClosed" class="block h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
                <svg id="mobileMenuIconOpen" class="hidden h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </div>

    <!-- Mobile Menu Panel: controlled entirely by JavaScript, never shown on desktop -->
    <div id="mobileMenuPanel" aria-label="Mobile navigation" style="display:none" class="border-t border-slate-200 bg-white absolute w-full left-0 shadow-md z-50">
        <div class="px-2 pt-2 pb-3 space-y-1 sm:px-3">
            @if(Route::currentRouteName() === 'home')
                <a href="#home-hero" data-target="home-hero" class="mobile-scrollspy-link block px-3 py-2 rounded-md text-base font-semibold text-[#1E7E34] bg-[#1E7E34]/10 transition-colors">HOME</a>
                <a href="#gallery" data-target="gallery" class="mobile-nav-link mobile-scrollspy-link block px-3 py-2 rounded-md text-base font-semibold text-slate-700 hover:text-slate-900 hover:bg-slate-50 transition-colors">GALLERY</a>
                <a href="#packages-preview" data-target="packages-preview" class="mobile-nav-link mobile-scrollspy-link block px-3 py-2 rounded-md text-base font-semibold text-slate-700 hover:text-slate-900 hover:bg-slate-50 transition-colors">PACKAGES</a>
                <a href="#about" data-target="about" class="mobile-nav-link mobile-scrollspy-link block px-3 py-2 rounded-md text-base font-semibold text-slate-700 hover:text-slate-900 hover:bg-slate-50 transition-colors">ABOUT</a>
            @else
                <a href="{{ route('home') }}" class="block px-3 py-2 rounded-md text-base font-semibold text-slate-700 hover:text-slate-900 hover:bg-slate-50 transition-colors">HOME</a>
                <a href="{{ route('gallery') }}" class="mobile-nav-link block px-3 py-2 rounded-md text-base font-semibold {{ Route::currentRouteName() === 'gallery' ? 'text-[#1E7E34] bg-[#1E7E34]/10' : 'text-slate-700 hover:text-slate-900 hover:bg-slate-50' }} transition-colors">GALLERY</a>
                <a href="{{ route('packages.index') }}" class="mobile-nav-link block px-3 py-2 rounded-md text-base font-semibold {{ Route::currentRouteName() === 'packages.index' ? 'text-[#1E7E34] bg-[#1E7E34]/10' : 'text-slate-700 hover:text-slate-900 hover:bg-slate-50' }} transition-colors">PACKAGES</a>
                <a href="{{ route('about') }}" class="mobile-nav-link block px-3 py-2 rounded-md text-base font-semibold {{ Route::currentRouteName() === 'about' ? 'text-[#1E7E34] bg-[#1E7E34]/10' : 'text-slate-700 hover:text-slate-900 hover:bg-slate-50' }} transition-colors">ABOUT</a>
            @endif
            @guest
                <a href="{{ route('booking.start') }}" class="block px-3 py-2 rounded-md text-base font-semibold {{ Route::currentRouteName() === 'booking.start' ? 'text-[#1E7E34] bg-[#1E7E34]/10' : 'text-slate-700 hover:text-slate-900 hover:bg-slate-50' }} transition-colors">BOOKING</a>
                <a href="{{ route('login') }}" class="block px-3 py-2 rounded-md text-base font-semibold text-[#1E7E34] border border-[#1E7E34] mt-2 hover:bg-[#1E7E34]/10 transition-colors text-center">Log In</a>
            @else
                @if(auth()->user()->role === 'admin')
                    <a href="{{ route('admin.dashboard') }}" class="block px-3 py-2 rounded-md text-base font-semibold {{ str_starts_with(Route::currentRouteName() ?? '', 'admin.') ? 'text-[#1E7E34] bg-[#1E7E34]/10' : 'text-slate-700 hover:text-slate-900 hover:bg-slate-50' }} transition-colors">ADMIN DASHBOARD</a>
                @elseif(auth()->user()->role === 'staff')
                    <a href="{{ route('staff.dashboard') }}" class="block px-3 py-2 rounded-md text-base font-semibold {{ str_starts_with(Route::currentRouteName() ?? '', 'staff.') ? 'text-[#1E7E34] bg-[#1E7E34]/10' : 'text-slate-700 hover:text-slate-900 hover:bg-slate-50' }} transition-colors">STAFF DASHBOARD</a>
                @else
                    <a href="{{ route('booking.start') }}" class="block px-3 py-2 rounded-md text-base font-semibold {{ in_array(Route::currentRouteName(), ['booking.start', 'bookings.create']) ? 'text-[#1E7E34] bg-[#1E7E34]/10' : 'text-slate-700 hover:text-slate-900 hover:bg-slate-50' }} transition-colors">BOOK EVENT</a>
                    <a href="{{ route('bookings') }}" class="block px-3 py-2 rounded-md text-base font-semibold {{ in_array(Route::currentRouteName(), ['bookings', 'bookings.show', 'bookings.analysis']) ? 'text-[#1E7E34] bg-[#1E7E34]/10' : 'text-slate-700 hover:text-slate-900 hover:bg-slate-50' }} transition-colors">MY BOOKINGS</a>
                    <a href="{{ route('client.dashboard') }}" class="block px-3 py-2 rounded-md text-base font-semibold {{ Route::currentRouteName() === 'client.dashboard' ? 'text-[#1E7E34] bg-[#1E7E34]/10' : 'text-slate-700 hover:text-slate-900 hover:bg-slate-50' }} transition-colors">DASHBOARD</a>
                @endif
            @endguest
        </div>
    </div>
</header>

<script>
    (function() {
        // md breakpoint: 768px
        var MD_BREAKPOINT = 768;

        var toggleBtn = document.getElementById('mobileMenuToggle');
        var panel = document.getElementById('mobileMenuPanel');
        var iconClosed = document.getElementById('mobileMenuIconClosed');
        var iconOpen = document.getElementById('mobileMenuIconOpen');

        function isMobile() {
            return window.innerWidth < MD_BREAKPOINT;
        }

        function syncButtonVisibility() {
            if (isMobile()) {
                toggleBtn.style.display = '';
            } else {
                // On desktop: hide button and force-close panel
                toggleBtn.style.display = 'none';
                closeMenu();
            }
        }

        function closeMenu() {
            panel.style.display = 'none';
            toggleBtn.setAttribute('aria-expanded', 'false');
            iconClosed.classList.remove('hidden');
            iconOpen.classList.add('hidden');
        }

        function openMenu() {
            if (!isMobile()) return; // Safety: never open on desktop
            panel.style.display = 'block';
            toggleBtn.setAttribute('aria-expanded', 'true');
            iconClosed.classList.add('hidden');
            iconOpen.classList.remove('hidden');
        }

        toggleBtn.addEventListener('click', function() {
            if (!isMobile()) return;
            var isOpen = panel.style.display === 'block';
            if (isOpen) {
                closeMenu();
            } else {
                openMenu();
            }
        });

        // Close mobile menu when clicking anchor/nav links
        document.querySelectorAll('.mobile-nav-link').forEach(function(link) {
            link.addEventListener('click', function() {
                closeMenu();
            });
        });

        // Sync on resize (e.g. rotating device or resizing browser window)
        window.addEventListener('resize', syncButtonVisibility);

        // Initial sync on page load
        syncButtonVisibility();
        
        // ScrollSpy implementation for home page
        var isHomePage = window.location.pathname === '/' || window.location.pathname === '/home';
        if (isHomePage && 'IntersectionObserver' in window) {
            var sections = document.querySelectorAll('section[id]');
            var desktopLinks = document.querySelectorAll('.scrollspy-link');
            var mobileLinks = document.querySelectorAll('.mobile-scrollspy-link');
            
            var observerOptions = {
                root: null,
                rootMargin: '-50% 0px -50% 0px',
                threshold: 0
            };

            var observer = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        var targetId = entry.target.id;
                        
                        // Update desktop links
                        desktopLinks.forEach(function(link) {
                            link.classList.remove('text-[#1E7E34]', 'border-[#1E7E34]');
                            link.classList.add('text-slate-700', 'border-transparent');
                            
                            if (link.getAttribute('data-target') === targetId) {
                                link.classList.remove('text-slate-700', 'border-transparent');
                                link.classList.add('text-[#1E7E34]', 'border-[#1E7E34]');
                            }
                        });
                        
                        // Update mobile links
                        mobileLinks.forEach(function(link) {
                            link.classList.remove('text-[#1E7E34]', 'bg-[#1E7E34]/10');
                            link.classList.add('text-slate-700');
                            
                            if (link.getAttribute('data-target') === targetId) {
                                link.classList.remove('text-slate-700');
                                link.classList.add('text-[#1E7E34]', 'bg-[#1E7E34]/10');
                            }
                        });
                    }
                });
            }, observerOptions);

            sections.forEach(function(section) {
                observer.observe(section);
            });
        }
    })();
</script>
