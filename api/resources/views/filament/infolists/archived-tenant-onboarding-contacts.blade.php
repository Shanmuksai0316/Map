@php
    $tenant = $getRecord();
    $wizard = $tenant ? ($tenant->data['wizard'] ?? $tenant->data ?? []) : [];
    $ti = $wizard['tenant_info'] ?? $tenant->data['tenant_info'] ?? [];
@endphp
<dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-3 text-sm">
    <div class="sm:col-span-2">
        <dt class="text-gray-500 dark:text-gray-400">Rector</dt>
        <dd class="font-medium text-gray-900 dark:text-gray-100 mt-0.5">
            {{ $ti['rector_name'] ?? '—' }}
            @if(!empty($ti['rector_phone']) || !empty($ti['rector_email']))
                <span class="text-gray-500 dark:text-gray-400"> · {{ $ti['rector_phone'] ?? '' }}{{ !empty($ti['rector_phone']) && !empty($ti['rector_email']) ? ' · ' : '' }}{{ $ti['rector_email'] ?? '' }}</span>
            @endif
        </dd>
    </div>
    <div class="sm:col-span-2">
        <dt class="text-gray-500 dark:text-gray-400">College Management</dt>
        <dd class="font-medium text-gray-900 dark:text-gray-100 mt-0.5">
            {{ $ti['college_mgmt_name'] ?? '—' }}
            @if(!empty($ti['college_mgmt_phone']) || !empty($ti['college_mgmt_email']))
                <span class="text-gray-500 dark:text-gray-400"> · {{ $ti['college_mgmt_phone'] ?? '' }}{{ !empty($ti['college_mgmt_phone']) && !empty($ti['college_mgmt_email']) ? ' · ' : '' }}{{ $ti['college_mgmt_email'] ?? '' }}</span>
            @endif
        </dd>
    </div>
</dl>
