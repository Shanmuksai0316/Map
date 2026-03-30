<div class="space-y-4">
    <div class="grid grid-cols-2 gap-4">
        <div>
            <span class="text-sm font-medium text-[#2F4F2F]/80 dark:text-[#2F4F2F]/80">Request ID</span>
            <p class="text-sm text-[#2F4F2F] dark:text-[#2F4F2F]">{{ $requestId }}</p>
        </div>
        <div>
            <span class="text-sm font-medium text-[#2F4F2F]/80 dark:text-[#2F4F2F]/80">Status</span>
            <p class="text-sm">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                    @if($status === 'Pending') bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200
                    @elseif($status === 'Approved') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200
                    @elseif($status === 'Rejected') bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200
                    @else bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200
                    @endif
                ">
                    {{ $status }}
                </span>
            </p>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div>
            <span class="text-sm font-medium text-[#2F4F2F]/80 dark:text-[#2F4F2F]/80">Student Name</span>
            <p class="text-sm text-[#2F4F2F] dark:text-[#2F4F2F]">{{ $studentName }}</p>
        </div>
        <div>
            <span class="text-sm font-medium text-[#2F4F2F]/80 dark:text-[#2F4F2F]/80">Room Number</span>
            <p class="text-sm text-[#2F4F2F] dark:text-[#2F4F2F]">{{ $roomNumber }}</p>
        </div>
    </div>

    <div>
        <span class="text-sm font-medium text-[#2F4F2F]/80 dark:text-[#2F4F2F]/80">Going Out Date</span>
        <p class="text-sm text-[#2F4F2F] dark:text-[#2F4F2F]">{{ $goingOutDate }}</p>
    </div>

    <div>
        <span class="text-sm font-medium text-[#2F4F2F]/80 dark:text-[#2F4F2F]/80">Submitted Date & Time</span>
        <p class="text-sm text-[#2F4F2F] dark:text-[#2F4F2F]">{{ $submittedAt }}</p>
    </div>

    <div>
        <span class="text-sm font-medium text-[#2F4F2F]/80 dark:text-[#2F4F2F]/80">Reason / Description</span>
        <p class="text-sm text-[#2F4F2F] dark:text-[#2F4F2F]">{{ $description }}</p>
    </div>
</div>

