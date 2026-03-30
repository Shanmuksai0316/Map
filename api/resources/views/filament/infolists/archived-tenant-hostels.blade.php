@php
    $tenant = $getRecord();
    $hostels = $tenant ? \App\Models\Hostel::withoutGlobalScopes()->where('tenant_id', $tenant->id)->with(['campus', 'amenities'])->orderBy('code')->get() : collect();
@endphp
<div class="space-y-6">
    @if($hostels->isEmpty())
        <p class="text-sm text-gray-500 dark:text-gray-400">No hostels configured.</p>
    @else
        @foreach($hostels as $hostel)
            <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800/50 p-4 shadow-sm">
                <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-700 pb-3 mb-3">
                    <div>
                        <span class="font-mono text-sm font-medium text-primary-600 dark:text-primary-400">{{ $hostel->code }}</span>
                        <span class="mx-2 text-gray-400">·</span>
                        <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $hostel->name }}</span>
                    </div>
                    <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium
                        {{ $hostel->gender_mode === 'boys' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300' : '' }}
                        {{ $hostel->gender_mode === 'girls' ? 'bg-pink-100 text-pink-800 dark:bg-pink-900/30 dark:text-pink-300' : '' }}
                        {{ ($hostel->gender_mode ?? '') === 'co-ed' ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300' : '' }}
                        {{ !in_array($hostel->gender_mode ?? '', ['boys','girls','co-ed']) ? 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' : '' }}
                    ">
                        {{ ucfirst($hostel->gender_mode ?? '—') }}
                    </span>
                </div>

                <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-4 gap-y-2 text-sm">
                    @if($hostel->campus)
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Campus</dt>
                            <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $hostel->campus->name }} ({{ $hostel->campus->code }})</dd>
                        </div>
                    @endif
                    @if($hostel->location)
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Location</dt>
                            <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $hostel->location }}</dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Curfew</dt>
                        <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $hostel->curfew_start ?? '—' }} – {{ $hostel->curfew_end ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Visiting hours</dt>
                        <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $hostel->visiting_start ?? '—' }} – {{ $hostel->visiting_end ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Overnight out-pass</dt>
                        <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $hostel->overnight_enabled ? 'Yes' : 'No' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">QR required during curfew</dt>
                        <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $hostel->qr_required_during_curfew ? 'Yes' : 'No' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Backup codes enabled</dt>
                        <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $hostel->backup_codes_enabled ? 'Yes' : 'No' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Rooms / Beds</dt>
                        <dd class="font-medium text-gray-900 dark:text-gray-100">
                            {{ \App\Models\Room::withoutGlobalScopes()->where('hostel_id', $hostel->id)->count() }} rooms / {{ \App\Models\RoomBed::withoutGlobalScopes()->where('hostel_id', $hostel->id)->count() }} beds
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Students</dt>
                        <dd class="font-medium text-gray-900 dark:text-gray-100">
                            {{ \App\Models\Student::withoutGlobalScopes()->where('hostel_id', $hostel->id)->count() }} students
                        </dd>
                    </div>
                </dl>

                @if(is_array($hostel->address) && !empty(array_filter($hostel->address)))
                    <div class="mt-3 pt-3 border-t border-gray-100 dark:border-gray-700">
                        <dt class="text-gray-500 dark:text-gray-400 text-sm">Address</dt>
                        <dd class="font-medium text-gray-900 dark:text-gray-100 text-sm mt-0.5">{{ implode(', ', array_filter($hostel->address)) }}</dd>
                    </div>
                @endif

                @php $amenities = $hostel->amenities; @endphp
                @if($amenities->isNotEmpty())
                    <div class="mt-3 pt-3 border-t border-gray-100 dark:border-gray-700">
                        <dt class="text-gray-500 dark:text-gray-400 text-sm">Amenities</dt>
                        <dd class="flex flex-wrap gap-1 mt-1">
                            @foreach($amenities as $a)
                                <span class="inline-flex items-center rounded-md bg-gray-100 dark:bg-gray-700 px-2 py-0.5 text-xs font-medium text-gray-700 dark:text-gray-300">{{ $a->label ?? $a->key }}</span>
                            @endforeach
                        </dd>
                    </div>
                @endif
            </div>
        @endforeach
    @endif
</div>
