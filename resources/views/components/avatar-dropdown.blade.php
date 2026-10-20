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

        <div id="notificationDropdown" class="hidden absolute right-0 top-full mt-2 w-[calc(100vw-2rem)] sm:w-80 max-h-[85vh] overflow-y-auto rounded-2xl border border-slate-200 bg-white shadow-2xl z-50 origin-top-right">
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
                            <p class="truncate text-sm font-semibold text-slate-800">{{ $notification->title }}</p>
                            <p class="mt-1 line-clamp-2 text-xs leading-5 text-slate-600">{{ $notification->message }}</p>
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
        <button id="avatarToggle" class="w-10 h-10 rounded-full bg-white flex items-center justify-center shadow-md border-2 border-purple-700 overflow-hidden hover:shadow-lg transition">
            <img src="https://ui-avatars.com/api/?name={{ urlencode(Auth::user()->name ?? 'User') }}&background=8b5cf6&color=fff" alt="Profile" class="w-full h-full object-cover">
        </button>

        <div id="avatarDropdown" class="hidden absolute right-0 top-full mt-2 w-[calc(100vw-2rem)] sm:w-56 max-h-[85vh] overflow-y-auto bg-white rounded-lg shadow-xl border border-gray-200 z-50 origin-top-right">
            <div class="py-1">
                @if(auth()->user()->role === 'admin')
                    <a href="{{ route('admin.dashboard') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition">
                        <i class="fa-solid fa-gauge-high mr-2 w-4 text-center"></i>Admin Dashboard
                    </a>
                    <hr class="my-1 border-slate-100">
                @elseif(auth()->user()->role === 'staff')
                    <a href="{{ route('staff.dashboard') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition">
                        <i class="fa-solid fa-clipboard-list mr-2 w-4 text-center"></i>Staff Dashboard
                    </a>
                    <hr class="my-1 border-slate-100">
                @else
                    <a href="{{ route('bookings') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition font-medium">
                        <i class="fa-solid fa-calendar-check mr-2 w-4 text-center text-purple-700"></i>My Bookings
                    </a>
                    <a href="{{ route('client.dashboard') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition">
                        <i class="fa-solid fa-house mr-2 w-4 text-center"></i>Dashboard
                    </a>
                    <a href="{{ route('booking-history') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition">
                        <i class="fa-solid fa-clock-rotate-left mr-2 w-4 text-center"></i>History
                    </a>
                    <hr class="my-1 border-slate-100">
                    <a href="{{ route('account-settings') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition">
                        <i class="fa-solid fa-user-gear mr-2 w-4 text-center"></i>Account Settings
                    </a>
                    <hr class="my-1 border-slate-100">
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

            if (notificationToggle && notificationMenu) {
                notificationToggle.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const isOpen = !notificationMenu.classList.contains('hidden');
                    notificationMenu.classList.toggle('hidden', isOpen);
                    notificationToggle.setAttribute('aria-expanded', String(!isOpen));
                    if (!isOpen && avatarMenu) {
                        avatarMenu.classList.add('hidden');
                    }
                });
            }

            if (avatarToggle && avatarMenu) {
                avatarToggle.addEventListener('click', (e) => {
                    e.stopPropagation();
                    avatarMenu.classList.toggle('hidden');
                    if (!avatarMenu.classList.contains('hidden') && notificationMenu) {
                        notificationMenu.classList.add('hidden');
                        if (notificationToggle) {
                            notificationToggle.setAttribute('aria-expanded', 'false');
                        }
                    }
                });
            }

            document.addEventListener('click', (e) => {
                if (notificationMenu && !e.target.closest('#notificationDropdown') && !e.target.closest('#notificationToggle')) {
                    notificationMenu.classList.add('hidden');
                    if (notificationToggle) {
                        notificationToggle.setAttribute('aria-expanded', 'false');
                    }
                }

                if (avatarMenu && !e.target.closest('#avatarDropdown') && !e.target.closest('#avatarToggle')) {
                    avatarMenu.classList.add('hidden');
                }
            });
        })();
    </script>
</div>
