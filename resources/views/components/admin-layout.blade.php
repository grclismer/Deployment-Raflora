@props(['title' => 'Admin Dashboard', 'description' => null])

@php
    $unreadAlerts = \App\Models\AdminAlert::where('is_read', false)->count()
        + \App\Models\Booking::whereIn('status', ['payment_submitted', 'payment_pending'])->count();

    // Single source for the sidebar, its active state, and the header breadcrumb.
    $adminNav = [
        'Overview' => [
            ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'active' => ['admin.dashboard*'], 'icon' => 'fa-solid fa-gauge-high'],
        ],
        'Bookings' => [
            ['label' => 'Booking Management', 'route' => 'admin.bookings', 'active' => ['admin.bookings*'], 'icon' => 'fa-solid fa-calendar-days'],
            ['label' => 'Notifications', 'route' => 'admin.notifications', 'active' => ['admin.notifications*'], 'icon' => 'fa-solid fa-bell', 'badge' => $unreadAlerts],
        ],
        'Operations' => [
            ['label' => 'Gallery Management', 'route' => 'admin.gallery', 'active' => ['admin.gallery*'], 'icon' => 'fa-regular fa-image'],
            ['label' => 'Package Management', 'route' => 'admin.packages.index', 'active' => ['admin.packages*'], 'icon' => 'fa-solid fa-gift'],
            ['label' => 'Inventory Management', 'route' => 'admin.inventory.index', 'active' => ['admin.inventory*'], 'icon' => 'fa-solid fa-boxes-stacked'],
            ['label' => 'Return Tracking', 'route' => 'admin.return-tracking', 'active' => ['admin.return-tracking*'], 'icon' => 'fa-solid fa-truck-ramp-box'],
        ],
        'Clients' => [
            ['label' => 'Client Records', 'route' => 'admin.client-records', 'active' => ['admin.client-records*'], 'icon' => 'fa-solid fa-folder-open'],
        ],
        'Analytics' => [
            ['label' => 'Reports & Analytics', 'route' => 'admin.reports', 'active' => ['admin.reports*'], 'icon' => 'fa-solid fa-chart-pie'],
        ],
        'System' => [
            ['label' => 'Account Management', 'route' => 'admin.settings', 'active' => ['admin.settings*', 'admin.users*'], 'icon' => 'fa-solid fa-users-gear'],
        ],
    ];

    $currentSection = null;
    $currentItem = null;
    foreach ($adminNav as $section => $items) {
        foreach ($items as $item) {
            if (request()->routeIs(...$item['active'])) {
                [$currentSection, $currentItem] = [$section, $item];
                break 2;
            }
        }
    }
    // Child pages (booking review, add item, ...) link back to their module; module pages show their section.
    $isChildPage = $currentItem && ! request()->routeIs($currentItem['route']);

    $adminUser = auth()->user();
    $adminName = $adminUser->name ?? 'Raflora Admin';
    $adminInitials = strtoupper(substr($adminName ?: 'RA', 0, 2));
    // Hidden on the bookings list (it has its own search) and inside a single booking.
    // Reports & Analytics shows its Export button in this spot instead; the Dashboard has neither.
    $showHeaderSearch = ! request()->routeIs('admin.dashboard', 'admin.bookings', 'admin.bookings.show', 'admin.bookings.edit', 'admin.reports');
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} — Raflora Enterprises</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('rafloralogo.ico') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>
        (function() {
            try {
                if (localStorage.getItem('raflora_admin_sidebar_collapsed') === 'true' && window.innerWidth >= 1024) {
                    document.documentElement.classList.add('sidebar-collapsed');
                }
            } catch (e) {}
        })();
    </script>
</head>
<body class="rf-admin bg-slate-50 text-slate-700 antialiased">
    <div class="min-h-screen w-full bg-slate-50">

    <!-- Mobile Slide-Over Backdrop -->
    <div id="adminSidebarBackdrop" class="fixed inset-0 z-30 hidden bg-slate-950/50 backdrop-blur-[2px] transition-opacity duration-300 lg:hidden" aria-hidden="true" onclick="closeAdminSidebar()"></div>

    <!-- LEFT SIDEBAR: RESPONSIVE OFF-CANVAS / FIXED DESKTOP -->
    <aside
        id="adminSidebar"
        class="fixed inset-y-0 left-0 w-64 h-full flex-shrink-0 bg-navy-950 text-navy-100 z-40 -translate-x-full lg:translate-x-0 transition-all duration-300 ease-in-out flex flex-col justify-between overflow-hidden"
        aria-label="Admin sidebar"
    >
        <!-- Brand Header & Collapse Toggles -->
        <div class="rf-sidebar-header h-16 px-4 border-b border-white/10 flex items-center justify-between gap-3 shrink-0">
            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 min-w-0 group" title="Raflora Admin Dashboard">
                <span class="h-8 w-8 rounded-lg bg-white flex items-center justify-center shrink-0 shadow-sm">
                    <img src="{{ asset('assets/images/raflora_flower_emblem_transparent.png') }}" alt="Raflora Enterprises" class="h-5 w-6 object-contain">
                </span>
                <div class="rf-sidebar-brand-text min-w-0">
                    <span class="block text-base font-bold text-white tracking-tight font-serif leading-tight truncate">Raflora</span>
                    <span class="block text-[11px] font-medium text-navy-300 truncate">Floral Event Management</span>
                </div>
            </a>

            <div class="flex items-center gap-1 shrink-0">
                <!-- Desktop Collapse Button -->
                <button
                    type="button"
                    id="desktopSidebarToggleBtn"
                    onclick="toggleDesktopSidebar()"
                    class="hidden lg:flex items-center justify-center w-7 h-7 rounded-md border border-white/10 text-navy-300 hover:text-white hover:bg-white/10 transition cursor-pointer"
                    aria-label="Toggle sidebar collapse"
                    title="Toggle sidebar width"
                >
                    <i class="rf-collapse-btn-icon fa-solid fa-chevron-left text-[11px] transition-transform duration-200"></i>
                </button>

                <!-- Mobile Close Button -->
                <button
                    type="button"
                    onclick="closeAdminSidebar()"
                    class="lg:hidden p-2 rounded-md text-navy-300 hover:text-white hover:bg-white/10 transition cursor-pointer"
                    aria-label="Close navigation"
                >
                    <i class="fa-solid fa-xmark text-base"></i>
                </button>
            </div>
        </div>

        <!-- Scrolling Navigation Region -->
        <nav class="flex-1 overflow-y-auto overflow-x-hidden px-3 py-4 space-y-5 [scrollbar-width:thin] [scrollbar-color:rgba(255,255,255,0.15)_transparent]" aria-label="Admin navigation">
            @foreach($adminNav as $section => $items)
                <div class="rf-nav-section">
                    <p class="rf-sidebar-section-title text-[10.5px] font-semibold text-navy-400 uppercase tracking-[0.12em] px-3 mb-1.5">{{ $section }}</p>
                    <div class="space-y-0.5">
                        @foreach($items as $item)
                            @php $isActive = request()->routeIs(...$item['active']); @endphp
                            <a
                                href="{{ route($item['route']) }}"
                                class="rf-nav-item relative group {{ $isActive ? 'is-active' : '' }}"
                                title="{{ $item['label'] }}"
                                data-title="{{ $item['label'] }}"
                                @if($isActive) aria-current="page" @endif
                            >
                                @if(isset($item['badge']))
                                    <div class="relative w-5 text-center shrink-0 flex items-center justify-center">
                                        <i class="{{ $item['icon'] }} text-sm" aria-hidden="true"></i>
                                        @if($item['badge'] > 0)
                                            <span class="rf-nav-badge-dot hidden absolute -top-1 -right-1 w-2.5 h-2.5 bg-red-600 rounded-full ring-2 ring-navy-950"></span>
                                        @endif
                                    </div>
                                @else
                                    <i class="{{ $item['icon'] }} w-5 text-center text-sm shrink-0" aria-hidden="true"></i>
                                @endif
                                <span class="rf-sidebar-label text-sm truncate">{{ $item['label'] }}</span>
                                @if(isset($item['badge']) && $item['badge'] > 0)
                                    <span class="rf-nav-badge-pill ml-auto inline-flex min-w-6 items-center justify-center rounded-full bg-red-600 px-2 py-0.5 text-[11px] font-semibold text-white">{{ $item['badge'] > 99 ? '99+' : $item['badge'] }}</span>
                                @endif
                                {{-- Nav labels are static strings defined above, so they render unescaped like the original markup. --}}
                                <span class="rf-collapsed-tooltip">{!! $item['label'] !!}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </nav>

        <!-- Profile / Account Footer -->
        <div class="rf-sidebar-footer relative p-3 border-t border-white/10 shrink-0">
            <!-- Account Popover Menu -->
            <div
                id="adminUserDropdownMenu"
                style="display: none;"
                class="absolute bottom-full left-3 right-3 mb-2 bg-white rounded-xl shadow-xl ring-1 ring-slate-900/10 p-1.5 z-50 text-[13px] text-slate-700"
            >
                <div class="px-2.5 py-2 border-b border-slate-100 mb-1">
                    <p class="font-semibold text-slate-900 truncate">{{ $adminName }}</p>
                    <p class="text-xs text-slate-500 truncate">{{ $adminUser->email ?? 'admin@raflora.com' }}</p>
                </div>
                <a href="{{ route('admin.settings') }}" class="flex items-center gap-2.5 px-2.5 py-2 rounded-lg hover:bg-slate-50 hover:text-slate-900 font-medium transition">
                    <i class="fa-solid fa-gear w-4 text-center text-slate-400"></i>
                    <span>Account Settings</span>
                </a>
                <a href="{{ route('home') }}" class="flex items-center gap-2.5 px-2.5 py-2 rounded-lg hover:bg-slate-50 hover:text-slate-900 font-medium transition">
                    <i class="fa-solid fa-arrow-up-right-from-square w-4 text-center text-slate-400"></i>
                    <span>View Public Site</span>
                </a>
                <form method="POST" action="{{ route('logout') }}" class="w-full mt-1 pt-1 border-t border-slate-100">
                    @csrf
                    <button type="submit" class="w-full flex items-center gap-2.5 px-2.5 py-2 rounded-lg text-rose-600 hover:bg-rose-50 font-medium transition cursor-pointer text-left">
                        <i class="fa-solid fa-right-from-bracket w-4 text-center"></i>
                        <span>Log Out</span>
                    </button>
                </form>
            </div>

            <div class="flex items-center justify-between gap-2">
                <button
                    type="button"
                    onclick="toggleAdminUserMenu()"
                    class="flex items-center gap-2.5 min-w-0 flex-1 p-1.5 rounded-lg hover:bg-white/5 transition text-left cursor-pointer"
                    aria-haspopup="true"
                    aria-expanded="false"
                    id="adminUserMenuBtn"
                    title="{{ $adminName }} (Administrator)"
                >
                    <div class="relative shrink-0">
                        <div class="w-9 h-9 rounded-full bg-brand-500/20 text-brand-200 ring-1 ring-brand-400/30 flex items-center justify-center font-semibold text-xs select-none">
                            {{ $adminInitials }}
                        </div>
                        <span class="absolute bottom-0 right-0 w-2.5 h-2.5 rounded-full bg-brand-400 ring-2 ring-navy-950"></span>
                    </div>
                    <div class="rf-sidebar-footer-text min-w-0 flex-1">
                        <p class="text-[13px] font-semibold text-white truncate">{{ $adminName }}</p>
                        <p class="text-[11px] text-navy-300 truncate">Administrator</p>
                    </div>
                </button>

                <form method="POST" action="{{ route('logout') }}" class="rf-sidebar-footer-text shrink-0">
                    @csrf
                    <button
                        type="submit"
                        class="p-2 text-navy-300 hover:text-white hover:bg-white/10 rounded-lg transition cursor-pointer"
                        title="Log Out"
                        aria-label="Log Out"
                    >
                        <i class="fa-solid fa-right-from-bracket text-xs"></i>
                    </button>
                </form>
            </div>
        </div>
    </aside>

    <!-- RIGHT MAIN CONTAINER -->
    <div id="adminMainContainer" class="flex flex-col lg:pl-64 min-h-screen w-full transition-all duration-300 ease-in-out">

        <!-- Mobile Top Bar -->
        <div class="sticky top-0 z-30 bg-white/95 backdrop-blur px-4 h-14 border-b border-slate-200 flex items-center justify-between gap-3 lg:hidden">
            <button
                id="mobileAdminBrandToggle"
                type="button"
                class="flex items-center gap-3 min-w-0 rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 cursor-pointer"
                aria-controls="adminSidebar"
                aria-expanded="false"
                aria-label="Toggle admin navigation"
            >
                <span class="w-9 h-9 rounded-lg border border-slate-200 flex items-center justify-center text-slate-600 shrink-0">
                    <i class="fa-solid fa-bars text-sm"></i>
                </span>
                <img src="{{ asset('assets/images/raflora_flower_emblem_transparent.png') }}" alt="Raflora logo" class="h-6 w-7 object-contain shrink-0">
                <span id="mobileAdminPanelName" class="text-sm font-semibold text-slate-900 truncate max-w-[200px]">{{ $title }}</span>
            </button>
        </div>

        <!-- Desktop Page Header (single source of page context) -->
        <header class="hidden lg:block sticky top-0 z-20 bg-white/90 backdrop-blur border-b border-slate-200">
            <div class="mx-auto w-full max-w-[1600px] px-6 xl:px-8 min-h-[4.5rem] py-3 flex items-center justify-between gap-6">
                <div class="min-w-0">
                    @if($currentItem)
                        <nav class="flex items-center gap-1.5 text-xs font-medium text-slate-500 mb-0.5" aria-label="Breadcrumb">
                            @if($isChildPage)
                                <a href="{{ route($currentItem['route']) }}" class="hover:text-brand-700 transition">{{ $currentItem['label'] }}</a>
                                <i class="fa-solid fa-chevron-right text-[9px] text-slate-300" aria-hidden="true"></i>
                                <span class="text-slate-700 truncate" aria-current="page">{{ $title }}</span>
                            @else
                                <span class="text-[11px] font-semibold tracking-[0.12em] text-brand-700">{{ strtoupper($currentSection) }}</span>
                            @endif
                        </nav>
                    @endif
                    <h1 class="serif text-[1.375rem] leading-tight font-bold text-navy-900 tracking-tight truncate">{{ $title }}</h1>
                    @if(!empty($description))
                        <p class="text-[13px] text-slate-500 mt-0.5 truncate">{{ $description }}</p>
                    @endif
                </div>

                <div class="flex items-center gap-3 shrink-0">
                    @if($showHeaderSearch)
                        <form method="GET" action="{{ route('admin.bookings') }}" role="search" class="relative hidden xl:block">
                            <label for="adminGlobalSearch" class="sr-only">Search bookings</label>
                            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400 pointer-events-none" aria-hidden="true"></i>
                            <input
                                id="adminGlobalSearch"
                                type="search"
                                name="search"
                                placeholder="Search bookings…"
                                autocomplete="off"
                                class="w-72 h-9 pl-8 pr-9 rounded-lg border border-slate-200 bg-slate-50 text-sm text-slate-800 placeholder-slate-400 focus:bg-white focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none transition"
                            >
                            <kbd class="absolute right-2.5 top-1/2 -translate-y-1/2 text-[10px] font-semibold text-slate-400 border border-slate-200 rounded px-1.5 py-px bg-white pointer-events-none">/</kbd>
                        </form>
                    @endif
                    @isset($actions)
                        <div class="flex items-center gap-2">{{ $actions }}</div>
                    @endisset
                </div>
            </div>
        </header>

        <!-- Main Work Area -->
        <main class="flex-1 bg-slate-50 p-4 pb-12 sm:p-6 xl:px-8" aria-label="Admin workspace content">
            <div class="mx-auto w-full max-w-[1600px]">
                {{-- The actions slot renders here on mobile and in the header on desktop, so keep it to id-free links and buttons. --}}
                @isset($actions)
                    <div class="lg:hidden flex flex-wrap items-center gap-2 mb-4">{{ $actions }}</div>
                @endisset
                {{ $slot }}
            </div>
        </main>
    </div>

    <!-- UI Interaction Scripts -->
    <script>
        // Desktop Collapse Toggle
        function toggleDesktopSidebar() {
            const isCurrentlyCollapsed = document.documentElement.classList.contains('sidebar-collapsed');
            if (isCurrentlyCollapsed) {
                document.documentElement.classList.remove('sidebar-collapsed');
                localStorage.setItem('raflora_admin_sidebar_collapsed', 'false');
            } else {
                document.documentElement.classList.add('sidebar-collapsed');
                localStorage.setItem('raflora_admin_sidebar_collapsed', 'true');
            }
            // Close popover menu if open
            closeAdminUserMenu();
        }

        // Mobile Slide-over Drawer Controls
        function toggleAdminSidebarState() {
            if (window.matchMedia('(min-width: 1024px)').matches) {
                return;
            }

            const sidebar = document.getElementById('adminSidebar');
            if (!sidebar) return;

            if (sidebar.classList.contains('-translate-x-full')) {
                openAdminSidebar();
            } else {
                closeAdminSidebar();
            }
        }

        function openAdminSidebar() {
            const sidebar = document.getElementById('adminSidebar');
            const backdrop = document.getElementById('adminSidebarBackdrop');
            const mobileToggle = document.getElementById('mobileAdminBrandToggle');

            if (!sidebar) return;

            sidebar.classList.remove('-translate-x-full');
            if (backdrop) backdrop.classList.remove('hidden');
            if (mobileToggle) mobileToggle.setAttribute('aria-expanded', 'true');
            document.body.style.overflow = 'hidden';
        }

        function closeAdminSidebar() {
            const sidebar = document.getElementById('adminSidebar');
            const backdrop = document.getElementById('adminSidebarBackdrop');
            const mobileToggle = document.getElementById('mobileAdminBrandToggle');

            if (!sidebar) return;

            sidebar.classList.add('-translate-x-full');
            if (backdrop) backdrop.classList.add('hidden');
            if (mobileToggle) mobileToggle.setAttribute('aria-expanded', 'false');
            document.body.style.overflow = '';
        }

        // Admin User Menu Popover Toggle
        function toggleAdminUserMenu() {
            const menu = document.getElementById('adminUserDropdownMenu');
            const btn = document.getElementById('adminUserMenuBtn');
            if (!menu) return;

            const isShown = menu.style.display === 'block';
            if (isShown) {
                closeAdminUserMenu();
            } else {
                menu.style.display = 'block';
                btn?.setAttribute('aria-expanded', 'true');
            }
        }

        function closeAdminUserMenu() {
            const menu = document.getElementById('adminUserDropdownMenu');
            const btn = document.getElementById('adminUserMenuBtn');
            if (!menu) return;

            menu.style.display = 'none';
            btn?.setAttribute('aria-expanded', 'false');
        }

        // Event listeners initialization
        document.addEventListener('DOMContentLoaded', function() {
            const mobileToggle = document.getElementById('mobileAdminBrandToggle');
            const navLinks = document.querySelectorAll('#adminSidebar nav a');

            if (mobileToggle) {
                mobileToggle.addEventListener('click', function(e) {
                    e.stopPropagation();
                    toggleAdminSidebarState();
                });
            }

            // Expose globally for backward compatibility
            window.toggleAdminSidebar = toggleAdminSidebarState;
            window.closeAdminSidebar = closeAdminSidebar;

            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape') {
                    closeAdminSidebar();
                    closeAdminUserMenu();
                }

                // "/" focuses the header booking search unless the admin is already typing somewhere.
                const search = document.getElementById('adminGlobalSearch');
                const target = event.target;
                const isTyping = target && (target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName));
                if (event.key === '/' && search && !isTyping && search.offsetParent !== null) {
                    event.preventDefault();
                    search.focus();
                }
            });

            // Click Outside Popover Listener
            document.addEventListener('click', function(e) {
                const userMenu = document.getElementById('adminUserDropdownMenu');
                const userBtn = document.getElementById('adminUserMenuBtn');
                if (userMenu && userMenu.style.display === 'block') {
                    if (!userMenu.contains(e.target) && !userBtn?.contains(e.target)) {
                        closeAdminUserMenu();
                    }
                }
            });

            // Close mobile menu when clicking nav links
            navLinks.forEach((link) => {
                link.addEventListener('click', () => {
                    if (window.matchMedia('(max-width: 1023px)').matches) {
                        closeAdminSidebar();
                    }
                });
            });
        });
    </script>

    {{-- Global Flash Messages Toast Container (Root Stacking Context) --}}
    <div id="rf-toast-container" class="fixed top-4 right-4 sm:top-6 sm:right-6 z-[99999] flex flex-col gap-3 pointer-events-none w-[calc(100vw-2rem)] sm:w-96 max-w-full" style="isolation: isolate;">
        @if(session('success'))
            <x-alert type="success">{{ session('success') }}</x-alert>
        @endif
        @if(session('error'))
            <x-alert type="danger">{{ session('error') }}</x-alert>
        @endif
        @if(session('warning'))
            <x-alert type="warning">{{ session('warning') }}</x-alert>
        @endif
        @if(session('info'))
            <x-alert type="info">{{ session('info') }}</x-alert>
        @endif
        @if($errors->any())
            <x-alert type="danger">
                <div class="font-semibold mb-1">Please fix the following errors:</div>
                <ul class="list-disc list-inside space-y-0.5 text-[13px]">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-alert>
        @endif
    </div>

    <x-confirm-modal />
    </div>
</body>
</html>
