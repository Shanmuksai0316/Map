@php
    $tenant = $getRecord();
    $campuses = $tenant ? \App\Models\Campus::withoutGlobalScopes()->where('tenant_id', $tenant->id)->orderBy('code')->get() : collect();
@endphp
<div class="space-y-3">
    @if($campuses->isEmpty())
        <p class="text-sm text-gray-500 dark:text-gray-400">No campuses configured.</p>
    @else
        @foreach($campuses as $campus)
            <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50 p-4">
                <div class="font-medium text-gray-900 dark:text-gray-100">{{ $campus->name }}</div>
                <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                    <span class="font-mono">{{ $campus->code }}</span>
                    @if(is_array($campus->address) && !empty($campus->address))
                        — {{ implode(', ', array_filter($campus->address)) }}
                    @endif
                </div>
            </div>
        @endforeach
    @endif
</div>
