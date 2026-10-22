@props(['title' => 'Staff Workspace', 'description' => null])

@php
    $staffUser = auth()->user();
    $staffUnread = app(\App\Services\StaffWorkspaceService::class)->unreadMessageCount($staffUser);
    $staffInitials = collect(preg_split('/\s+/', trim($staffUser->name ?? 'S')))->filter()->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('');
    $staffProfileImage = $staffUser->profile_image && \Storage::disk('public')->exists($staffUser->profile_image)
        ? asset('storage/' . $staffUser->profile_image)
        : null;

    // Single source for the sidebar matching the Admin sidebar layout & conventions
    $staffNav = [
        'Workspace' => [
            ['label' => 'Dashboard', 'route' => 'staff.dashboard', 'active' => ['staff.dashboard'], 'icon' => 'fa-solid fa-gauge-high'],
            ['label' => 'My Tasks', 'route' => 'staff.tasks', 'active' => ['staff.tasks'], 'icon' => 'fa-regular fa-square-check'],
            ['label' => 'Event Assignments', 'route' => 'staff.assignments', 'active' => ['staff.assignments', 'staff.events.*'], 'icon' => 'fa-regular fa-calendar-check'],
            ['label' => 'Work Orders', 'route' => 'staff.work-orders', 'active' => ['staff.work-orders'], 'icon' => 'fa-solid fa-box-archive'],
            ['label' => 'Inventory Requests', 'route' => 'staff.requests', 'active' => ['staff.requests*'], 'icon' => 'fa-solid fa-boxes-stacked'],
            ['label' => 'Dispatch', 'route' => 'staff.dispatch', 'active' => ['staff.dispatch'], 'icon' => 'fa-solid fa-truck-fast'],
            ['label' => 'Returns', 'route' => 'staff.returns', 'active' => ['staff.returns'], 'icon' => 'fa-solid fa-rotate-left'],
            ['label' => 'Event Checklist', 'route' => 'staff.checklist', 'active' => ['staff.checklist'], 'icon' => 'fa-solid fa-list-check'],
            ['label' => 'Messages', 'route' => 'staff.messages', 'active' => ['staff.messages'], 'icon' => 'fa-regular fa-comments', 'badge' => $staffUnread],
            ['label' => 'Activity Logs', 'route' => 'staff.activity', 'active' => ['staff.activity'], 'icon' => 'fa-solid fa-clock-rotate-left'],
        ],
        'Reference' => [
            ['label' => 'Event Calendar', 'route' => 'staff.calendar', 'active' => ['staff.calendar'], 'icon' => 'fa-regular fa-calendar-days'],
            ['label' => 'Resource Guide', 'route' => 'staff.guide', 'active' => ['staff.guide'], 'icon' => 'fa-regular fa-compass'],
        ],
    ];

    $currentSection = null;
    $currentItem = null;
    foreach ($staffNav as $section => $items) {
        foreach ($items as $item) {
            if (request()->routeIs(...$item['active'])) {
                [$currentSection, $currentItem] = [$section, $item];
                break 2;
            }
        }
    }
    $isChildPage = $currentItem && ! request()->routeIs($currentItem['route']);
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
<body class="rf-admin bg-slate-50 text-slate-700 antialiased font-sans">
    <div class="min-h-screen w-full bg-slate-50">

    <!-- Mobile Slide-Over Backdrop -->
    <div id="staffSidebarBackdrop" class="fixed inset-0 z-30 hidden bg-slate-950/50 backdrop-blur-[2px] transition-opacity duration-300 lg:hidden" aria-hidden="true" onclick="closeStaffSidebar()"></div>

    <!-- LEFT SIDEBAR: RESPONSIVE OFF-CANVAS / FIXED DESKTOP (LIKE ADMIN) -->
    <aside
        id="staffSidebar"
        class="fixed inset-y-0 left-0 w-64 h-full flex-shrink-0 bg-navy-950 text-navy-100 z-40 -translate-x-full lg:translate-x-0 transition-all duration-300 ease-in-out flex flex-col justify-between overflow-hidden"
        aria-label="Staff navigation sidebar"
    >
        <!-- Brand Header & Collapse Toggles -->
        <div class="rf-sidebar-header h-16 px-4 border-b border-white/10 flex items-center justify-between gap-3 shrink-0">
            <a href="{{ route('staff.dashboard') }}" class="flex items-center gap-3 min-w-0 group" title="Raflora Staff Workspace">
                <span class="h-8 w-8 rounded-lg bg-white flex items-center justify-center shrink-0 shadow-sm">
                    <img src="{{ asset('assets/images/raflora_flower_emblem_transparent.png') }}" alt="Raflora Enterprises" class="h-5 w-6 object-contain">
                </span>
                <div class="rf-sidebar-brand-text min-w-0">
                    <span class="block text-base font-bold text-white tracking-tight font-serif leading-tight truncate">Raflora</span>
                    <span class="block text-[11px] font-medium text-navy-300 truncate">Floral Event Management</span>
                    <span class="sr-only">Workspace</span>
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
                    id="closeStaffSidebarBtn"
                    onclick="closeStaffSidebar()"
                    class="lg:hidden p-2 rounded-md text-navy-300 hover:text-white hover:bg-white/10 transition cursor-pointer"
                    aria-label="Close Staff navigation"
                >
                    <i class="fa-solid fa-xmark text-base"></i>
                </button>
            </div>
        </div>

        <!-- Scrolling Navigation Region -->
        <nav class="flex-1 overflow-y-auto overflow-x-hidden px-3 py-4 space-y-5 [scrollbar-width:thin] [scrollbar-color:rgba(255,255,255,0.15)_transparent]" aria-label="Staff navigation">
            @foreach($staffNav as $section => $items)
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
                                            <span class="rf-nav-badge-dot hidden absolute -top-1 -right-1 w-2.5 h-2.5 bg-rose-600 rounded-full ring-2 ring-navy-950"></span>
                                        @endif
                                    </div>
                                @else
                                    <i class="{{ $item['icon'] }} w-5 text-center text-sm shrink-0" aria-hidden="true"></i>
                                @endif
                                <span class="rf-sidebar-label text-sm truncate">{{ $item['label'] }}</span>
                                @if(isset($item['badge']) && $item['badge'] > 0)
                                    <span class="rf-nav-badge-pill ml-auto inline-flex min-w-6 items-center justify-center rounded-full bg-rose-600 px-2 py-0.5 text-[11px] font-semibold text-white">{{ $item['badge'] > 99 ? '99+' : $item['badge'] }}</span>
                                @endif
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
                id="staffUserDropdownMenu"
                style="display: none;"
                class="absolute bottom-full left-3 right-3 mb-2 bg-white rounded-xl shadow-xl ring-1 ring-slate-900/10 p-1.5 z-50 text-[13px] text-slate-700"
            >
                <div class="px-2.5 py-2 border-b border-slate-100 mb-1">
                    <p class="font-semibold text-slate-900 truncate">{{ $staffUser->name }}</p>
                    <p class="text-xs text-slate-500 truncate">{{ $staffUser->email ?? 'staff@raflora.com' }}</p>
                </div>
                <a href="{{ route('staff.profile.edit') }}" class="flex items-center gap-2.5 px-2.5 py-2 rounded-lg hover:bg-slate-50 hover:text-slate-900 font-medium transition">
                    <i class="fa-solid fa-user-gear w-4 text-center text-slate-400"></i>
                    <span>Account Settings</span>
                </a>
                <a href="{{ route('staff.activity') }}" class="flex items-center gap-2.5 px-2.5 py-2 rounded-lg hover:bg-slate-50 hover:text-slate-900 font-medium transition">
                    <i class="fa-solid fa-clock-rotate-left w-4 text-center text-slate-400"></i>
                    <span>My Activity</span>
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
                    onclick="toggleStaffUserMenu()"
                    class="flex items-center gap-2.5 min-w-0 flex-1 p-1.5 rounded-lg hover:bg-white/5 transition text-left cursor-pointer"
                    aria-haspopup="true"
                    aria-expanded="false"
                    id="staffUserMenuBtn"
                    title="{{ $staffUser->name }} (Staff - Floral Team)"
                >
                    <div class="relative shrink-0">
                        @if($staffProfileImage)
                            <img src="{{ $staffProfileImage }}" alt="Profile photo for {{ $staffUser->name }}" class="h-9 w-9 rounded-full object-cover ring-1 ring-rose-400/30">
                        @else
                            <div class="w-9 h-9 rounded-full bg-rose-500/20 text-rose-200 ring-1 ring-rose-400/30 flex items-center justify-center font-semibold text-xs select-none">
                                {{ strtoupper($staffInitials ?: 'ST') }}
                            </div>
                        @endif
                        <span class="absolute bottom-0 right-0 w-2.5 h-2.5 rounded-full bg-emerald-400 ring-2 ring-navy-950"></span>
                    </div>
                    <div class="rf-sidebar-footer-text min-w-0 flex-1">
                        <p class="text-[13px] font-semibold text-white truncate">{{ $staffUser->name }}</p>
                        <p class="text-[11px] text-navy-300 truncate">Staff - Floral Team</p>
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

    <!-- RIGHT MAIN CONTAINER (LIKE ADMIN WITH lg:pl-64) -->
    <div id="staffMainContainer" class="flex flex-col lg:pl-64 min-h-screen w-full transition-all duration-300 ease-in-out">
        <!-- Mobile Top Bar -->
        <div class="sticky top-0 z-30 bg-white/95 backdrop-blur px-4 h-14 border-b border-slate-200 flex items-center justify-between gap-3 lg:hidden">
            <button
                id="mobileStaffToggle"
                type="button"
                onclick="toggleStaffSidebarState()"
                class="flex items-center gap-3 min-w-0 rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 cursor-pointer"
                aria-controls="staffSidebar"
                aria-expanded="false"
                aria-label="Open Staff navigation"
            >
                <span class="w-9 h-9 rounded-lg border border-slate-200 flex items-center justify-center text-slate-600 shrink-0">
                    <i class="fa-solid fa-bars text-sm"></i>
                </span>
                <img src="{{ asset('assets/images/raflora_flower_emblem_transparent.png') }}" alt="Raflora logo" class="h-6 w-7 object-contain shrink-0">
                <span class="text-sm font-semibold text-slate-900 truncate max-w-[200px]">{{ $title }}</span>
            </button>
            <div class="flex items-center gap-2">
                <a href="{{ route('staff.messages') }}" class="relative p-2 text-slate-500 hover:text-slate-800" aria-label="Messages">
                    <i class="fa-regular fa-bell text-base"></i>
                    @if($staffUnread > 0)
                        <span class="absolute top-1 right-1 w-2.5 h-2.5 rounded-full bg-rose-600"></span>
                    @endif
                </a>
            </div>
        </div>

        <!-- Desktop Page Header (single source of page context like Admin) -->
        <header class="hidden lg:block sticky top-0 z-20 bg-white/90 backdrop-blur border-b border-slate-200">
            <div class="mx-auto w-full max-w-[1600px] px-6 xl:px-8 min-h-[4.5rem] py-3 flex items-center justify-between gap-6">
                <div class="min-w-0 flex items-center gap-4 flex-1">
                    <div>
                        @if($currentItem)
                            <nav class="flex items-center gap-1.5 text-xs font-medium text-slate-500 mb-0.5" aria-label="Breadcrumb">
                                @if($isChildPage)
                                    <a href="{{ route($currentItem['route']) }}" class="hover:text-brand-700 transition">{{ $currentItem['label'] }}</a>
                                    <i class="fa-solid fa-chevron-right text-[9px] text-slate-300" aria-hidden="true"></i>
                                    <span class="text-slate-700 truncate" aria-current="page">{{ $title }}</span>
                                @else
                                    <span class="text-[11px] font-semibold tracking-[0.12em] text-rose-600">{{ strtoupper($currentSection) }}</span>
                                @endif
                            </nav>
                        @endif
                        <h1 class="serif text-[1.375rem] leading-tight font-bold text-navy-900 tracking-tight truncate">{{ $title }}</h1>
                    </div>
                </div>

                <div class="flex items-center gap-4 shrink-0">
                    @isset($actions)
                        <div class="hidden lg:flex items-center gap-2">{{ $actions }}</div>
                    @endisset

                    <a href="{{ route('staff.messages') }}" class="relative inline-flex h-9 w-9 items-center justify-center rounded-full text-slate-500 transition hover:bg-slate-100 hover:text-slate-800" aria-label="Messages">
                        <i class="fa-regular fa-bell text-[16px]" aria-hidden="true"></i>
                        @if($staffUnread > 0)
                            <span class="absolute top-1 right-1 inline-flex min-w-4 h-4 items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-bold leading-none text-white ring-2 ring-white tabular-nums">{{ $staffUnread > 9 ? '9+' : $staffUnread }}</span>
                        @endif
                    </a>
                    <div class="text-right leading-tight">
                        <p class="text-xs font-bold text-slate-800">{{ now()->timezone('Asia/Manila')->format('D, M j, Y') }}</p>
                        <p class="text-[11px] font-medium text-slate-500">{{ now()->timezone('Asia/Manila')->format('g:i A') }}</p>
                    </div>
                    <a href="{{ route('staff.profile.edit') }}" class="h-9 w-9 items-center justify-center overflow-hidden rounded-full bg-rose-600 text-xs font-bold text-white shadow-xs flex select-none transition hover:opacity-90" title="{{ $staffUser->name }}" aria-label="Staff account settings">
                        @if($staffProfileImage)
                            <img src="{{ $staffProfileImage }}" alt="" class="h-full w-full object-cover">
                        @else
                            {{ strtoupper($staffInitials ?: 'ST') }}
                        @endif
                    </a>
                </div>
            </div>
        </header>

        <!-- Main Work Area (Clean like Admin without duplicate header/description) -->
        <main class="flex-1 bg-[#F8F9FC] p-4 pb-12 sm:p-6 xl:px-8" aria-label="Staff workspace content">
            <div class="mx-auto w-full max-w-[1600px]">
                @isset($actions)
                    <div class="lg:hidden flex flex-wrap items-center gap-2 mb-4">{{ $actions }}</div>
                @endisset

                {{ $slot }}
            </div>
        </main>
    </div>

    {{-- Global Flash Messages Toast Container (Root Stacking Context for tests and floating alerts) --}}
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

    </div>

    <!-- UI Interaction Scripts (matching Admin behavior) -->
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
            closeStaffUserMenu();
        }

        // Mobile Slide-over Drawer Controls
        function toggleStaffSidebarState() {
            if (window.matchMedia('(min-width: 1024px)').matches) {
                return;
            }

            const sidebar = document.getElementById('staffSidebar');
            if (!sidebar) return;

            if (sidebar.classList.contains('-translate-x-full')) {
                openStaffSidebar();
            } else {
                closeStaffSidebar();
            }
        }

        function openStaffSidebar() {
            const sidebar = document.getElementById('staffSidebar');
            const backdrop = document.getElementById('staffSidebarBackdrop');
            const mobileToggle = document.getElementById('mobileStaffToggle');
            const closeBtn = document.getElementById('closeStaffSidebarBtn');

            if (!sidebar) return;

            sidebar.classList.remove('-translate-x-full');
            if (backdrop) backdrop.classList.remove('hidden');
            if (mobileToggle) mobileToggle.setAttribute('aria-expanded', 'true');
            document.body.style.overflow = 'hidden';
            if (closeBtn) closeBtn.focus();
        }

        function closeStaffSidebar() {
            const sidebar = document.getElementById('staffSidebar');
            const backdrop = document.getElementById('staffSidebarBackdrop');
            const mobileToggle = document.getElementById('mobileStaffToggle');

            if (!sidebar) return;

            sidebar.classList.add('-translate-x-full');
            if (backdrop) backdrop.classList.add('hidden');
            if (mobileToggle) mobileToggle.setAttribute('aria-expanded', 'false');
            document.body.style.overflow = '';
        }

        // Staff User Menu Popover Toggle
        function toggleStaffUserMenu() {
            const menu = document.getElementById('staffUserDropdownMenu');
            const btn = document.getElementById('staffUserMenuBtn');
            if (!menu) return;

            const isShown = menu.style.display === 'block';
            if (isShown) {
                closeStaffUserMenu();
            } else {
                menu.style.display = 'block';
                btn?.setAttribute('aria-expanded', 'true');
            }
        }

        function closeStaffUserMenu() {
            const menu = document.getElementById('staffUserDropdownMenu');
            const btn = document.getElementById('staffUserMenuBtn');
            if (!menu) return;

            menu.style.display = 'none';
            btn?.setAttribute('aria-expanded', 'false');
        }

        document.addEventListener('DOMContentLoaded', function() {
            const navLinks = document.querySelectorAll('#staffSidebar nav a');

            window.toggleStaffSidebar = toggleStaffSidebarState;
            window.closeStaffSidebar = closeStaffSidebar;

            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape') {
                    closeStaffSidebar();
                    closeStaffUserMenu();
                }
            });

            document.addEventListener('click', function(e) {
                const userMenu = document.getElementById('staffUserDropdownMenu');
                const userBtn = document.getElementById('staffUserMenuBtn');
                if (userMenu && userMenu.style.display === 'block') {
                    if (!userMenu.contains(e.target) && !userBtn?.contains(e.target)) {
                        closeStaffUserMenu();
                    }
                }
            });

            navLinks.forEach((link) => {
                link.addEventListener('click', () => {
                    if (window.matchMedia('(max-width: 1023px)').matches) {
                        closeStaffSidebar();
                    }
                });
            });
        });
    </script>
</body>
</html>
