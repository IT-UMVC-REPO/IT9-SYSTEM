<x-layouts::app :title="__('User Management')">
    <div class="p-8 bg-gray-50 min-h-screen">
        <div class="mb-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h1 class="text-3xl font-extrabold text-emerald-900 tracking-tight">User Management</h1>
                <p class="text-emerald-700/60 mt-1">Manage, filter, and monitor all registered accounts.</p>
            </div>
            
            <div class="flex items-center gap-2 text-sm text-emerald-800 bg-emerald-50 px-4 py-2 rounded-lg border border-emerald-100">
                <span class="font-bold">Total Users:</span> {{ $users->total() }}
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-emerald-100 p-5 mb-6">
            <form method="GET" action="{{ route('admin.users') }}" class="flex flex-col md:flex-row gap-4">
                <div class="relative flex-1">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <input type="text" name="search" value="{{ request('search') }}" 
                        placeholder="Search by name or email..." 
                        class="pl-10 block w-full rounded-lg border-emerald-200 border bg-emerald-50/30 focus:ring-emerald-500 focus:border-emerald-500 text-emerald-900 transition-all">
                </div>
                
                <div class="w-full md:w-48">
                    <select name="role" class="block w-full rounded-lg border-emerald-200 border bg-emerald-50/30 focus:ring-emerald-500 focus:border-emerald-500 text-emerald-900">
                        <option value="">All Roles</option>
                        @foreach($roles as $role)
                            <option value="{{ $role->value }}" {{ request('role') == $role->value ? 'selected' : '' }}>
                                {{ ucfirst($role->name) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <button type="submit" class="inline-flex items-center justify-center px-6 py-2 border border-transparent text-sm font-semibold rounded-lg text-white bg-emerald-600 hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500 transition-colors shadow-sm">
                    Apply Filters
                </button>
                
                @if(request()->anyFilled(['search', 'role']))
                    <a href="{{ route('admin.users') }}" class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-emerald-600 hover:text-emerald-800 underline decoration-2 underline-offset-4">
                        Clear
                    </a>
                @endif
            </form>
        </div>

        <div class="bg-white rounded-xl shadow-lg border border-emerald-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-emerald-100">
                    <thead class="bg-emerald-800">
                        <tr>
                            <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-emerald-50 uppercase tracking-widest">User Details</th>
                            <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-emerald-50 uppercase tracking-widest">Role</th>
                            <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-emerald-50 uppercase tracking-widest">Status</th>
                            <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-emerald-50 uppercase tracking-widest">Joined Date</th>
                            <th scope="col" class="relative px-6 py-4">
                                <span class="sr-only">Actions</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-emerald-50">
                        @forelse($users as $user)
                        <tr class="hover:bg-emerald-50/40 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="h-10 w-10 flex-shrink-0 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-700 font-bold">
                                        {{ strtoupper(substr($user->name, 0, 1)) }}
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-semibold text-emerald-900">{{ $user->name }}</div>
                                        <div class="text-xs text-emerald-600/70">{{ $user->email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-medium bg-gray-100 text-gray-800 capitalize">
                                    {{ $user->role->value }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($user->is_active)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
                                        <span class="w-2 h-2 mr-1.5 bg-emerald-500 rounded-full"></span>
                                        Active
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                        <span class="w-2 h-2 mr-1.5 bg-red-500 rounded-full"></span>
                                        Inactive
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-emerald-700">
                                {{ $user->created_at->toFormattedDateString() }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <form action="{{ route('admin.users.toggle', $user) }}" method="POST" class="inline-block">
                                    @csrf 
                                    @method('PATCH')
                                    <button type="submit" 
                                        onclick="return confirm('Change status for this user?')"
                                        class="{{ $user->is_active ? 'text-red-600 hover:text-red-900 bg-red-50' : 'text-emerald-600 hover:text-emerald-900 bg-emerald-50' }} px-4 py-2 rounded-lg transition-all font-bold">
                                        {{ $user->is_active ? 'Deactivate' : 'Activate' }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-emerald-500 italic">
                                No users found matching your criteria.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            
            @if($users->hasPages())
            <div class="bg-emerald-50 px-6 py-4 border-t border-emerald-100">
                {{ $users->links() }}
            </div>
            @endif
        </div>
    </div>
</x-layouts::app>