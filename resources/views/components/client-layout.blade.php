@props(['active' => 'dashboard', 'plain' => true])

<div class="min-h-[100dvh] w-full overflow-x-hidden flex flex-col relative bg-slate-50">
    @unless($plain)
        <div class="fixed inset-0 -z-10 bg-cover bg-center" style="background-image: url('{{ asset('assets/images/background.jpg') }}');"></div>
        <div class="fixed inset-0 -z-10 bg-slate-950/55"></div>
    @endunless

    @php
        $pageTitle = match($active) {
            'dashboard' => 'Dashboard',
            'bookings' => 'My Bookings',
            'booking-history' => 'History',
            'notifications' => 'Notifications',
            'account-settings' => 'Account',
            default => null
        };
    @endphp

    <x-navbar :title="$pageTitle" />

    <div class="flex flex-1 relative z-10">
        <main class="mx-auto w-full max-w-7xl flex-1 p-4 sm:p-6 lg:p-8" aria-label="Client content">
            {{-- Flash Messages --}}
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

            {{ $slot }}
        </main>
    </div>
</div>
