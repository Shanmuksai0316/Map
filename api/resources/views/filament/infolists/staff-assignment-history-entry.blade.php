@php
    $history = collect($getState() ?? []);
@endphp

<div class="space-y-2">
    @if($history->isEmpty())
        <p class="text-sm text-gray-500 dark:text-gray-400">No assignment history</p>
    @else
        <div class="space-y-3">
            @foreach($history as $assignment)
                @php
                    $hostel = \App\Models\Hostel::find($assignment->hostel_id);
                    $tenant = \App\Models\Tenant::find($assignment->tenant_id);
                    $isActive = is_null($assignment->revoked_at);
                @endphp
                <div class="rounded-lg border p-3 {{ $isActive ? 'bg-green-50 border-green-200 dark:bg-green-900/20 dark:border-green-800' : 'bg-gray-50 border-gray-200 dark:bg-gray-800 dark:border-gray-700' }}">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                {{ $tenant->name ?? 'Unknown' }} - {{ $hostel->name ?? 'Unknown Hostel' }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                {{ \Carbon\Carbon::parse($assignment->assigned_at)->format('d M Y, h:i A') }}
                                @if(!$isActive)
                                    - {{ \Carbon\Carbon::parse($assignment->revoked_at)->format('d M Y, h:i A') }}
                                @endif
                            </p>
                            @if($assignment->assignment_notes)
                                <p class="text-xs text-gray-600 dark:text-gray-300 mt-1">
                                    {{ $assignment->assignment_notes }}
                                </p>
                            @endif
                        </div>
                        @if($isActive)
                            <span class="inline-flex items-center rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-800 dark:bg-green-900 dark:text-green-300">
                                Active
                            </span>
                        @else
                            <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-1 text-xs font-medium text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                                Revoked
                            </span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>

