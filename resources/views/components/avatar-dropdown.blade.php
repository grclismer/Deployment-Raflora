<div class="relative inline-flex items-center gap-2 sm:gap-3">
    <div>
        <button id="notificationToggle" type="button" class="relative inline-flex h-10 w-10 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-700 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-emerald-100" aria-label="View notifications" aria-expanded="false">
            <i class="fa-solid fa-bell text-lg"></i>
            @php
                $unreadNotificationCount = \App\Models\ClientNotification::where('user_id', Auth::id())->where('is_read', false)->count();
            @endphp
            @if($unreadNotificationCount > 0)
                <span id="notificationBellBadge" data-unread-count="{{ $unreadNotificationCount }}" class="absolute -top-1 -right-1 flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white">
                    {{ $unreadNotificationCount > 9 ? '9+' : $unreadNotificationCount }}
                </span>
            @else
                <span id="notificationBellBadge" data-unread-count="0" class="hidden absolute -top-1 -right-1 flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white"></span>
            @endif
        </button>

        <div id="notificationDropdown" class="hidden absolute right-0 top-full z-50 mt-2 max-h-[calc(100dvh-6rem)] w-[calc(100vw-2rem)] origin-top-right overflow-y-auto rounded-2xl border border-slate-200 bg-white shadow-2xl sm:w-80">
            <div class="border-b border-slate-200 px-4 py-3">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm font-semibold text-slate-800">Notifications</p>
                    <a href="{{ route('client.notifications.index') }}" class="text-xs font-medium text-purple-600 hover:text-purple-700">View all</a>
                </div>
            </div>

            @php
                $notificationItems = \App\Models\ClientNotification::where('user_id', Auth::id())
                    ->with('booking')
                    ->latest()
                    ->limit(8)
                    ->get();
            @endphp

            @forelse($notificationItems as $notification)
                @php
                    $targetUrl = route('client.notifications.index');
                    if ($notification->booking_id) {
                        $isMsg = str_contains(strtolower($notification->title ?? ''), 'message');
                        $targetUrl = route('bookings.analysis', ['booking' => $notification->booking_id]) . ($isMsg ? '#booking-conversation-root' : '');
                    }
                @endphp
                <a href="{{ $targetUrl }}" class="block border-b border-slate-100 px-4 py-3 hover:bg-slate-50 transition {{ $notification->is_read ? 'bg-white' : 'bg-purple-50/60' }}">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <p class="break-words text-sm font-semibold text-slate-800 [overflow-wrap:anywhere]">{{ $notification->title }}</p>
                            <p class="mt-1 line-clamp-3 break-words text-xs leading-5 text-slate-600">{{ $notification->message }}</p>
                        </div>
                        @if(!$notification->is_read)
                            <span class="mt-0.5 h-2.5 w-2.5 rounded-full bg-purple-600 flex-shrink-0"></span>
                        @endif
                    </div>
                    <p class="mt-2 text-[11px] text-slate-500">
                        {{ $notification->booking ? ucfirst($notification->booking->event_type ?? 'Booking') : 'System update' }}
                        • {{ $notification->created_at->diffForHumans() }}
                    </p>
                </a>
            @empty
                <div class="px-4 py-6 text-sm text-slate-500">No notifications yet.</div>
            @endforelse
        </div>
    </div>

    <div>
        <button id="avatarToggle" type="button" class="flex h-10 w-10 items-center justify-center overflow-hidden rounded-full border-2 border-purple-700 bg-white shadow-md transition hover:shadow-lg" aria-label="Open profile menu" aria-expanded="false" aria-controls="avatarDropdown">
            @php
                $headerUser = auth()->user();
                $headerProfileImage = $headerUser?->profile_image && \Storage::disk('public')->exists($headerUser->profile_image)
                    ? asset('storage/' . $headerUser->profile_image)
                    : null;
                $headerNameParts = preg_split('/\s+/', trim((string) ($headerUser?->name ?? 'User')), 2);
                $headerFirstName = $headerUser?->first_name ?: ($headerNameParts[0] ?? '');
                $headerLastName = $headerUser?->last_name ?: ($headerNameParts[1] ?? '');
                $headerInitials = collect([$headerFirstName, $headerLastName])
                    ->filter()
                    ->map(fn ($part) => mb_strtoupper(mb_substr(trim($part), 0, 1)))
                    ->implode('');
            @endphp
            @if($headerProfileImage)
                <img data-profile-image-preview src="{{ $headerProfileImage }}" alt="Profile photo for {{ $headerUser->name }}" class="h-full w-full object-cover">
                <span data-profile-image-fallback class="hidden h-full w-full items-center justify-center bg-purple-100 text-sm font-bold text-purple-800">{{ $headerInitials ?: 'U' }}</span>
            @else
                <span data-profile-image-fallback class="flex h-full w-full items-center justify-center bg-purple-100 text-sm font-bold text-purple-800">{{ $headerInitials ?: 'U' }}</span>
                <img data-profile-image-preview alt="Profile photo preview" class="hidden h-full w-full object-cover">
            @endif
        </button>

        <div id="avatarDropdown" class="hidden absolute right-0 top-full z-50 mt-2 max-h-[calc(100dvh-6rem)] w-[calc(100vw-2rem)] origin-top-right overflow-y-auto rounded-2xl border border-gray-200 bg-white shadow-xl sm:w-56">
            @if(auth()->user()->role === 'client')
                <div data-mobile-avatar-nav class="hidden py-2 md:hidden" style="display:none">
                    <a href="{{ route('home') }}#home-hero" class="mobile-avatar-nav-link block px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">HOME</a>
                    <a href="{{ route('gallery') }}" class="mobile-avatar-nav-link block px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">GALLERY</a>
                    <a href="{{ route('packages.index') }}" class="mobile-avatar-nav-link block px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">PACKAGES</a>
                    <a href="{{ route('about') }}" class="mobile-avatar-nav-link block px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">ABOUT</a>
                    <button type="button" data-account-menu-open class="flex w-full items-center justify-between border-t border-slate-100 px-5 py-3 text-left text-sm font-semibold text-slate-700 transition hover:bg-slate-50" aria-expanded="false" aria-controls="mobileAccountMenu">
                        <span>ACCOUNT</span>
                        <i class="fa-solid fa-chevron-right text-xs text-slate-400" aria-hidden="true"></i>
                    </button>
                </div>

                <div id="mobileAccountMenu" data-mobile-account-menu class="hidden py-2 md:hidden" style="display:none">
                    <button type="button" data-account-menu-back class="flex w-full items-center gap-2 border-b border-slate-100 px-5 py-3 text-left text-sm font-semibold text-slate-600 transition hover:bg-slate-50" aria-label="Back to main menu">
                        <i class="fa-solid fa-arrow-left text-xs" aria-hidden="true"></i>
                        <span>ACCOUNT</span>
                    </button>
                    <a href="{{ route('booking.start') }}" class="block px-5 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50">BOOK EVENT</a>
                    <a href="{{ route('bookings') }}" class="block px-5 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50">MY BOOKINGS</a>
                    <a href="{{ route('client.dashboard') }}" class="block px-5 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50">DASHBOARD</a>
                    <a href="{{ route('booking-history') }}" class="block px-5 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50">HISTORY</a>
                    <a href="{{ route('account-settings') }}" class="block border-t border-slate-100 px-5 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50">Account Settings</a>
                    <form method="POST" action="{{ route('logout') }}" class="border-t border-slate-100">
                        @csrf
                        <button type="submit" class="w-full px-5 py-3 text-left text-sm font-semibold text-red-600 transition hover:bg-red-50">Log Out</button>
                    </form>
                </div>
            @endif

            <div data-desktop-avatar-actions class="hidden py-1 md:block" style="display:none">
                @if(auth()->user()->role === 'admin')
                    <a href="{{ route('admin.dashboard') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition">
                        <i class="fa-solid fa-gauge-high mr-2 w-4 text-center"></i>Admin Dashboard
                    </a>
                    <hr class="my-1 border-slate-100">
                @elseif(auth()->user()->role === 'staff')
                    <a href="{{ route('staff.dashboard') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition">
                        <i class="fa-solid fa-clipboard-list mr-2 w-4 text-center"></i>Staff Dashboard
                    </a>
                    <a href="{{ route('staff.profile.edit') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition">
                        <i class="fa-solid fa-user-gear mr-2 w-4 text-center"></i>Account Settings
                    </a>
                    <hr class="my-1 border-slate-100">
                @else
                    <a href="{{ route('client.dashboard') }}" class="block px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 transition">DASHBOARD</a>
                    <a href="{{ route('booking-history') }}" class="block px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 transition">HISTORY</a>
                    <a href="{{ route('account-settings') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition">Account Settings</a>
                @endif
                <form method="POST" action="{{ route('logout') }}" class="block w-full text-left">
                    @csrf
                    <button type="submit" class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition">
                        <i class="fa-solid fa-arrow-right-from-bracket mr-2 w-4 text-center"></i>Log Out
                    </button>
                </form>
            </div>
            </div>
        </div>

    <script>
        (function(){
            const notificationToggle = document.getElementById('notificationToggle');
            const notificationMenu = document.getElementById('notificationDropdown');
            const avatarToggle = document.getElementById('avatarToggle');
            const avatarMenu = document.getElementById('avatarDropdown');
            const mobileAccountOpen = avatarMenu?.querySelector('[data-account-menu-open]');
            const mobileAccountBack = avatarMenu?.querySelector('[data-account-menu-back]');
            const mobileAccountMenu = avatarMenu?.querySelector('[data-mobile-account-menu]');
            const mobileAvatarNav = avatarMenu?.querySelector('[data-mobile-avatar-nav]');
            const desktopAvatarActions = avatarMenu?.querySelector('[data-desktop-avatar-actions]');
            const hasMobileAvatarNav = Boolean(mobileAvatarNav && mobileAccountMenu);
            const isMobileViewport = () => window.matchMedia('(max-width: 767px)').matches;

            const syncAvatarMenuLayout = () => {
                if (!avatarMenu || !desktopAvatarActions) return;

                const menuIsOpen = !avatarMenu.classList.contains('hidden');
                const showMobileMenu = hasMobileAvatarNav && isMobileViewport() && menuIsOpen;
                const showAccountMenu = showMobileMenu && !mobileAccountMenu.classList.contains('hidden');

                if (mobileAvatarNav) {
                    mobileAvatarNav.style.display = showMobileMenu && !showAccountMenu ? 'block' : 'none';
                }
                if (mobileAccountMenu) {
                    mobileAccountMenu.style.display = showAccountMenu ? 'block' : 'none';
                }
                desktopAvatarActions.style.display = menuIsOpen && !isMobileViewport() ? 'block' : 'none';
            };

            const showMobileAvatarNav = () => {
                mobileAvatarNav?.classList.remove('hidden');
                mobileAccountMenu?.classList.add('hidden');
                mobileAccountOpen?.setAttribute('aria-expanded', 'false');
                syncAvatarMenuLayout();
            };

            const closeNotificationMenu = () => {
                if (!notificationMenu || !notificationToggle) return;
                notificationMenu.classList.add('hidden');
                notificationToggle.setAttribute('aria-expanded', 'false');
            };
            const closeAvatarMenu = () => {
                if (!avatarMenu || !avatarToggle) return;
                avatarMenu.classList.add('hidden');
                avatarToggle.setAttribute('aria-expanded', 'false');
                avatarToggle.setAttribute('aria-label', 'Open profile menu');
                showMobileAvatarNav();
                syncAvatarMenuLayout();
            };

            mobileAccountOpen?.addEventListener('click', () => {
                mobileAvatarNav?.classList.add('hidden');
                mobileAccountMenu?.classList.remove('hidden');
                mobileAccountOpen.setAttribute('aria-expanded', 'true');
                syncAvatarMenuLayout();
            });
            mobileAccountBack?.addEventListener('click', showMobileAvatarNav);
            avatarMenu?.querySelectorAll('.mobile-avatar-nav-link').forEach((link) => {
                link.addEventListener('click', closeAvatarMenu);
            });

            if (notificationToggle && notificationMenu) {
                notificationToggle.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const isOpen = !notificationMenu.classList.contains('hidden');
                    if (isOpen) {
                        closeNotificationMenu();
                    } else {
                        closeAvatarMenu();
                        document.dispatchEvent(new CustomEvent('raflora:close-mobile-menu'));
                        notificationMenu.classList.remove('hidden');
                        notificationToggle.setAttribute('aria-expanded', 'true');
                    }
                });
            }

            if (avatarToggle && avatarMenu) {
                avatarToggle.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const isOpen = avatarMenu.classList.contains('hidden');
                    if (isOpen) {
                        closeNotificationMenu();
                        document.dispatchEvent(new CustomEvent('raflora:close-mobile-menu'));
                        avatarMenu.classList.remove('hidden');
                        avatarToggle.setAttribute('aria-expanded', 'true');
                        avatarToggle.setAttribute('aria-label', 'Close profile menu');
                        showMobileAvatarNav();
                        syncAvatarMenuLayout();
                    } else {
                        closeAvatarMenu();
                    }
                });
            }

            document.addEventListener('raflora:close-header-dropdowns', () => {
                closeNotificationMenu();
                closeAvatarMenu();
            });

            document.addEventListener('click', (e) => {
                if (!(e.target instanceof Element)) return;
                const insideDropdowns = e.target.closest('#notificationDropdown, #notificationToggle, #avatarDropdown, #avatarToggle');
                if (!insideDropdowns) {
                    closeNotificationMenu();
                    closeAvatarMenu();
                }
            });

            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    closeNotificationMenu();
                    closeAvatarMenu();
                }
            });

            window.addEventListener('resize', syncAvatarMenuLayout);
            syncAvatarMenuLayout();
        })();
    </script>
</div>
