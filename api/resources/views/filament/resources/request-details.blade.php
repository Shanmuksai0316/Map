<div class="space-y-4 p-4">
    <div class="grid grid-cols-2 gap-4">
        <div>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Request ID</p>
            <p class="text-lg font-semibold">#{{ $record->id }}</p>
        </div>
        <div>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Request Name</p>
            <p class="text-lg font-semibold">{{ $record->title ?? 'N/A' }}</p>
        </div>
    </div>
    
    <div class="grid grid-cols-2 gap-4">
        <div>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Student Name</p>
            <p class="text-base">{{ $record->student?->user?->name ?? 'Unknown' }}</p>
        </div>
        <div>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Room Number</p>
            <p class="text-base">{{ $record->student?->roomAllocation()?->first()?->room?->room_no ?? 'N/A' }}</p>
        </div>
    </div>
    
    <div class="grid grid-cols-2 gap-4">
        <div>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Submitted Date & Time</p>
            <p class="text-base">{{ $record->created_at?->format('d M Y H:i') ?? 'N/A' }}</p>
        </div>
        <div>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Status</p>
            <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium 
                @if($record->status === 'open') bg-yellow-100 text-yellow-800
                @elseif($record->status === 'in_progress') bg-blue-100 text-blue-800
                @elseif($record->status === 'resolved') bg-green-100 text-green-800
                @else bg-gray-100 text-gray-800
                @endif">
                {{ ucfirst(str_replace('_', ' ', $record->status ?? 'Unknown')) }}
            </span>
        </div>
    </div>
    
    <div>
        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Assigned To</p>
        <p class="text-base">{{ $record->assignedTo?->name ?? 'Not Assigned' }}</p>
    </div>
    
    <div class="border-t pt-4">
        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Description</p>
        <p class="mt-2 text-base text-gray-700 dark:text-gray-300">
            {{ $record->description ?? 'No description provided.' }}
        </p>
    </div>
</div>

