<div data-profile-crop-modal class="fixed inset-0 z-[100] hidden items-center justify-center bg-slate-950/70 p-4" role="dialog" aria-modal="true" aria-labelledby="profileCropTitle">
    <div class="w-full max-w-md rounded-2xl bg-white p-5 shadow-2xl sm:p-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 id="profileCropTitle" class="text-lg font-semibold text-slate-900">Crop your profile photo</h2>
                <p class="mt-1 text-sm text-slate-500">Drag to adjust the photo, then zoom as needed.</p>
            </div>
            <button type="button" data-profile-crop-cancel class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600" aria-label="Cancel photo crop">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
        </div>

        <div class="mx-auto mt-5 h-64 w-64 max-w-full overflow-hidden rounded-full bg-slate-100">
            <canvas data-profile-crop-canvas width="512" height="512" class="h-full w-full touch-none" aria-label="Profile photo crop preview"></canvas>
        </div>
        <label class="mt-5 block text-sm font-medium text-slate-700" for="profileCropZoom">Zoom</label>
        <input data-profile-crop-zoom id="profileCropZoom" type="range" min="1" max="3" step="0.01" value="1" class="mt-2 w-full accent-emerald-700">
        <p data-profile-crop-error class="mt-2 min-h-5 text-sm text-red-600" role="status" aria-live="polite"></p>

        <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <button type="button" data-profile-crop-cancel class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600">Cancel</button>
            <button type="button" data-profile-crop-apply class="rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2">Use cropped photo</button>
        </div>
    </div>
</div>
