<x-app-layout title="Notifications">
    <x-client-layout active="notifications">
        @php
            // Group by recency and tag a category so the inbox can be filtered without another request.
            $categorize = function (array $notification): string {
                $text = strtolower(($notification['title'] ?? '') . ' ' . ($notification['badge'] ?? ''));
                if (str_contains($text, 'message') || str_contains($text, 'replied')) {
                    return 'messages';
                }
                if (str_contains($text, 'quot') || str_contains($text, 'price')) {
                    return 'quotes';
                }
                return 'bookings';
            };
            $groupLabel = function (array $notification): string {
                $created = !empty($notification['created_at']) ? \Illuminate\Support\Carbon::parse($notification['created_at']) : null;
                if (!$created) {
                    return 'Older';
                }
                if ($created->isToday()) {
                    return 'Today';
                }
                if ($created->isYesterday()) {
                    return 'Yesterday';
                }
                return $created->greaterThanOrEqualTo(now()->startOfWeek()) ? 'Earlier this week' : 'Older';
            };

            $items = collect($notificationData)->map(fn ($n) => $n + ['category' => $categorize($n)]);
            $groups = $items->groupBy(fn ($n) => $groupLabel($n));
            $categoryCounts = $items->countBy('category');

            $filters = [
                'all' => ['label' => 'All', 'count' => $items->count()],
                'unread' => ['label' => 'Unread', 'count' => $unreadCount],
                'quotes' => ['label' => 'Quotes & pricing', 'count' => $categoryCounts->get('quotes', 0)],
                'bookings' => ['label' => 'Booking updates', 'count' => $categoryCounts->get('bookings', 0)],
                'messages' => ['label' => 'Messages', 'count' => $categoryCounts->get('messages', 0)],
            ];

            $categoryIcon = [
                'quotes' => 'fa-solid fa-file-invoice-dollar',
                'bookings' => 'fa-regular fa-calendar-check',
                'messages' => 'fa-regular fa-comment-dots',
            ];

            $badgeTone = fn ($badge) => match ($badge) {
                'Declined' => 'bg-rose-50 text-rose-700 ring-rose-200',
                'Approved' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
                'Price Change' => 'bg-amber-50 text-amber-800 ring-amber-200',
                'Item Adjustment' => 'bg-sky-50 text-sky-700 ring-sky-200',
                default => 'bg-slate-100 text-slate-600 ring-slate-200',
            };
        @endphp

        <div class="mx-auto max-w-4xl sm:px-2 sm:py-4 lg:px-4">
            <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <div class="flex items-center">
                        <h1 class="rf-page-title text-2xl font-bold text-slate-900 sm:text-3xl">Notifications</h1>
                        <x-info-popover title="Notifications">
                            Manage your event updates, quotes, and admin notices.
                        </x-info-popover>
                    </div>
                    <p class="mt-1 text-sm text-slate-500">Updates from Raflora about your bookings, quotations, and messages.</p>
                </div>

                @if($notifications->count())
                    <div class="flex flex-col items-stretch gap-2 sm:flex-row sm:items-center">
                        <span id="clientUnreadSummary" class="rf-badge rf-badge--primary self-start" data-unread="{{ $unreadCount }}">
                            {{ $unreadCount }} New Update{{ $unreadCount === 1 ? '' : 's' }}
                        </span>
                        @if($unreadCount > 0)
                            <form method="POST" action="{{ route('client.notifications.read-all') }}">
                                @csrf
                                <button type="submit" class="inline-flex min-h-10 w-full items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-3.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 sm:w-auto">
                                    <i class="fa-solid fa-check-double text-xs" aria-hidden="true"></i> Mark all as read
                                </button>
                            </form>
                        @endif
                    </div>
                @endif
            </div>

            <div class="rf-panel overflow-hidden">
                @if($notifications->isEmpty())
                    <div class="flex flex-col items-center justify-center gap-2 px-6 py-16 text-center">
                        <span class="flex h-12 w-12 items-center justify-center rounded-full bg-emerald-50 text-emerald-600" aria-hidden="true"><i class="fa-regular fa-bell text-lg"></i></span>
                        <p class="text-base font-semibold text-slate-800">You have no booking updates yet.</p>
                        <p class="text-sm text-slate-500">We'll let you know here when Raflora reviews your booking or sends a quotation.</p>
                    </div>
                @else
                    <div class="flex gap-1.5 overflow-x-auto border-b border-slate-200 px-4 py-3 sm:px-5" role="tablist" aria-label="Filter notifications">
                        @foreach($filters as $key => $filter)
                            @if($key === 'all' || $filter['count'] > 0)
                                <button type="button" role="tab" aria-selected="{{ $key === 'all' ? 'true' : 'false' }}" data-filter="{{ $key }}"
                                    class="client-notif-filter inline-flex min-h-9 items-center gap-2 whitespace-nowrap rounded-full px-3.5 text-sm transition {{ $key === 'all' ? 'bg-slate-900 font-semibold text-white' : 'bg-slate-100 font-medium text-slate-600 hover:bg-slate-200' }}">
                                    {{ $filter['label'] }}
                                    <span class="client-notif-filter-count text-xs tabular-nums opacity-75" @if($key === 'unread') id="clientUnreadFilterCount" @endif>{{ $filter['count'] }}</span>
                                </button>
                            @endif
                        @endforeach
                    </div>

                    @foreach($groups as $label => $groupItems)
                        <section class="notification-group" aria-label="{{ $label }}">
                            <h2 class="sticky top-0 z-[1] border-b border-slate-100 bg-slate-50/95 px-4 py-2 font-sans text-xs font-semibold uppercase tracking-wider text-slate-500 backdrop-blur sm:px-5">{{ $label }}</h2>
                            <ul class="divide-y divide-slate-100" role="list">
                                @foreach($groupItems as $notification)
                                    @php $isUnread = !$notification['is_read']; @endphp
                                    <li class="notification-row relative flex gap-3 px-4 py-4 transition sm:gap-4 sm:px-5 {{ $isUnread ? 'bg-emerald-50/40' : 'bg-white' }}" data-notification-id="{{ $notification['id'] }}" data-read="{{ $notification['is_read'] ? '1' : '0' }}" data-category="{{ $notification['category'] }}">
                                        @if($isUnread)
                                            <span class="unread-bar absolute inset-y-0 left-0 w-1 bg-emerald-600" aria-hidden="true"></span>
                                        @endif
                                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $isUnread ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}" aria-hidden="true">
                                            <i class="{{ $categoryIcon[$notification['category']] }} text-sm"></i>
                                        </span>

                                        <div class="min-w-0 flex-1">
                                            <div class="flex min-w-0 items-start justify-between gap-3">
                                                <p class="min-w-0 break-words text-[15px] [overflow-wrap:anywhere] {{ $isUnread ? 'font-bold text-slate-900' : 'font-semibold text-slate-800' }}">
                                                    {{ $notification['title'] ?: ucwords(str_replace('_', ' ', $notification['booking_event'])) }}
                                                </p>
                                                <time class="shrink-0 whitespace-nowrap text-xs text-slate-400" datetime="{{ $notification['created_at'] ?? '' }}" title="{{ $notification['timestamp'] }}">{{ $notification['time_ago'] ?? $notification['timestamp'] }}</time>
                                            </div>
                                            <div class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs font-medium text-slate-500">
                                                @if($notification['booking_id'])
                                                    <span class="capitalize">{{ str_replace('_', ' ', $notification['booking_event']) }} · Booking #{{ $notification['booking_id'] }}</span>
                                                    <span aria-hidden="true">•</span>
                                                @endif
                                                <span>{{ $notification['timestamp'] }}</span>
                                            </div>
                                            <p class="mt-1.5 line-clamp-2 text-sm leading-6 text-slate-600">{{ Str::limit($notification['summary'], 140) }}</p>

                                            <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
                                                <div class="flex items-center gap-2">
                                                    <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 {{ $badgeTone($notification['badge']) }}">{{ $notification['badge'] }}</span>
                                                    @if($isUnread)
                                                        <span class="new-pill rf-badge rf-badge--primary shrink-0">New</span>
                                                    @endif
                                                </div>
                                                <button
                                                    type="button"
                                                    data-booking-id="{{ $notification['booking_id'] ?? '' }}"
                                                    data-notification-id="{{ $notification['id'] }}"
                                                    data-mark-read-url="{{ route('client.notifications.mark-as-read', ['notification' => $notification['id']]) }}"
                                                    data-notification='@json($notification)'
                                                    class="view-details-btn inline-flex min-h-10 items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-800 transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-slate-200"
                                                >
                                                    View Details <i class="fa-solid fa-chevron-right text-[10px]" aria-hidden="true"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @endforeach
                    <p id="clientNotifFilterEmpty" class="hidden px-6 py-12 text-center text-sm text-slate-500">Nothing here — try another filter.</p>
                    @if(($olderCount ?? 0) > 0)
                        <p class="border-t border-slate-100 px-6 py-3 text-center text-xs text-slate-500" data-older-notifications>
                            Showing your {{ $notifications->count() }} most recent updates. {{ $olderCount }} older {{ $olderCount === 1 ? 'update is' : 'updates are' }} still on each booking's Updates page.
                        </p>
                    @endif
                @endif
            </div>
        </div>

        <div id="notificationModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="modalEventTitle">
            <div class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-3xl bg-white shadow-2xl">
                <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4 sm:px-6 sm:py-5">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-[#0F2E5B]">Latest admin update</p>
                        <h2 id="modalEventTitle" class="mt-1.5 text-xl font-bold text-slate-900"></h2>
                        <p id="modalEventMeta" class="mt-1 text-sm text-slate-500"></p>
                    </div>
                    <button id="closeNotificationModal" type="button" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-slate-200 text-slate-500 transition hover:bg-slate-100" aria-label="Close details">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div id="modalBody" class="space-y-3 px-5 py-4 text-slate-800 sm:px-6 sm:py-5"></div>

                <div class="flex flex-col-reverse gap-2 border-t border-slate-200 px-5 py-4 sm:flex-row sm:justify-end sm:px-6">
                    <button id="closeModalFooter" type="button" class="min-h-11 rounded-xl border border-slate-300 bg-white px-5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                        Close
                    </button>
                    <a id="modalActionLink" href="#" class="hidden min-h-11 rounded-xl bg-emerald-700 px-5 py-3 text-center text-sm font-semibold text-white transition hover:bg-emerald-800">Open Booking</a>
                </div>
            </div>
        </div>

        <script>
            (function () {
                const modal = document.getElementById('notificationModal');
                const modalTitle = document.getElementById('modalEventTitle');
                const modalMeta = document.getElementById('modalEventMeta');
                const modalBody = document.getElementById('modalBody');
                const closeButtons = [
                    document.getElementById('closeNotificationModal'),
                    document.getElementById('closeModalFooter')
                ];
                const bellBadge = document.getElementById('notificationBellBadge');
                const unreadSummary = document.getElementById('clientUnreadSummary');
                const unreadFilterCount = document.getElementById('clientUnreadFilterCount');

                function updateBellBadge(count) {
                    const nextCount = Math.max(0, Number(count) || 0);

                    if (unreadSummary) {
                        unreadSummary.dataset.unread = String(nextCount);
                        unreadSummary.textContent = nextCount + ' New Update' + (nextCount === 1 ? '' : 's');
                    }
                    if (unreadFilterCount) {
                        unreadFilterCount.textContent = String(nextCount);
                    }

                    if (!bellBadge) {
                        return;
                    }

                    bellBadge.dataset.unreadCount = String(nextCount);
                    if (nextCount <= 0) {
                        bellBadge.textContent = '';
                        bellBadge.classList.add('hidden');
                        return;
                    }

                    bellBadge.textContent = nextCount > 9 ? '9+' : String(nextCount);
                    bellBadge.classList.remove('hidden');
                }

                function markNotificationAsRead(button) {
                    const row = button.closest('.notification-row');
                    const notificationId = button.dataset.notificationId;
                    const markReadUrl = button.dataset.markReadUrl;

                    if (!notificationId || !markReadUrl || row?.dataset.read === '1') {
                        return;
                    }

                    fetch(markReadUrl, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({}),
                    })
                        .then((response) => response.ok ? response.json() : null)
                        .then((data) => {
                            if (!data || !data.success) {
                                return;
                            }

                            row.dataset.read = '1';
                            row.classList.remove('bg-emerald-50/40');
                            row.classList.add('bg-white');
                            row.querySelectorAll('.unread-bar, .new-pill').forEach((el) => el.remove());

                            updateBellBadge(data.unread_count ?? 0);
                        })
                        .catch(() => {});
                }

                function appendListSection(title, items, sectionClass, headingClass, itemClass) {
                    const section = document.createElement('div');
                    section.className = 'rounded-2xl border p-4 ' + sectionClass;

                    const heading = document.createElement('p');
                    heading.className = 'mb-2.5 text-[11px] font-bold uppercase tracking-[0.16em] ' + headingClass;
                    heading.textContent = title;
                    section.appendChild(heading);

                    const list = document.createElement('ul');
                    list.className = 'space-y-1.5';
                    items.forEach((item) => {
                        const li = document.createElement('li');
                        li.className = itemClass;
                        li.textContent = item;
                        list.appendChild(li);
                    });
                    section.appendChild(list);
                    modalBody.appendChild(section);
                }

                function renderNotificationDetails(notification) {
                    modalTitle.textContent = notification.title || notification.booking_event || 'Booking';
                    modalMeta.textContent = [notification.event_date, notification.timestamp].filter(Boolean).join(' • ');
                    modalBody.innerHTML = '';

                    const details = notification.details || {};

                    if (details.custom_note) {
                        const note = document.createElement('div');
                        note.className = 'rounded-2xl border border-slate-200 bg-slate-50 p-4';
                        const noteText = document.createElement('p');
                        noteText.className = 'whitespace-pre-line text-sm leading-6 text-slate-700';
                        noteText.textContent = details.custom_note;
                        note.appendChild(noteText);
                        modalBody.appendChild(note);
                    }

                    if (details.items && details.items.length) {
                        appendListSection('Items', details.items, 'border-slate-200 bg-white', 'text-slate-500', 'text-sm font-medium text-slate-800');
                    }

                    if (details.removed_items && details.removed_items.length) {
                        appendListSection('Removed Items', details.removed_items, 'border-rose-200 bg-rose-50', 'text-rose-700', 'text-sm font-semibold text-rose-700');
                    }

                    if (details.price_changes && details.price_changes.length) {
                        appendListSection('Price Changes', details.price_changes, 'border-amber-200 bg-amber-50', 'text-amber-800', 'text-sm font-semibold text-amber-900');
                    }

                    if (!details.custom_note && (!details.items || !details.items.length) && (!details.removed_items || !details.removed_items.length) && (!details.price_changes || !details.price_changes.length)) {
                        const emptyState = document.createElement('div');
                        emptyState.className = 'rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600';
                        emptyState.textContent = 'No detail information was attached to this update.';
                        modalBody.appendChild(emptyState);
                    }
                }

                const selectedBookingId = new URLSearchParams(window.location.search).get('booking');
                const analysisBase = "{{ url('/bookings/analysis') }}";

                document.querySelectorAll('.view-details-btn').forEach((button) => {
                    const openNotification = () => {
                        const notification = JSON.parse(button.dataset.notification || '{}');
                        renderNotificationDetails(notification);
                        modal.classList.remove('hidden');
                        modal.classList.add('flex');
                        const actionLink = document.getElementById('modalActionLink');
                        if (actionLink) {
                            if (notification.booking_id) {
                                const isMsg = (notification.title && notification.title.toLowerCase().includes('message'));
                                actionLink.href = analysisBase + '/' + notification.booking_id + (isMsg ? '#booking-conversation-root' : '');
                                actionLink.classList.remove('hidden');
                                actionLink.textContent = isMsg ? 'Open Conversation' : 'Open Booking';
                            } else {
                                actionLink.classList.add('hidden');
                            }
                        }
                        markNotificationAsRead(button);
                    };

                    button.addEventListener('click', openNotification);

                    if (selectedBookingId && button.dataset.bookingId === String(selectedBookingId)) {
                        openNotification();
                    }
                });

                // Filter chips: category filters plus an Unread view; empty groups collapse.
                const filterButtons = document.querySelectorAll('.client-notif-filter');
                const emptyNote = document.getElementById('clientNotifFilterEmpty');
                const ACTIVE = ['bg-slate-900', 'font-semibold', 'text-white'];
                const INACTIVE = ['bg-slate-100', 'font-medium', 'text-slate-600', 'hover:bg-slate-200'];

                filterButtons.forEach((filterButton) => {
                    filterButton.addEventListener('click', () => {
                        const value = filterButton.dataset.filter;
                        let visible = 0;
                        document.querySelectorAll('.notification-group').forEach((group) => {
                            let groupVisible = 0;
                            group.querySelectorAll('.notification-row').forEach((row) => {
                                const show = value === 'all'
                                    || (value === 'unread' ? row.dataset.read !== '1' : row.dataset.category === value);
                                row.classList.toggle('hidden', !show);
                                if (show) groupVisible++;
                            });
                            group.classList.toggle('hidden', groupVisible === 0);
                            visible += groupVisible;
                        });
                        filterButtons.forEach((other) => {
                            const isActive = other === filterButton;
                            other.classList.remove(...(isActive ? INACTIVE : ACTIVE));
                            other.classList.add(...(isActive ? ACTIVE : INACTIVE));
                            other.setAttribute('aria-selected', isActive ? 'true' : 'false');
                        });
                        if (emptyNote) emptyNote.classList.toggle('hidden', visible > 0);
                    });
                });

                const closeModal = () => {
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                };

                closeButtons.forEach((button) => {
                    if (button) {
                        button.addEventListener('click', closeModal);
                    }
                });

                modal.addEventListener('click', (event) => {
                    if (event.target === modal) {
                        closeModal();
                    }
                });

                document.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape' && modal && !modal.classList.contains('hidden')) {
                        closeModal();
                    }
                });
            })();
        </script>
    </x-client-layout>
</x-app-layout>
