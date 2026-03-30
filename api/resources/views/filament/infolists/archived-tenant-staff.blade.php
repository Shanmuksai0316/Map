@php
    $tenant = $getRecord();
    $campusManager = $tenant ? \App\Models\User::withoutGlobalScopes()->where('tenant_id', $tenant->id)->whereHas('roles', fn($q) => $q->where('name', 'Campus Manager'))->with('roles')->first() : null;
    $assignments = $tenant ? \Illuminate\Support\Facades\DB::table('staff_assignments')->where('tenant_id', $tenant->id)->whereNull('revoked_at')->get() : collect();
    $hostelIds = $assignments->pluck('hostel_id')->unique()->filter()->values();
    $hostels = $hostelIds->isNotEmpty() ? \App\Models\Hostel::withoutGlobalScopes()->whereIn('id', $hostelIds)->get()->keyBy('id') : collect();
@endphp
<div class="space-y-4">
    <div>
        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Campus Manager (tenant-level)</h4>
        @if($campusManager)
            <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50 p-3">
                <div class="font-medium text-gray-900 dark:text-gray-100">{{ $campusManager->name }}</div>
                <div class="text-sm text-gray-600 dark:text-gray-400">{{ $campusManager->email }}</div>
                @if($campusManager->phone)
                    <div class="text-sm text-gray-600 dark:text-gray-400">{{ $campusManager->phone }}</div>
                @endif
            </div>
        @else
            <p class="text-sm text-gray-500 dark:text-gray-400">Not assigned.</p>
        @endif
    </div>

    <div>
        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Hostel staff</h4>
        @if($assignments->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">No hostel staff assignments.</p>
        @else
            @foreach($assignments->groupBy('hostel_id') as $hostelId => $hostelAssignments)
                @php
                    $hostel = $hostels->get($hostelId);
                    $hostelName = $hostel ? $hostel->name . ' (' . $hostel->code . ')' : 'Hostel #' . $hostelId;
                @endphp
                <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-3 mb-2">
                    <div class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ $hostelName }}</div>
                    <ul class="space-y-1">
                        @foreach($hostelAssignments as $a)
                            @php $user = \App\Models\User::withoutGlobalScopes()->find($a->user_id); @endphp
                            <li class="text-sm text-gray-900 dark:text-gray-100">
                                {{ $user ? $user->name : 'User #' . $a->user_id }}
                                @if($user)
                                    <span class="text-gray-500 dark:text-gray-400">— {{ $user->email }}</span>
                                    @if($role = $user->roles->first())
                                        <span class="inline-flex items-center rounded bg-gray-100 dark:bg-gray-700 px-1.5 py-0.5 text-xs font-medium text-gray-700 dark:text-gray-300 ml-1">{{ $role->name }}</span>
                                    @endif
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        @endif
    </div>
</div>
