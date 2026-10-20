<x-admin-layout title="Edit Gallery">
    <div class="mb-6 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.gallery') }}" class="w-10 h-10 rounded-full flex items-center justify-center bg-white border border-gray-200 text-gray-500 hover:text-brand-800 hover:bg-brand-50 transition">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <div>
                <h2 class="text-xl font-bold text-gray-800">Edit Gallery</h2>
                <p class="text-sm text-gray-500">Update the details and images of this gallery.</p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden max-w-3xl">
        <form action="{{ route('admin.gallery.update', $gallery) }}" method="POST" enctype="multipart/form-data" class="p-6 sm:p-8 space-y-6">
            @csrf
            @method('PUT')
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="title" class="block text-sm font-semibold text-gray-700 mb-1">Event Title <span class="text-red-500">*</span></label>
                    <input type="text" name="title" id="title" value="{{ old('title', $gallery->title) }}" required class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-600 transition shadow-sm" placeholder="e.g. Manila Hotel Elegant Wedding">
                    @error('title') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                
                <div>
                    <label for="event_date" class="block text-sm font-semibold text-gray-700 mb-1">Event Date <span class="text-red-500">*</span></label>
                    <input type="date" name="event_date" id="event_date" value="{{ old('event_date', $gallery->event_date) }}" max="{{ now()->toDateString() }}" required class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-600 transition shadow-sm">
                    @error('event_date') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="event_type" class="block text-sm font-semibold text-gray-700 mb-1">Event Type <span class="text-red-500">*</span></label>
                    <input type="text" name="event_type" id="event_type" value="{{ old('event_type', $gallery->event_type) }}" required class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-600 transition shadow-sm" placeholder="e.g. Wedding, Debut">
                    @error('event_type') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                
                <div>
                    <label for="theme" class="block text-sm font-semibold text-gray-700 mb-1">Theme <span class="text-red-500">*</span></label>
                    <input type="text" name="theme" id="theme" value="{{ old('theme', $gallery->theme) }}" required class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-600 transition shadow-sm" placeholder="e.g. Elegant, Modern, Rustic">
                    @error('theme') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Existing Images</label>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4 mb-4">
                    @foreach($gallery->images as $image)
                        <div class="relative group rounded-lg overflow-hidden border border-gray-200">
                            <img src="{{ str_starts_with($image->image_path, 'assets/') ? asset($image->image_path) : asset('storage/' . $image->image_path) }}" alt="Gallery Image" class="w-full h-24 object-cover">
                        </div>
                    @endforeach
                </div>
                
                <label for="images" class="block text-sm font-semibold text-gray-700 mb-1">Add More Images <span class="text-gray-400 font-normal">(Optional)</span></label>
                <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-lg bg-gray-50 hover:bg-gray-100 transition relative">
                    <div class="space-y-1 text-center">
                        <i class="fa-solid fa-cloud-arrow-up text-3xl text-gray-400"></i>
                        <div class="flex text-sm text-gray-600 justify-center">
                            <label for="images" class="relative cursor-pointer bg-white rounded-md font-medium text-brand-700 hover:text-brand-800 focus-within:outline-none px-1">
                                <span>Upload files</span>
                                <input id="images" name="images[]" type="file" class="sr-only" multiple accept="image/jpeg,image/png,image/jpg,image/gif">
                            </label>
                            <p class="pl-1">or drag and drop</p>
                        </div>
                        <p class="text-xs text-gray-500">PNG, JPG, GIF up to 5MB (Multiple files allowed)</p>
                    </div>
                </div>
                <div id="file-list" class="mt-3 text-sm text-gray-600 space-y-1"></div>
                @error('images') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                @error('images.*') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="pt-4 border-t border-gray-100 flex justify-end gap-3">
                <a href="{{ route('admin.gallery') }}" class="px-5 py-2.5 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition shadow-sm">Cancel</a>
                <button type="submit" class="btn-primary px-6">Save Gallery</button>
            </div>
        </form>
    </div>

    <script>
        document.getElementById('images').addEventListener('change', function(e) {
            const fileList = document.getElementById('file-list');
            fileList.innerHTML = '';
            for (let i = 0; i < this.files.length; i++) {
                const p = document.createElement('p');
                p.className = 'text-sm text-green-700 bg-green-50 px-3 py-1.5 rounded-md inline-flex items-center gap-2 mr-2 mb-2 border border-green-200';
                p.innerHTML = '<i class="fa-solid fa-image"></i> ' + this.files[i].name;
                fileList.appendChild(p);
            }
        });
    </script>
</x-admin-layout>
