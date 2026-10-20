@props([
    'booking',
    'role' => 'client', // 'admin' or 'client'
    'bookingMessages' => collect(),
    'unreadCount' => 0,
    'activeQuotation' => null,
])

@php
    $isAdmin = $role === 'admin';
    $fetchUrl = $isAdmin ? route('admin.bookings.messages.index', $booking) : route('bookings.messages.index', $booking);
    $sendUrl = $isAdmin ? route('admin.bookings.messages.store', $booking) : route('bookings.messages.store', $booking);
    $readUrl = $isAdmin ? route('admin.bookings.messages.read', $booking) : route('bookings.messages.read', $booking);
    $clientName = $booking->client?->full_name ?? ($booking->guest_name ?? 'Client');
    $isCommunicable = !in_array($booking->status, ['archived'], true);
@endphp

<div id="booking-conversation-root" 
     class="rf-panel overflow-hidden bg-white border border-slate-200/90 rounded-2xl shadow-sm transition-all"
     data-booking-id="{{ $booking->id }}"
     data-role="{{ $role }}"
     data-fetch-url="{{ $fetchUrl }}"
     data-send-url="{{ $sendUrl }}"
     data-read-url="{{ $readUrl }}"
     data-initial-count="{{ $bookingMessages->count() }}">

    {{-- HEADER --}}
    <div class="px-5 py-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3 bg-gradient-to-r from-slate-50/70 via-slate-50/40 to-white">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl {{ $isAdmin ? 'bg-purple-100 text-purple-700' : 'bg-emerald-100 text-emerald-700' }} flex items-center justify-center font-bold text-base shadow-inner shrink-0">
                <i class="fa-solid fa-comments"></i>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-base sm:text-lg font-bold text-slate-900 tracking-tight leading-tight">Communication</h2>
                    <span id="comm-unread-chip" class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $isAdmin ? 'bg-purple-100 text-purple-800 border border-purple-200' : 'bg-emerald-100 text-emerald-800 border border-emerald-200' }} {{ $unreadCount > 0 ? '' : 'hidden' }}">
                        <span id="comm-unread-num">{{ $unreadCount }}</span> unread
                    </span>
                </div>
                <p class="text-xs text-slate-500 font-medium">Client ↔ Admin · Booking #{{ $booking->id }}</p>
            </div>
        </div>

        <div class="flex items-center gap-2.5">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200/70 shadow-2xs">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <span id="comm-sync-status">Live Sync</span>
            </span>
            <button type="button" 
                    id="comm-refresh-btn"
                    title="Refresh conversation"
                    aria-label="Refresh messages"
                    class="p-2 text-slate-400 hover:text-slate-700 rounded-xl hover:bg-slate-100 active:bg-slate-200 transition">
                <i class="fa-solid fa-arrow-rotate-right text-xs transition-transform duration-300"></i>
            </button>
        </div>
    </div>

    {{-- SPECIAL WORKFLOW ACTION BANNERS --}}
    @if($booking->status === 'cancellation_requested')
        <div class="px-5 py-3.5 bg-rose-50/90 border-b border-rose-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="flex items-start gap-2.5">
                <div class="w-8 h-8 rounded-lg bg-rose-100 text-rose-700 flex items-center justify-center shrink-0 mt-0.5">
                    <i class="fa-solid fa-triangle-exclamation text-sm"></i>
                </div>
                <div>
                    <h3 class="text-xs sm:text-sm font-bold text-rose-900">Cancellation Request Pending Review</h3>
                    <p class="text-xs text-rose-700 mt-0.5">
                        {{ $isAdmin ? 'Client requested cancellation for this booking. Review reason below and determine approval.' : 'Your cancellation request is pending administrative review.' }}
                    </p>
                </div>
            </div>
            @if($isAdmin)
                <div class="flex items-center gap-2 shrink-0">
                    <form action="{{ route('admin.bookings.handle-cancellation', $booking) }}" method="POST" class="inline-flex gap-2">
                        @csrf
                        <input type="hidden" name="admin_note" value="">
                        <button type="submit" name="action" value="deny" class="px-3.5 py-1.5 bg-white text-rose-700 text-xs font-semibold border border-rose-300 rounded-lg hover:bg-rose-100 transition shadow-xs">
                            Deny Request
                        </button>
                        <button type="submit" name="action" value="approve" class="px-3.5 py-1.5 bg-rose-600 text-white text-xs font-bold rounded-lg hover:bg-rose-700 transition shadow-xs">
                            Approve Cancellation
                        </button>
                    </form>
                </div>
            @endif
        </div>
    @elseif($booking->status === 'change_requested')
        <div class="px-5 py-3.5 bg-amber-50/90 border-b border-amber-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="flex items-start gap-2.5">
                <div class="w-8 h-8 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center shrink-0 mt-0.5">
                    <i class="fa-solid fa-arrows-rotate text-sm"></i>
                </div>
                <div>
                    <h3 class="text-xs sm:text-sm font-bold text-amber-900">Quotation Changes Requested</h3>
                    <p class="text-xs text-amber-700 mt-0.5">
                        {{ $isAdmin ? 'Client requested adjustments to the proposal. Review the items in this thread and update the quotation.' : 'Your change request has been delivered to Admin. Review feedback below.' }}
                    </p>
                </div>
            </div>
            @if($isAdmin)
                <button type="button" onclick="if(window.switchTab) switchTab('tab-quotation'); setTimeout(() => document.getElementById('schedule-change-request')?.scrollIntoView({ block: 'center', behavior: 'smooth' }), 60);" class="px-3.5 py-1.5 bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold rounded-lg transition shadow-xs shrink-0">
                    <i class="fa-solid fa-file-signature mr-1.5"></i>Review &amp; Update Quotation
                </button>
            @endif
        </div>
    @endif

    {{-- CONVERSATION MESSAGES FEED --}}
    <div class="relative">
        <div id="comm-messages-scroll" 
             class="p-4 sm:p-6 max-h-[520px] min-h-[300px] overflow-y-auto space-y-4 bg-slate-50/40 scroll-smooth">
            
            <div id="comm-empty-state" class="{{ $bookingMessages->count() > 0 ? 'hidden' : '' }} py-16 text-center">
                <div class="w-14 h-14 mx-auto rounded-2xl bg-white border border-slate-200/80 text-slate-400 flex items-center justify-center text-xl mb-3 shadow-2xs">
                    <i class="fa-regular fa-comments"></i>
                </div>
                <h3 class="text-sm font-bold text-slate-700">No Messages Yet</h3>
                <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">This conversation is dedicated to Booking #{{ $booking->id }}. Write below to communicate directly between Client and Admin.</p>
            </div>

            <div id="comm-messages-container" class="space-y-4">
                @foreach($bookingMessages as $msg)
                    @php
                        $isSender = ($isAdmin && $msg->sender_type === 'admin') || (!$isAdmin && $msg->sender_type === 'client');
                        $senderLabel = $msg->sender_type === 'admin' 
                            ? ($isAdmin ? 'You (Admin)' : 'Raflora Admin')
                            : ($msg->sender_type === 'client' 
                                ? (!$isAdmin ? 'You' : $clientName) 
                                : 'Staff');
                        $msgText = $msg->message;
                        $isChangeRequest = str_starts_with(trim($msgText), 'Request Changes:');
                        $isCancellation = str_starts_with(trim($msgText), 'Requested cancellation:');

                        // Structured change request parser
                        $changeDetails = [];
                        if ($isChangeRequest) {
                            $lines = explode("\n", trim($msgText));
                            foreach ($lines as $line) {
                                if (str_starts_with($line, 'Type:')) $changeDetails['type'] = trim(substr($line, 5));
                                elseif (str_starts_with($line, 'Item/Material:')) $changeDetails['item'] = trim(substr($line, 14));
                                elseif (str_starts_with($line, 'Quantity:')) $changeDetails['quantity'] = trim(substr($line, 9));
                                elseif (str_starts_with($line, 'Schedule:')) $changeDetails['schedule'] = trim(substr($line, 9));
                                elseif (str_starts_with($line, 'Details:')) $changeDetails['details'] = trim(substr($line, 8));
                                elseif (str_starts_with($line, 'Reason:')) $changeDetails['reason'] = trim(substr($line, 7));
                            }
                        }
                    @endphp

                    <div class="flex {{ $isSender ? 'justify-end' : 'justify-start' }} message-item" data-id="{{ $msg->id }}">
                        <div class="max-w-xl w-full sm:w-auto sm:max-w-md md:max-w-lg lg:max-w-xl rounded-2xl p-4 transition-all duration-200 {{ $isSender 
                            ? ($isAdmin ? 'bg-purple-700 text-white rounded-tr-xs shadow-sm ring-1 ring-purple-600/50' : 'bg-emerald-600 text-white rounded-tr-xs shadow-sm ring-1 ring-emerald-500/50') 
                            : 'bg-white text-slate-800 border border-slate-200/90 rounded-tl-xs shadow-xs' }}">
                            
                            {{-- SENDER HEADER & TIMESTAMP --}}
                            <div class="flex items-center justify-between gap-4 mb-2 pb-1.5 border-b {{ $isSender ? ($isAdmin ? 'border-purple-600/60' : 'border-emerald-500/60') : 'border-slate-100' }}">
                                <div class="flex items-center gap-1.5 min-w-0">
                                    <span class="font-bold text-xs uppercase tracking-wider truncate {{ $isSender ? 'text-white' : ($msg->sender_type === 'admin' ? 'text-purple-900' : 'text-slate-800') }}">
                                        {{ $senderLabel }}
                                    </span>
                                    @if($isAdmin && $msg->visibility === 'admin_staff')
                                        <span class="text-[10px] font-semibold px-1.5 py-0.2 rounded {{ $isSender ? 'bg-purple-800/80 text-purple-200' : 'bg-slate-100 text-slate-600' }}" title="Internal Staff Note">
                                            Internal
                                        </span>
                                    @endif
                                </div>
                                <div class="flex items-center gap-1.5 text-[11px] shrink-0 {{ $isSender ? 'text-white/80' : 'text-slate-400' }}">
                                    <span>{{ $msg->created_at?->format('M j, g:i A') }}</span>
                                    @if($isSender && $msg->read_at)
                                        <i class="fa-solid fa-check-double text-[10px] text-white/90" title="Read by {{ $isAdmin ? 'Client' : 'Admin' }} at {{ $msg->read_at->format('M j, g:i A') }}"></i>
                                    @endif
                                </div>
                            </div>

                            {{-- MESSAGE CONTENT --}}
                            @if($isChangeRequest)
                                <div class="rounded-xl p-3 my-1 {{ $isSender ? 'bg-white/10 text-white border border-white/20' : 'bg-amber-50/80 border border-amber-200/90 text-amber-950' }}">
                                    <div class="flex items-center justify-between gap-2 mb-2 pb-1 border-b {{ $isSender ? 'border-white/15' : 'border-amber-200/60' }}">
                                        <span class="inline-flex items-center gap-1.5 font-bold text-xs uppercase tracking-wide {{ $isSender ? 'text-amber-200' : 'text-amber-900' }}">
                                            <i class="fa-solid fa-arrows-rotate"></i> Change Request
                                        </span>
                                        @if(!empty($changeDetails['type']))
                                            <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full {{ $isSender ? 'bg-white/20 text-white' : 'bg-amber-200/70 text-amber-900' }}">
                                                {{ $changeDetails['type'] }}
                                            </span>
                                        @endif
                                    </div>
                                    @if(!empty($changeDetails['item']) || !empty($changeDetails['quantity']))
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs mb-2">
                                            @if(!empty($changeDetails['item']))
                                                <div>
                                                    <span class="text-[11px] block opacity-75">Item / Material:</span>
                                                    <strong class="font-semibold">{{ $changeDetails['item'] }}</strong>
                                                </div>
                                            @endif
                                            @if(!empty($changeDetails['quantity']))
                                                <div>
                                                    <span class="text-[11px] block opacity-75">Quantity:</span>
                                                    <strong class="font-semibold">{{ $changeDetails['quantity'] }}</strong>
                                                </div>
                                            @endif
                                        </div>
                                    @endif
                                    @if(!empty($changeDetails['schedule']))
                                        <div class="text-xs mb-2">
                                            <span class="text-[11px] block opacity-75">Requested schedule:</span>
                                            <strong class="font-semibold">{{ $changeDetails['schedule'] }}</strong>
                                        </div>
                                    @endif
                                    @php
                                        $reasonText = $changeDetails['details'] ?? ($changeDetails['reason'] ?? null);
                                    @endphp
                                    @if($reasonText)
                                        <div class="text-xs pt-1.5 border-t {{ $isSender ? 'border-white/15' : 'border-amber-200/60' }}">
                                            <span class="text-[11px] block opacity-75">Reason / Details:</span>
                                            <span class="leading-relaxed">{{ $reasonText }}</span>
                                        </div>
                                    @else
                                        <div class="text-xs whitespace-pre-wrap leading-relaxed font-mono mt-1">{{ $msgText }}</div>
                                    @endif
                                </div>
                            @elseif($isCancellation)
                                <div class="rounded-xl p-3 my-1 {{ $isSender ? 'bg-white/10 text-white border border-white/20' : 'bg-rose-50/80 border border-rose-200/90 text-rose-950' }}">
                                    <div class="flex items-center gap-1.5 font-bold text-xs uppercase tracking-wide mb-1.5 {{ $isSender ? 'text-rose-200' : 'text-rose-900' }}">
                                        <i class="fa-solid fa-triangle-exclamation"></i> Cancellation Request
                                    </div>
                                    <div class="text-xs leading-relaxed">
                                        <span class="text-[11px] block opacity-75 mb-0.5">Submitted Reason:</span>
                                        {{ str_replace('Requested cancellation:', '', $msgText) }}
                                    </div>
                                </div>
                            @else
                                <div class="text-xs sm:text-sm whitespace-pre-wrap break-words leading-relaxed {{ $isSender ? 'text-white' : 'text-slate-700' }}">{{ $msgText }}</div>
                            @endif

                            {{-- QUOTATION CONTEXT REFERENCE --}}
                            @if($msg->related_quotation_version)
                                <div class="mt-2.5 pt-2 border-t {{ $isSender ? ($isAdmin ? 'border-purple-600/60' : 'border-emerald-500/60') : 'border-slate-100' }} flex items-center justify-between gap-2">
                                    <button type="button" 
                                            onclick="if(window.switchTab) { switchTab('tab-quotation'); } else { const q = document.getElementById('quotationState'); if(q) q.scrollIntoView({behavior: 'smooth'}); }" 
                                            title="View Quotation v{{ $msg->related_quotation_version }}"
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold {{ $isSender ? 'bg-white/20 text-white hover:bg-white/30' : 'bg-purple-50 text-purple-700 border border-purple-200 hover:bg-purple-100' }} transition">
                                        <i class="fa-solid fa-file-invoice text-xs"></i>
                                        <span>Ref: Quotation v{{ $msg->related_quotation_version }}</span>
                                        <i class="fa-solid fa-arrow-up-right-from-square text-[10px] opacity-70 ml-0.5"></i>
                                    </button>
                                </div>
                            @endif

                            {{-- ATTACHMENT --}}
                            @if($msg->attachment_path)
                                <div class="mt-2.5 pt-2 border-t {{ $isSender ? ($isAdmin ? 'border-purple-600/60' : 'border-emerald-500/60') : 'border-slate-100' }}">
                                    <span class="text-[10px] uppercase font-semibold tracking-wider block mb-1 {{ $isSender ? 'text-white/70' : 'text-slate-400' }}">
                                        Attachment ({{ ucwords(str_replace('_', ' ', $msg->attachment_category ?? 'document')) }})
                                    </span>
                                    <a href="{{ route('secure.attachment.show', $msg->id) }}" target="_blank" rel="noopener noreferrer" 
                                       class="inline-flex items-center gap-2 text-xs font-semibold px-2.5 py-1.5 rounded-lg {{ $isSender ? 'bg-white/20 hover:bg-white/30 text-white' : 'bg-slate-100 hover:bg-slate-200 text-slate-800' }} transition">
                                        <i class="fa-solid fa-paperclip"></i>
                                        <span class="truncate max-w-[220px]">{{ $msg->attachment_name ?? 'View Attachment' }}</span>
                                        <i class="fa-solid fa-arrow-up-right-from-square text-[10px] opacity-70"></i>
                                    </a>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- FLOATING JUMP-TO-LATEST PILL --}}
        <div id="comm-scroll-latest" 
             class="hidden absolute bottom-3 right-6 z-10">
            <button type="button" 
                    id="comm-scroll-latest-btn"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-900/90 hover:bg-slate-900 text-white text-xs font-bold rounded-full shadow-lg backdrop-blur-xs transition transform hover:scale-105 active:scale-95">
                <i class="fa-solid fa-arrow-down text-[10px]"></i>
                <span>Jump to latest</span>
            </button>
        </div>
    </div>

    {{-- COMPOSER --}}
    @if($isCommunicable)
        <div class="p-4 sm:p-5 border-t border-slate-200 bg-white">
            <form id="comm-composer-form" enctype="multipart/form-data" class="space-y-3">
                @csrf
                <input type="hidden" name="submission_key" id="comm-submission-key" value="">
                @if($isAdmin && $activeQuotation)
                    <input type="hidden" name="related_quotation_version" value="{{ $activeQuotation->version }}">
                @endif

                <div class="relative">
                    <label for="comm-message-text" class="sr-only">Type message</label>
                    <textarea id="comm-message-text" 
                              name="message" 
                              rows="3" 
                              maxlength="2000"
                              placeholder="{{ $isAdmin ? 'Write a message to ' . $clientName . ' (Press Ctrl+Enter to send)...' : 'Write a message to Raflora Admin (Press Ctrl+Enter to send)...' }}" 
                              class="w-full text-sm rounded-xl border border-slate-300 shadow-2xs focus:border-{{ $isAdmin ? 'purple' : 'emerald' }}-500 focus:ring-1 focus:ring-{{ $isAdmin ? 'purple' : 'emerald' }}-500 p-3.5 text-slate-800 resize-y transition placeholder:text-slate-400"
                              required></textarea>
                    <div class="flex items-center justify-end px-1 pt-1">
                        <span id="comm-char-count" class="text-[11px] text-slate-400 font-mono">0 / 2,000</span>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 pt-1">
                    {{-- Attachments & Visibility Controls --}}
                    <div class="flex flex-wrap items-center gap-2.5">
                        <div class="relative inline-flex items-center">
                            <label for="comm-visibility" class="sr-only">Visibility</label>
                            <select name="visibility" 
                                    id="comm-visibility" 
                                    class="text-xs font-semibold rounded-lg border border-slate-300 text-slate-700 py-1.5 px-2.5 focus:border-{{ $isAdmin ? 'purple' : 'emerald' }}-500 focus:ring-{{ $isAdmin ? 'purple' : 'emerald' }}-500 bg-slate-50 cursor-pointer">
                                @if($isAdmin)
                                    <option value="client_admin" selected>Client &amp; Admin</option>
                                    <option value="shared">Shared (Client, Admin, Staff)</option>
                                    <option value="admin_staff">Admin &amp; Staff (Internal)</option>
                                @else
                                    <option value="client_admin" selected>Admin (Private)</option>
                                    <option value="shared">Shared (Admin &amp; Staff)</option>
                                @endif
                            </select>
                        </div>

                        <div class="relative inline-flex items-center">
                            <label for="comm-file-input" 
                                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-300 text-slate-700 text-xs font-medium bg-slate-50 hover:bg-slate-100 cursor-pointer transition">
                                <i class="fa-solid fa-paperclip text-slate-500"></i>
                                <span id="comm-file-label" class="max-w-[140px] truncate">Attach File</span>
                            </label>
                            <input type="file" 
                                   id="comm-file-input" 
                                   name="attachment" 
                                   accept=".jpg,.jpeg,.png,.webp,.pdf" 
                                   class="sr-only">
                            <button type="button" 
                                    id="comm-file-clear" 
                                    title="Remove attachment" 
                                    class="hidden ml-1 text-slate-400 hover:text-rose-600 text-xs p-1">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </div>

                        <div class="relative inline-flex items-center">
                            <label for="comm-category" class="sr-only">Category</label>
                            <select name="attachment_category" 
                                    id="comm-category" 
                                    class="text-xs font-semibold rounded-lg border border-slate-300 text-slate-700 py-1.5 px-2.5 focus:border-{{ $isAdmin ? 'purple' : 'emerald' }}-500 focus:ring-{{ $isAdmin ? 'purple' : 'emerald' }}-500 bg-slate-50 cursor-pointer">
                                <option value="">No Attachment</option>
                                <option value="payment_proof">Payment Proof</option>
                                <option value="inspiration_reference">Inspiration / Reference</option>
                                <option value="proposal_quotation">Proposal / Quotation</option>
                                <option value="event_venue">Venue Document</option>
                                <option value="other_booking_document">Other Document</option>
                            </select>
                        </div>
                    </div>

                    {{-- Send Button --}}
                    <div class="flex items-center justify-end gap-2">
                        <button type="submit" 
                                id="comm-send-btn"
                                class="inline-flex items-center justify-center gap-2 px-6 py-2.5 rounded-xl text-sm font-bold text-white shadow-xs transition active:scale-[0.98] {{ $isAdmin ? 'bg-purple-700 hover:bg-purple-800' : 'bg-emerald-600 hover:bg-emerald-700' }}">
                            <i id="comm-send-icon" class="fa-solid fa-paper-plane text-xs"></i>
                            <span id="comm-send-text">Send Message</span>
                        </button>
                    </div>
                </div>

                {{-- Status / Error alert container --}}
                <div id="comm-alert" class="hidden text-xs rounded-xl p-3 font-medium transition-all"></div>
            </form>
        </div>
    @else
        <div class="p-5 text-center text-xs text-slate-500 border-t border-slate-100 bg-slate-50 rounded-b-2xl">
            <i class="fa-solid fa-box-archive mr-1.5 text-slate-400"></i> This booking is archived. Messaging is read-only.
        </div>
    @endif
</div>

{{-- CLIENT-SIDE REAL-TIME SCRIPT --}}
<script>
(function() {
    const root = document.getElementById('booking-conversation-root');
    if (!root) return;

    const bookingId = root.dataset.bookingId;
    const role = root.dataset.role;
    const fetchUrl = root.dataset.fetchUrl;
    const sendUrl = root.dataset.sendUrl;
    const readUrl = root.dataset.readUrl;

    const scrollBox = document.getElementById('comm-messages-scroll');
    const container = document.getElementById('comm-messages-container');
    const emptyState = document.getElementById('comm-empty-state');
    const form = document.getElementById('comm-composer-form');
    const messageInput = document.getElementById('comm-message-text');
    const charCount = document.getElementById('comm-char-count');
    const sendBtn = document.getElementById('comm-send-btn');
    const sendText = document.getElementById('comm-send-text');
    const sendIcon = document.getElementById('comm-send-icon');
    const alertBox = document.getElementById('comm-alert');
    const unreadChip = document.getElementById('comm-unread-chip');
    const unreadNum = document.getElementById('comm-unread-num');
    const syncStatus = document.getElementById('comm-sync-status');
    const refreshBtn = document.getElementById('comm-refresh-btn');
    const scrollLatestBox = document.getElementById('comm-scroll-latest');
    const scrollLatestBtn = document.getElementById('comm-scroll-latest-btn');
    const fileInput = document.getElementById('comm-file-input');
    const fileLabel = document.getElementById('comm-file-label');
    const fileClearBtn = document.getElementById('comm-file-clear');
    const catSelect = document.getElementById('comm-category');

    let isSending = false;
    let pollTimer = null;
    let knownMessageIds = new Set();

    // Populate initial known IDs
    document.querySelectorAll('#comm-messages-container .message-item').forEach(el => {
        const id = parseInt(el.dataset.id, 10);
        if (id) knownMessageIds.add(id);
    });

    function getCsrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || 
               document.querySelector('input[name="_token"]')?.value || '';
    }

    function generateUuid() {
        if (crypto.randomUUID) {
            return crypto.randomUUID();
        }
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
            var r = Math.random() * 16 | 0, v = c === 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }

    function isScrolledNearBottom() {
        if (!scrollBox) return true;
        return (scrollBox.scrollHeight - scrollBox.scrollTop - scrollBox.clientHeight) < 120;
    }

    function scrollToBottom(force = false) {
        if (!scrollBox) return;
        if (force || isScrolledNearBottom()) {
            scrollBox.scrollTop = scrollBox.scrollHeight;
            if (scrollLatestBox) scrollLatestBox.classList.add('hidden');
        } else {
            if (scrollLatestBox) scrollLatestBox.classList.remove('hidden');
        }
    }

    if (scrollBox) {
        scrollBox.addEventListener('scroll', () => {
            if (isScrolledNearBottom() && scrollLatestBox) {
                scrollLatestBox.classList.add('hidden');
            }
        });
    }

    if (scrollLatestBtn) {
        scrollLatestBtn.addEventListener('click', () => {
            scrollToBottom(true);
        });
    }

    // Character counter
    if (messageInput && charCount) {
        messageInput.addEventListener('input', () => {
            const len = messageInput.value.length;
            charCount.textContent = `${len.toLocaleString()} / 2,000`;
            if (len > 1900) {
                charCount.className = 'text-[11px] text-rose-500 font-bold font-mono';
            } else {
                charCount.className = 'text-[11px] text-slate-400 font-mono';
            }
        });
    }

    // Attachment file change handling
    if (fileInput && fileLabel) {
        fileInput.addEventListener('change', () => {
            const f = fileInput.files[0];
            if (f) {
                fileLabel.textContent = f.name;
                if (fileClearBtn) fileClearBtn.classList.remove('hidden');
                if (catSelect && !catSelect.value) {
                    catSelect.value = 'other_booking_document';
                }
            } else {
                fileLabel.textContent = 'Attach File';
                if (fileClearBtn) fileClearBtn.classList.add('hidden');
            }
        });
    }

    if (fileClearBtn && fileInput) {
        fileClearBtn.addEventListener('click', () => {
            fileInput.value = '';
            fileLabel.textContent = 'Attach File';
            fileClearBtn.classList.add('hidden');
            if (catSelect) catSelect.value = '';
        });
    }

    function showAlert(msg, isError = true) {
        if (!alertBox) return;
        alertBox.textContent = msg;
        alertBox.className = isError 
            ? 'text-xs rounded-xl p-3 font-medium bg-rose-50 text-rose-800 border border-rose-200 block'
            : 'text-xs rounded-xl p-3 font-medium bg-emerald-50 text-emerald-800 border border-emerald-200 block';
        setTimeout(() => {
            alertBox.className = 'hidden text-xs rounded-xl p-3 font-medium';
        }, 5000);
    }

    function updateUnreadBadges(count) {
        if (unreadChip && unreadNum) {
            if (count > 0) {
                unreadNum.textContent = count;
                unreadChip.classList.remove('hidden');
            } else {
                unreadChip.classList.add('hidden');
            }
        }
        // Launcher badges of the floating chat panels (Admin and client booking pages).
        document.querySelectorAll('#admin-comm-unread-badge, [data-chat-unread-badge]').forEach((badge) => {
            if (count > 0) {
                badge.textContent = count;
                badge.classList.remove('hidden');
            } else {
                badge.classList.add('hidden');
            }
        });
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }

    function renderMessageHtml(msg) {
        const isSender = (role === 'admin' && msg.sender_type === 'admin') || (role === 'client' && msg.sender_type === 'client');
        const isAdminRole = (role === 'admin');
        const bubbleBg = isSender 
            ? (isAdminRole ? 'bg-purple-700 text-white rounded-tr-xs shadow-sm ring-1 ring-purple-600/50' : 'bg-emerald-600 text-white rounded-tr-xs shadow-sm ring-1 ring-emerald-500/50')
            : 'bg-white text-slate-800 border border-slate-200/90 rounded-tl-xs shadow-xs';
        const senderColor = isSender ? 'text-white' : (msg.sender_type === 'admin' ? 'text-purple-900' : 'text-slate-800');
        const borderColor = isSender ? (isAdminRole ? 'border-purple-600/60' : 'border-emerald-500/60') : 'border-slate-100';

        const rawText = msg.message || '';
        const isChangeRequest = rawText.trim().startsWith('Request Changes:');
        const isCancellation = rawText.trim().startsWith('Requested cancellation:');

        let bodyHtml = '';
        if (isChangeRequest) {
            const lines = rawText.trim().split('\n');
            let type = '', item = '', qty = '', schedule = '', reason = '';
            lines.forEach(line => {
                if (line.startsWith('Type:')) type = line.substring(5).trim();
                else if (line.startsWith('Item/Material:')) item = line.substring(14).trim();
                else if (line.startsWith('Quantity:')) qty = line.substring(9).trim();
                else if (line.startsWith('Schedule:')) schedule = line.substring(9).trim();
                else if (line.startsWith('Details:')) reason = line.substring(8).trim();
                else if (line.startsWith('Reason:')) reason = line.substring(7).trim();
            });

            const typeBadge = type ? `<span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full ${isSender ? 'bg-white/20 text-white' : 'bg-amber-200/70 text-amber-900'}">${escapeHtml(type)}</span>` : '';
            const itemQtyGrid = (item || qty) ? `
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs mb-2">
                    ${item ? `<div><span class="text-[11px] block opacity-75">Item / Material:</span><strong class="font-semibold">${escapeHtml(item)}</strong></div>` : ''}
                    ${qty ? `<div><span class="text-[11px] block opacity-75">Quantity:</span><strong class="font-semibold">${escapeHtml(qty)}</strong></div>` : ''}
                </div>` : '';
            const reasonHtml = reason ? `
                <div class="text-xs pt-1.5 border-t ${isSender ? 'border-white/15' : 'border-amber-200/60'}">
                    <span class="text-[11px] block opacity-75">Reason / Details:</span>
                    <span class="leading-relaxed">${escapeHtml(reason)}</span>
                </div>` : `<div class="text-xs whitespace-pre-wrap leading-relaxed font-mono mt-1">${escapeHtml(rawText)}</div>`;

            bodyHtml = `
                <div class="rounded-xl p-3 my-1 ${isSender ? 'bg-white/10 text-white border border-white/20' : 'bg-amber-50/80 border border-amber-200/90 text-amber-950'}">
                    <div class="flex items-center justify-between gap-2 mb-2 pb-1 border-b ${isSender ? 'border-white/15' : 'border-amber-200/60'}">
                        <span class="inline-flex items-center gap-1.5 font-bold text-xs uppercase tracking-wide ${isSender ? 'text-amber-200' : 'text-amber-900'}">
                            <i class="fa-solid fa-arrows-rotate"></i> Change Request
                        </span>
                        ${typeBadge}
                    </div>
                    ${itemQtyGrid}
                    ${schedule ? `<div class="text-xs mb-2"><span class="text-[11px] block opacity-75">Requested schedule:</span><strong class="font-semibold">${escapeHtml(schedule)}</strong></div>` : ''}
                    ${reasonHtml}
                </div>`;
        } else if (isCancellation) {
            const reasonClean = rawText.replace('Requested cancellation:', '').trim();
            bodyHtml = `
                <div class="rounded-xl p-3 my-1 ${isSender ? 'bg-white/10 text-white border border-white/20' : 'bg-rose-50/80 border border-rose-200/90 text-rose-950'}">
                    <div class="flex items-center gap-1.5 font-bold text-xs uppercase tracking-wide mb-1.5 ${isSender ? 'text-rose-200' : 'text-rose-900'}">
                        <i class="fa-solid fa-triangle-exclamation"></i> Cancellation Request
                    </div>
                    <div class="text-xs leading-relaxed">
                        <span class="text-[11px] block opacity-75 mb-0.5">Submitted Reason:</span>
                        ${escapeHtml(reasonClean)}
                    </div>
                </div>`;
        } else {
            bodyHtml = `<div class="text-xs sm:text-sm whitespace-pre-wrap break-words leading-relaxed ${isSender ? 'text-white' : 'text-slate-700'}">${escapeHtml(rawText)}</div>`;
        }

        let quoteRefHtml = '';
        if (msg.related_quotation_version) {
            quoteRefHtml = `
                <div class="mt-2.5 pt-2 border-t ${borderColor} flex items-center justify-between gap-2">
                    <button type="button" 
                            onclick="if(window.switchTab) { switchTab('tab-quotation'); } else { const q = document.getElementById('quotationState'); if(q) q.scrollIntoView({behavior: 'smooth'}); }" 
                            title="View Quotation v${escapeHtml(msg.related_quotation_version)}"
                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold ${isSender ? 'bg-white/20 text-white hover:bg-white/30' : 'bg-purple-50 text-purple-700 border border-purple-200 hover:bg-purple-100'} transition">
                        <i class="fa-solid fa-file-invoice text-xs"></i>
                        <span>Ref: Quotation v${escapeHtml(msg.related_quotation_version)}</span>
                        <i class="fa-solid fa-arrow-up-right-from-square text-[10px] opacity-70 ml-0.5"></i>
                    </button>
                </div>`;
        }

        let attachHtml = '';
        if (msg.has_attachment && msg.attachment_url) {
            attachHtml = `
                <div class="mt-2.5 pt-2 border-t ${borderColor}">
                    <span class="text-[10px] uppercase font-semibold tracking-wider block mb-1 ${isSender ? 'text-white/70' : 'text-slate-400'}">
                        Attachment (${escapeHtml(msg.attachment_category_label || 'Document')})
                    </span>
                    <a href="${escapeHtml(msg.attachment_url)}" target="_blank" rel="noopener noreferrer" 
                       class="inline-flex items-center gap-2 text-xs font-semibold px-2.5 py-1.5 rounded-lg ${isSender ? 'bg-white/20 hover:bg-white/30 text-white' : 'bg-slate-100 hover:bg-slate-200 text-slate-800'} transition">
                        <i class="fa-solid fa-paperclip"></i>
                        <span class="truncate max-w-[220px]">${escapeHtml(msg.attachment_name || 'View Attachment')}</span>
                        <i class="fa-solid fa-arrow-up-right-from-square text-[10px] opacity-70"></i>
                    </a>
                </div>`;
        }

        const readCheck = (isSender && msg.is_read) 
            ? '<i class="fa-solid fa-check-double text-[10px] text-white/90"></i>' 
            : '';

        const internalBadge = (isAdminRole && msg.visibility === 'admin_staff')
            ? `<span class="text-[10px] font-semibold px-1.5 py-0.2 rounded ${isSender ? 'bg-purple-800/80 text-purple-200' : 'bg-slate-100 text-slate-600'}" title="Internal Staff Note">Internal</span>`
            : '';

        return `
            <div class="flex ${isSender ? 'justify-end' : 'justify-start'} message-item" data-id="${msg.id}">
                <div class="max-w-xl w-full sm:w-auto sm:max-w-md md:max-w-lg lg:max-w-xl rounded-2xl p-4 transition-all duration-200 ${bubbleBg}">
                    <div class="flex items-center justify-between gap-4 mb-2 pb-1.5 border-b ${borderColor}">
                        <div class="flex items-center gap-1.5 min-w-0">
                            <span class="font-bold text-xs uppercase tracking-wider truncate ${senderColor}">
                                ${escapeHtml(msg.sender_label)}
                            </span>
                            ${internalBadge}
                        </div>
                        <div class="flex items-center gap-1.5 text-[11px] shrink-0 ${isSender ? 'text-white/80' : 'text-slate-400'}">
                            <span>${escapeHtml(msg.created_at_human)}</span>
                            ${readCheck}
                        </div>
                    </div>
                    ${bodyHtml}
                    ${quoteRefHtml}
                    ${attachHtml}
                </div>
            </div>`;
    }

    async function fetchMessages(isManual = false) {
        if (!fetchUrl) return;
        try {
            if (syncStatus && isManual) syncStatus.textContent = 'Syncing...';
            if (refreshBtn && isManual) {
                const icon = refreshBtn.querySelector('i');
                if (icon) icon.classList.add('rotate-180');
            }
            const res = await fetch(fetchUrl, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': getCsrfToken()
                }
            });

            if (!res.ok) {
                if (res.status === 403) {
                    console.warn('Booking communication unauthorized.');
                    stopPolling();
                }
                return;
            }

            const data = await res.json();
            if (data.success && Array.isArray(data.messages)) {
                let hasNew = false;
                data.messages.forEach(msg => {
                    if (!knownMessageIds.has(msg.id)) {
                        knownMessageIds.add(msg.id);
                        hasNew = true;
                        if (emptyState) emptyState.classList.add('hidden');
                        const msgHtml = renderMessageHtml(msg);
                        container.insertAdjacentHTML('beforeend', msgHtml);
                    }
                });

                if (hasNew) {
                    scrollToBottom(false);
                }

                updateUnreadBadges(data.unread_count || 0);
            }
        } catch (e) {
            console.error('Failed to sync booking messages:', e);
        } finally {
            if (syncStatus) syncStatus.textContent = 'Live Sync';
            if (refreshBtn && isManual) {
                const icon = refreshBtn.querySelector('i');
                if (icon) {
                    setTimeout(() => icon.classList.remove('rotate-180'), 300);
                }
            }
        }
    }

    // Ctrl+Enter or Cmd+Enter shortcut
    if (messageInput && form) {
        messageInput.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                e.preventDefault();
                form.requestSubmit();
            }
        });
    }

    // SUBMIT MESSAGE
    if (form) {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            if (isSending) return;

            const message = (messageInput.value || '').trim();
            if (!message) {
                showAlert('Please enter a message before sending.');
                return;
            }

            isSending = true;
            sendBtn.disabled = true;
            sendBtn.classList.add('opacity-50', 'pointer-events-none');
            if (sendIcon) sendIcon.className = 'fa-solid fa-circle-notch fa-spin text-xs';
            sendText.textContent = 'Sending...';

            const submissionKey = generateUuid();
            document.getElementById('comm-submission-key').value = submissionKey;

            const formData = new FormData(form);

            try {
                const res = await fetch(sendUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': getCsrfToken()
                    },
                    body: formData
                });

                const data = await res.json();

                if (res.ok && data.success) {
                    messageInput.value = '';
                    if (charCount) charCount.textContent = '0 / 2,000';
                    if (fileInput) fileInput.value = '';
                    if (fileLabel) fileLabel.textContent = 'Attach File';
                    if (fileClearBtn) fileClearBtn.classList.add('hidden');
                    if (catSelect) catSelect.value = '';

                    if (data.message && !knownMessageIds.has(data.message.id)) {
                        knownMessageIds.add(data.message.id);
                        if (emptyState) emptyState.classList.add('hidden');
                        container.insertAdjacentHTML('beforeend', renderMessageHtml(data.message));
                        scrollToBottom(true);
                    }
                } else {
                    const errorMsg = data.error || (data.errors ? Object.values(data.errors).flat().join(' ') : 'Failed to send message.');
                    showAlert(errorMsg, true);
                }
            } catch (err) {
                console.error('Error sending message:', err);
                showAlert('Network error while sending message. Please try again.', true);
            } finally {
                isSending = false;
                sendBtn.disabled = false;
                sendBtn.classList.remove('opacity-50', 'pointer-events-none');
                if (sendIcon) sendIcon.className = 'fa-solid fa-paper-plane text-xs';
                sendText.textContent = 'Send Message';
            }
        });
    }

    if (refreshBtn) {
        refreshBtn.addEventListener('click', () => fetchMessages(true));
    }

    function startPolling() {
        if (pollTimer) clearInterval(pollTimer);
        pollTimer = setInterval(() => {
            if (document.visibilityState === 'visible') {
                fetchMessages(false);
            }
        }, 3500);
    }

    function stopPolling() {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            fetchMessages(false);
        }
    });

    // Initial scroll & poll start
    scrollToBottom(true);
    startPolling();
})();
</script>
