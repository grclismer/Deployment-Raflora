<x-admin-layout title="Users Management">
    <!-- Filter Controls: Role filter dropdown -->
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-4">
            <!-- Role Filter: Dropdown to filter users by role -->
            <select class="border border-brand-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-500">
                <option>All Roles</option>
                <option>Admin</option>
                <option>Client</option>
            </select>
        </div>
        <button class="bg-brand-700 hover:bg-brand-800 text-white px-4 py-2 rounded-lg text-sm font-semibold transition">
            <i class="fa-solid fa-plus mr-1"></i> Add User
        </button>
    </div>

    <!-- Users Table: Lists all registered users -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto w-full">
            <table class="w-full">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-700 uppercase">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-700 uppercase">Email</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-700 uppercase">Role</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-700 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-700 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr>
                        <td class="px-6 py-4 text-sm text-gray-800">John Doe</td>
                        <td class="px-6 py-4 text-sm text-gray-600">john.doe@example.com</td>
                        <td class="px-6 py-4 text-sm text-gray-600">Client</td>
                        <td class="px-6 py-4"><span class="px-2 py-1 text-xs bg-green-100 text-green-800 rounded-full">Active</span></td>
                        <td class="px-6 py-4">
                            <button class="text-emerald-700 hover:text-emerald-900 text-sm font-semibold">Edit</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</x-admin-layout>
