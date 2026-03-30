<div class="space-y-4">
    @if($history->isEmpty())
        <div class="text-center py-12">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">No assignment history</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">This staff member has never been assigned to a hostel.</p>
        </div>
    @else
        <div class="flow-root">
            <ul role="list" class="-mb-8">
                @foreach($history as $index => $assignment)
                    @php
                        $hostel = \App\Models\Hostel::find($assignment->hostel_id);
                        $tenant = \App\Models\Tenant::find($assignment->tenant_id);
                        $assignedBy = \App\Models\User::find($assignment->assigned_by);
                        $isActive = is_null($assignment->revoked_at);
                    @endphp
                    <li>
                        <div class="relative pb-8">
                            @if(!$loop->last)
                                <span class="absolute left-4 top-4 -ml-px h-full w-0.5 bg-gray-200 dark:bg-gray-700" aria-hidden="true"></span>
                            @endif
                            <div class="relative flex space-x-3">
                                <div>
                                    <span class="h-8 w-8 rounded-full flex items-center justify-center ring-8 ring-white dark:ring-gray-900 {{ $isActive ? 'bg-green-500' : 'bg-gray-400' }}">
                                        @if($isActive)
                                            <svg class="h-5 w-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                            </svg>
                                        @else
                                            <svg class="h-5 w-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                            </svg>
                                        @endif
                                    </span>
                                </div>
                                <div class="flex min-w-0 flex-1 justify-between space-x-4 pt-1.5">
                                    <div>
                                        <p class="text-sm text-gray-900 dark:text-gray-100">
                                            Assigned to 
                                            <span class="font-medium">{{ $tenant->name ?? 'Unknown' }}</span> - 
                                            <span class="font-medium">{{ $hostel->name ?? 'Unknown Hostel' }}</span>
                                            @if($isActive)
                                                <span class="ml-2 inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20 dark:bg-green-500/10 dark:text-green-400 dark:ring-green-500/20">
                                                    Active
                                                </span>
                                            @endif
                                        </p>
                                        @if($assignment->assignment_notes)
                                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                                {{ $assignment->assignment_notes }}
                                            </p>
                                        @endif
                                        @if(!$isActive && $assignment->revocation_reason)
                                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">
                                                <strong>Revoked:</strong> {{ $assignment->revocation_reason }}
                                            </p>
                                        @endif
                                        @if($assignedBy)
                                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                Assigned by: {{ $assignedBy->name }}
                                            </p>
                                        @endif
                                    </div>
                                    <div class="whitespace-nowrap text-right text-sm text-gray-500 dark:text-gray-400">
                                        <div>{{ \Carbon\Carbon::parse($assignment->assigned_at)->format('d M Y') }}</div>
                                        @if(!$isActive)
                                            <div class="text-xs">to {{ \Carbon\Carbon::parse($assignment->revoked_at)->format('d M Y') }}</div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>


