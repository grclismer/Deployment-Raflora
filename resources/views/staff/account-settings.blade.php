<x-staff-layout title="Account Settings">
    @php
        $profileImage = $user->profile_image && \Storage::disk('public')->exists($user->profile_image)
            ? asset('storage/' . $user->profile_image)
            : null;
        $initials = collect(preg_split('/\s+/', trim((string) $user->name)))
            ->filter()
            ->take(2)
            ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
    @endphp

    <section class="mx-auto max-w-2xl rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-8" aria-labelledby="staff-profile-heading">
        <div class="mb-6">
            <h2 id="staff-profile-heading" class="text-lg font-semibold text-slate-900">Profile photo</h2>
            <p class="mt-1 text-sm text-slate-500">Choose and crop a photo to personalize your staff profile.</p>
        </div>

        @if(session('success'))
            <p class="mb-5 rounded-xl bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800" role="status">{{ session('success') }}</p>
        @endif
        @if($errors->any())
            <div class="mb-5 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
                <ul class="list-disc space-y-1 pl-5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('staff.profile.photo.update') }}" enctype="multipart/form-data">
            @csrf
            <div class="flex flex-col items-center gap-5 sm:flex-row sm:items-start">
                <div class="relative h-24 w-24 shrink-0">
                    <img @if($profileImage) src="{{ $profileImage }}" @endif data-profile-image-preview alt="Profile image for {{ $user->name }}" class="h-24 w-24 rounded-full border border-slate-200 object-cover {{ $profileImage ? '' : 'hidden' }}">
                    <div data-profile-image-fallback class="flex h-24 w-24 items-center justify-center rounded-full bg-rose-50 text-xl font-bold text-rose-700 ring-1 ring-rose-100 {{ $profileImage ? 'hidden' : '' }}" aria-hidden="true">
                        {{ $initials ?: 'S' }}
                    </div>
                </div>
                <label class="block w-full cursor-pointer rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-5 text-center transition hover:border-rose-400 hover:bg-rose-50/40 sm:text-left">
                    <span class="block text-sm font-semibold text-slate-800">Choose a photo to crop</span>
                    <span class="mt-1 block text-xs text-slate-500">JPG, PNG or WEBP. Maximum size 2 MB.</span>
                    <input type="file" name="profile_image" data-profile-image-input accept="image/jpeg,image/png,image/webp" class="sr-only">
                    <span data-profile-image-input-error class="mt-2 block text-sm text-red-600" role="alert"></span>
                </label>
            </div>
            <div class="mt-6 flex justify-end">
                <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-rose-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-rose-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-600 focus-visible:ring-offset-2">
                    Save profile photo
                </button>
            </div>
            <x-profile-image-cropper />
        </form>
    </section>
</x-staff-layout>
