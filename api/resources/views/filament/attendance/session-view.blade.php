<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Room Cards Section -->
        <div>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Rooms</h3>
            
            @if($roomSummaries->isEmpty())
                <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                    <p>No rooms to show</p>
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($roomSummaries as $room)
                        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 {{ $selectedRoom && $selectedRoom['room_id'] == $room['room_id'] ? 'ring-2 ring-primary-500' : '' }}">
                            <div class="flex items-center justify-between mb-3">
                                <h4 class="font-semibold text-gray-900 dark:text-white">
                                    Room {{ $room['room_number'] }}
                                </h4>
                                <div class="text-right">
                                    <span class="text-sm text-gray-500 dark:text-gray-400 block">
                                        {{ $room['total_students'] }} students
                                    </span>
                                    @if($room['submitted_at'])
                                        <span class="text-xs text-green-600 dark:text-green-400 block mt-1">
                                            Submitted: {{ \Carbon\Carbon::parse($room['submitted_at'])->format('M j, Y H:i') }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                            
                            <div class="flex flex-wrap gap-2 mb-4">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                    P: {{ $room['present_count'] }}
                                </span>
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                    A: {{ $room['absent_count'] }}
                                </span>
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                    L: {{ $room['leave_count'] }}
                                </span>
                                @if($room['unmarked_count'] > 0)
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">
                                        U: {{ $room['unmarked_count'] }}
                                    </span>
                                @endif
                            </div>
                            
                            <x-filament::button
                                wire:click="$set('room_id', {{ $room['room_id'] }})"
                                size="sm"
                                class="w-full"
                            >
                                Open Roster
                            </x-filament::button>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Roster Section -->
        @if($selectedRoom)
            <div>
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                        Roster - Room {{ $selectedRoom['room_number'] }}
                    </h3>
                    
                    @if($canMark)
                        <div class="flex gap-2">
                            <x-filament::button
                                wire:click="markAllPresent"
                                size="sm"
                                color="success"
                            >
                                Mark All Present
                            </x-filament::button>
                            
                            <x-filament::button
                                wire:click="submitRoom"
                                size="sm"
                                color="primary"
                            >
                                Submit Room
                            </x-filament::button>
                        </div>
                    @endif
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Student Name
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Status
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Comment
                                    </th>
                                    @if($canMark)
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Actions
                                        </th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @forelse($roster as $student)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">
                                            {{ $student['student_name'] }}
                                            @if($student['locked'])
                                                <span class="ml-2 text-xs text-gray-500">(Leave)</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">
                                            @php
                                                $statusClasses = match($student['current_status']) {
                                                    'present' => 'bg-green-100 text-green-800',
                                                    'absent' => 'bg-red-100 text-red-800',
                                                    'leave' => 'bg-blue-100 text-blue-800',
                                                    default => 'bg-gray-100 text-gray-800'
                                                };
                                            @endphp
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $statusClasses }}">
                                                {{ ucfirst($student['current_status']) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                            {{ $student['comment'] ? Str::limit($student['comment'], 50) : '-' }}
                                        </td>
                                        @if($canMark && !$student['locked'])
                                            <td class="px-4 py-3">
                                                <div class="flex gap-2">
                                                    <x-filament::button
                                                        wire:click="markPresent({{ $student['student_id'] }})"
                                                        size="sm"
                                                        color="success"
                                                    >
                                                        Present
                                                    </x-filament::button>
                                                    <x-filament::button
                                                        wire:click="markAbsent({{ $student['student_id'] }})"
                                                        size="sm"
                                                        color="danger"
                                                    >
                                                        Absent
                                                    </x-filament::button>
                                                </div>
                                            </td>
                                        @elseif($canMark)
                                            <td class="px-4 py-3 text-sm text-gray-400">
                                                Locked
                                            </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ $canMark ? 4 : 3 }}" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                            No students found in this room
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @else
            <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                <p>Select a room to view roster</p>
            </div>
        @endif
    </div>
</x-filament-panels::page>


