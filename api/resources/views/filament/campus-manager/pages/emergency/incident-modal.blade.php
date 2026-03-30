<div class="space-y-4">
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
        <span class="text-sm font-medium text-[#2F4F2F]/80 dark:text-[#2F4F2F]/80">Incident Type</span>
        <p class="text-sm">
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200">
                {{ str_replace(['_', 'Late', 'Missed', 'Emergency', 'Security'], [' ', 'Late ', 'Missed ', 'Emergency ', 'Security '], $incidentType) }}
            </span>
        </p>
    </div>

    <div>
        <span class="text-sm font-medium text-[#2F4F2F]/80 dark:text-[#2F4F2F]/80">Submitted Date & Time</span>
        <p class="text-sm text-[#2F4F2F] dark:text-[#2F4F2F]">{{ $submittedAt }}</p>
    </div>

    <div>
        <span class="text-sm font-medium text-[#2F4F2F]/80 dark:text-[#2F4F2F]/80">Details</span>
        <p class="text-sm text-[#2F4F2F] dark:text-[#2F4F2F]">{{ $note }}</p>
    </div>

    @if($isAcknowledged)
        <div class="mt-4 p-3 bg-green-50 dark:bg-green-900/20 rounded-lg">
            <div class="flex items-center gap-2 text-green-700 dark:text-green-300">
                <x-heroicon-o-check-circle class="w-5 h-5" />
                <span class="text-sm font-medium">This incident has been acknowledged</span>
            </div>
        </div>
    @else
        <div class="mt-4 p-3 bg-red-50 dark:bg-red-900/20 rounded-lg">
            <div class="flex items-center gap-2 text-red-700 dark:text-red-300">
                <x-heroicon-o-exclamation-circle class="w-5 h-5" />
                <span class="text-sm font-medium">This incident requires acknowledgement</span>
            </div>
        </div>
    @endif
</div>

