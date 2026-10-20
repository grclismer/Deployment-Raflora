<x-admin-layout title="Archived Inventory">
    <div class="mb-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.inventory.index') }}" class="text-gray-400 hover:text-brand-800 transition">
                    <i class="fa-solid fa-arrow-left"></i>
                </a>
                <h2 class="text-xl font-bold text-gray-800">Archived Inventory</h2>
            </div>
            <p class="text-sm text-gray-500 mt-1">Historically preserved items removed from active inventory.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
            <span class="block sm:inline">{{ session('success') }}</span>
        </div>
    @endif
    @if(session('error'))
        <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
            <span class="block sm:inline">{{ session('error') }}</span>
        </div>
    @endif

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-8">
        <div class="overflow-x-auto">
            <table class="rf-table--stack w-full table-auto text-left min-w-[800px]">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Item Name</th>
                        <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Category</th>
                        <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider text-right">Last Stock</th>
                        <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider text-right">Archived Date</th>
                        <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    @forelse($inventoryItems as $item)
                        <tr class="hover:bg-slate-50 transition">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="h-10 w-10 flex-shrink-0 bg-gray-100 rounded overflow-hidden flex items-center justify-center">
                                        @if($item->image_path)
                                            <img src="{{ Storage::url($item->image_path) }}" alt="{{ $item->name }}" class="h-full w-full object-cover grayscale opacity-50">
                                        @else
                                            <i class="fa-solid fa-box-open text-gray-400"></i>
                                        @endif
                                    </div>
                                    <div>
                                        <p class="text-sm font-semibold text-gray-500">{{ $item->name }}</p>
                                        <p class="text-xs text-gray-400">{{ $item->item_code }}</p>
                                        @if($item->is_perishable)
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-gray-200 text-gray-500 mt-1">Perishable</span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td data-label="Category" class="px-6 py-4 text-sm text-gray-400">
                                {{ $item->category }}
                            </td>
                            <td data-label="Last Stock" class="px-6 py-4 text-sm text-gray-400 text-right">
                                {{ rtrim(rtrim($item->current_stock, '0'), '.') }}
                            </td>
                            <td data-label="Archived" class="px-6 py-4 text-sm text-gray-400 text-right">
                                {{ $item->deleted_at->format('M d, Y') }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                <form action="{{ route('admin.inventory.restore', $item->id) }}" method="POST" class="inline-block" data-confirm="Restore this item?" data-confirm-message="It returns to active inventory with its history." data-confirm-label="Restore item" data-confirm-tone="restore">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center justify-center px-3 py-1.5 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-brand-50 hover:text-brand-800 hover:border-brand-200 transition" title="Restore Item">
                                        <i class="fa-solid fa-rotate-left mr-1.5"></i> Restore
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center justify-center">
                                    <div class="h-12 w-12 text-gray-200 mb-3"><i class="fa-solid fa-box-archive text-4xl"></i></div>
                                    <p class="text-gray-500 font-medium">No archived items found.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-admin-layout>
