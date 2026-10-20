<x-admin-layout title="Add Package">
    <div class="mb-6 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.packages.index') }}" class="w-10 h-10 rounded-full flex items-center justify-center bg-white border border-gray-200 text-gray-500 hover:text-brand-800 hover:bg-brand-50 transition">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <div>
                <h2 class="text-xl font-bold text-gray-800">Add New Package</h2>
                <p class="text-sm text-gray-500">Create a new package with pricing and optional inventory mapping.</p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden max-w-4xl">
        <form action="{{ route('admin.packages.store') }}" method="POST" enctype="multipart/form-data" class="p-6 sm:p-8 space-y-8" onkeydown="if(event.key === 'Enter' && event.target.tagName !== 'TEXTAREA') { event.preventDefault(); }">
            @csrf
            
            <!-- SECTION 1 — PACKAGE INFORMATION -->
            <div>
                <h4 class="text-xs font-bold tracking-widest uppercase text-slate-400 mb-4 border-b border-slate-100 pb-2">1. Package Information</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Package Name <span class="text-red-500">*</span></label>
                        <input type="text" name="title" value="{{ old('title') }}" required class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-600 transition shadow-sm" placeholder="e.g. Classic Wedding Package">
                        @error('title') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Category <span class="text-red-500">*</span></label>
                        <input type="text" name="category" value="{{ old('category') }}" required placeholder="e.g. Wedding, Birthday" maxlength="50" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-600 transition shadow-sm">
                        @error('category') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Package Price (₱) <span class="text-red-500">*</span></label>
                        <input type="number" step="0.01" min="0" name="price" value="{{ old('price') }}" required class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-600 transition shadow-sm" placeholder="0.00">
                        <p class="text-xs text-gray-500 mt-1">Must be 0 or greater.</p>
                        @error('price') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Package Code</label>
                        <input type="text" readonly disabled placeholder="Generated automatically upon save" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-gray-50 text-gray-500 cursor-not-allowed shadow-sm">
                        <p class="text-xs text-gray-500 mt-1">Generated automatically when the package is created. It remains unchanged if the package name is edited.</p>
                    </div>
                </div>
            </div>

            <!-- SECTION 2 — DESCRIPTION -->
            <div>
                <h4 class="text-xs font-bold tracking-widest uppercase text-slate-400 mb-4 border-b border-slate-100 pb-2">2. Description</h4>
                <div>
                    <textarea name="description" rows="3" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-600 transition shadow-sm resize-y" placeholder="Brief description of the package">{{ old('description') }}</textarea>
                    @error('description') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <!-- SECTION 3 — CLIENT-FACING INCLUSIONS -->
            <div>
                <h4 class="text-xs font-bold tracking-widest uppercase text-slate-400 mb-4 border-b border-slate-100 pb-2">3. Client-Facing Inclusions (Highlights & Services)</h4>
                <div>
                    <input type="text" name="included_items" value="{{ old('included_items') }}" placeholder="e.g. Bridal Bouquet, 10 Table Centerpieces, Floral Archway, On-site Styling" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-600 transition shadow-sm">
                    <p class="text-xs text-gray-500 mt-1">Deliverables shown to clients when browsing packages on the website. Use a comma-separated list.</p>
                    @error('included_items') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <!-- SECTION 4 — PACKAGE IMAGES -->
            <div>
                <h4 class="text-xs font-bold tracking-widest uppercase text-slate-400 mb-4 border-b border-slate-100 pb-2">4. Package Images</h4>
                <div>
                    <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-lg bg-gray-50 hover:bg-gray-100 transition relative">
                        <div class="space-y-1 text-center">
                            <i class="fa-solid fa-cloud-arrow-up text-3xl text-gray-400"></i>
                            <div class="flex text-sm text-gray-600 justify-center">
                                <label for="images" class="relative cursor-pointer bg-white rounded-md font-medium text-brand-700 hover:text-brand-800 focus-within:outline-none px-1">
                                    <span>Upload Images</span>
                                    <input id="images" name="images[]" type="file" multiple class="sr-only" accept="image/jpeg,image/png,image/jpg,image/gif">
                                </label>
                                <p class="pl-1">or drag and drop</p>
                            </div>
                            <p class="text-xs text-gray-500">PNG, JPG, GIF up to 5MB</p>
                        </div>
                    </div>
                    <div id="file-list" class="mt-3 text-sm text-gray-600 space-y-1"></div>
                    @error('images') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    @error('images.*') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <!-- SECTION 5 — MATERIALS FROM INVENTORY (AUTHORITATIVE BOM) -->
            <div>
                <h4 class="text-xs font-bold tracking-widest uppercase text-slate-400 mb-4 border-b border-slate-100 pb-2">5. Materials From Inventory (Authoritative BOM)</h4>
                <p class="text-xs text-gray-500 mb-3">Select inventory items required to prepare one unit of this package. Package material quantities represent preparation requirements per booking and do not reserve or deduct current inventory stock.</p>
                
                <div class="flex gap-3 mb-3">
                    <div class="flex-1">
                        <input type="text" id="add_inv_search" onkeyup="filterInventory('add')" placeholder="Search inventory materials by name or item code..." class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-500 focus:border-brand-600 transition shadow-sm">
                    </div>
                    <div class="w-1/3">
                        <select id="add_inv_category" onchange="filterInventory('add')" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-500 focus:border-brand-600 transition shadow-sm">
                            <option value="">All Categories</option>
                            @foreach($inventoryCategories as $cat)
                                <option value="{{ $cat }}">{{ $cat }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                
                <!-- Inventory Catalogue Selection -->
                <div class="border border-gray-200 rounded-xl p-3 bg-gray-50 block overflow-y-auto overflow-x-hidden" style="max-height: 320px;">
                    <div class="space-y-2">
                        @foreach($inventoryItems as $item)
                            <div class="flex items-center justify-between group py-2 px-2.5 rounded-lg bg-white border border-gray-100 hover:border-brand-200 hover:bg-brand-50/20 transition add-inv-row" 
                                 id="add_inv_row_{{ $item->id }}"
                                 data-id="{{ $item->id }}"
                                 data-name="{{ $item->name }}" 
                                 data-code="{{ $item->item_code ?? 'N/A' }}" 
                                 data-category="{{ $item->category }}"
                                 data-unit="{{ $item->unit }}"
                                 data-stock="{{ (float)$item->current_stock }}">
                                <div class="flex items-center gap-3 pr-3 flex-1 min-w-0">
                                    <input type="checkbox" id="add_inv_check_{{ $item->id }}" class="h-4 w-4 rounded border-gray-300 text-brand-700 focus:ring-brand-500 add-inv-check cursor-pointer" onchange="toggleInvQty('add', {{ $item->id }})">
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <p class="text-[13px] font-semibold text-gray-800 truncate">{{ $item->name }}</p>
                                            <span class="font-mono text-[10px] bg-slate-100 text-slate-600 px-1.5 py-0.5 rounded border border-slate-200">{{ $item->item_code ?? 'N/A' }}</span>
                                        </div>
                                        <div class="flex items-center gap-2 mt-0.5 text-[11px] text-gray-500">
                                            <span>{{ $item->category }}</span>
                                            <span>•</span>
                                            <span class="text-slate-600">Available: <strong class="text-slate-800 font-semibold">{{ (float)$item->current_stock }}</strong> {{ $item->unit }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <label class="text-xs text-gray-500 hidden sm:block">Package Qty:</label>
                                    <input type="number" name="inventory_items[{{ $item->id }}]" id="add_inv_qty_{{ $item->id }}" min="0.01" step="0.01" placeholder="0" disabled oninput="updateSelectedMaterialsTable('add')" class="w-24 px-3 py-1.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-600 transition shadow-sm text-sm disabled:opacity-50 disabled:bg-gray-100">
                                    <span class="text-[13px] text-gray-500 w-12">{{ $item->unit }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Selected Materials Table (Bill of Materials) -->
                <div class="mt-4 border border-gray-200 rounded-xl bg-white overflow-hidden shadow-sm">
                    <div class="px-4 py-3 bg-slate-50 border-b border-gray-200 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <i class="fa-solid fa-list-check text-brand-700 text-xs"></i>
                            <h5 class="text-xs font-bold uppercase tracking-wider text-slate-700">Selected Materials (Bill of Materials)</h5>
                        </div>
                        <span class="text-xs font-semibold text-brand-700" id="add_inv_summary">0 materials selected</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-100/75 text-slate-600 font-semibold border-b border-gray-200">
                                <tr>
                                    <th class="px-4 py-2.5">Material</th>
                                    <th class="px-3 py-2.5">Item Code</th>
                                    <th class="px-3 py-2.5">Unit</th>
                                    <th class="px-3 py-2.5">Current Available Stock</th>
                                    <th class="px-3 py-2.5">Package Qty</th>
                                    <th class="px-3 py-2.5 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody id="add_selected_materials_tbody" class="divide-y divide-gray-100">
                                <tr id="add_empty_row">
                                    <td colspan="6" class="px-4 py-6 text-center text-slate-400 italic">
                                        No inventory materials selected yet. Search and check items from inventory above to add them to this package.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mt-3 p-3 bg-brand-50/60 border border-brand-100 rounded-lg text-xs text-brand-700 flex items-start gap-2">
                    <i class="fa-solid fa-circle-info mt-0.5 text-brand-700"></i>
                    <span><strong>Inventory Rule:</strong> Package creation defines required materials only. Current inventory stock will not be deducted or reserved until an event booking is confirmed and prepared.</span>
                </div>

                @if($errors->has('inventory_items.*') || $errors->has('inventory_items'))
                    <div class="mt-3 p-3 bg-red-50 border border-red-200 rounded-lg text-red-600 text-xs space-y-1">
                        <p class="font-semibold">There was an error with your inventory mappings:</p>
                        @foreach($errors->get('inventory_items.*') as $msg)
                            <div class="list-disc list-inside">{{ $msg[0] }}</div>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- SECTION 6 — PACKAGE STATUS -->
            <div>
                <h4 class="text-xs font-bold tracking-widest uppercase text-slate-400 mb-4 border-b border-slate-100 pb-2">6. Package Status</h4>
                <div class="flex items-center gap-3">
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" class="sr-only peer" checked>
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-brand-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-brand-700"></div>
                        <span class="ml-3 text-sm font-medium text-gray-700">Active (Visible to Clients)</span>
                    </label>
                </div>
            </div>

            <div class="pt-4 border-t border-gray-100 flex justify-end gap-3">
                <a href="{{ route('admin.packages.index') }}" class="px-5 py-2.5 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition shadow-sm">Cancel</a>
                <button type="submit" class="btn-primary px-6 py-2.5 rounded-lg shadow-sm">Save Package</button>
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

        function toggleInvQty(mode, id) {
            const checkbox = document.getElementById(mode + '_inv_check_' + id);
            const qtyInput = document.getElementById(mode + '_inv_qty_' + id);
            if (checkbox.checked) {
                qtyInput.disabled = false;
                qtyInput.required = true;
                if (!qtyInput.value || parseFloat(qtyInput.value) <= 0) {
                    qtyInput.value = '1';
                }
            } else {
                qtyInput.disabled = true;
                qtyInput.required = false;
                qtyInput.value = '';
            }
            updateSelectedMaterialsTable(mode);
        }

        function removeMaterial(mode, id) {
            const checkbox = document.getElementById(mode + '_inv_check_' + id);
            if (checkbox) {
                checkbox.checked = false;
                toggleInvQty(mode, id);
            }
        }

        function updateSelectedMaterialsTable(mode) {
            const rows = document.querySelectorAll('.' + mode + '-inv-row');
            const tbody = document.getElementById(mode + '_selected_materials_tbody');
            const summary = document.getElementById(mode + '_inv_summary');
            
            let selectedCount = 0;
            let rowsHtml = '';

            rows.forEach(row => {
                const id = row.dataset.id;
                const checkbox = document.getElementById(mode + '_inv_check_' + id);
                const qtyInput = document.getElementById(mode + '_inv_qty_' + id);

                if (checkbox && checkbox.checked) {
                    selectedCount++;
                    const name = row.dataset.name;
                    const code = row.dataset.code;
                    const category = row.dataset.category;
                    const unit = row.dataset.unit;
                    const stock = row.dataset.stock;
                    const qty = qtyInput ? qtyInput.value : '1';

                    rowsHtml += `
                        <tr class="hover:bg-slate-50/50">
                            <td class="px-4 py-2.5">
                                <span class="font-semibold text-gray-800">${name}</span>
                                <span class="block text-[11px] text-gray-500">${category}</span>
                            </td>
                            <td class="px-3 py-2.5 font-mono text-gray-600">${code}</td>
                            <td class="px-3 py-2.5 text-gray-600">${unit}</td>
                            <td class="px-3 py-2.5 text-gray-600">${stock} ${unit}</td>
                            <td class="px-3 py-2.5 font-semibold text-brand-700">${qty}</td>
                            <td class="px-3 py-2.5 text-right">
                                <button type="button" onclick="removeMaterial('${mode}', ${id})" class="text-red-500 hover:text-red-700 font-medium text-xs inline-flex items-center gap-1">
                                    <i class="fa-solid fa-xmark"></i> Remove
                                </button>
                            </td>
                        </tr>
                    `;
                }
            });

            if (selectedCount === 0) {
                tbody.innerHTML = `
                    <tr id="${mode}_empty_row">
                        <td colspan="6" class="px-4 py-6 text-center text-slate-400 italic">
                            No inventory materials selected yet. Search and check items from inventory above to add them to this package.
                        </td>
                    </tr>
                `;
            } else {
                tbody.innerHTML = rowsHtml;
            }

            if (summary) {
                summary.textContent = `${selectedCount} material${selectedCount === 1 ? '' : 's'} selected`;
            }
        }

        function filterInventory(mode) {
            const searchInput = document.getElementById(mode + '_inv_search').value.toLowerCase().trim();
            const categorySelect = document.getElementById(mode + '_inv_category').value.toLowerCase();
            const rows = document.querySelectorAll('.' + mode + '-inv-row');
            
            rows.forEach(row => {
                const name = (row.dataset.name || '').toLowerCase();
                const code = (row.dataset.code || '').toLowerCase();
                const category = (row.dataset.category || '').toLowerCase();
                
                const matchesSearch = searchInput === '' || name.includes(searchInput) || code.includes(searchInput);
                const matchesCategory = categorySelect === '' || category === categorySelect;
                
                if (matchesSearch && matchesCategory) {
                    row.style.display = 'flex';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        // Initialize table on load
        document.addEventListener('DOMContentLoaded', function() {
            updateSelectedMaterialsTable('add');
        });
    </script>
</x-admin-layout>
