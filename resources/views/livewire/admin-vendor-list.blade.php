
<div> {{-- This is the single root element that fixes your error! --}}

    <div class="flex space-x-2 mb-6 bg-gray-100 p-1 rounded-lg w-fit">
        @foreach(['Pending', 'Approved', 'Rejected'] as $status)
            <button 
                wire:click="setFilter('{{ $status }}')"
                class="px-4 py-2 rounded-md text-sm font-medium transition-colors {{ $statusFilter === $status ? 'bg-white shadow text-indigo-600' : 'text-gray-500 hover:text-gray-700' }}">
                {{ $status }}
            </button>
        @endforeach
    </div>

    <div class="bg-white border rounded-xl overflow-hidden shadow-sm">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Store Name</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Submitted</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($vendors as $vendor)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                            {{ $vendor->store_name }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $vendor->created_at->toFormattedDateString() }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            {{-- Task B1: Link to review page (Task B2) --}}
                            <a href="{{ route('admin.vendors.show', $vendor) }}" class="text-indigo-600 hover:text-indigo-900">
                                Review →
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-6 py-10 text-center text-gray-500">
                            No {{ strtolower($statusFilter) }} applications found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="p-4 border-t">
            {{ $vendors->links() }}
        </div>
    </div>

</div> {{-- End of single root element --}}