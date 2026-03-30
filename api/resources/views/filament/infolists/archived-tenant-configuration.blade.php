@php
    $tenant = $getRecord();
@endphp
@if(!$tenant)
    <p class="text-sm text-gray-500 dark:text-gray-400">—</p>
@else
    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-3 text-sm">
        <div>
            <dt class="text-gray-500 dark:text-gray-400">Subscription plan</dt>
            <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $tenant->subscription_plan ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-gray-500 dark:text-gray-400">Subscription amount</dt>
            <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $tenant->subscription_amount ? '₹' . number_format($tenant->subscription_amount) : '—' }}</dd>
        </div>
        <div>
            <dt class="text-gray-500 dark:text-gray-400">Subscription start</dt>
            <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $tenant->subscription_starts_at?->format('d M Y') ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-gray-500 dark:text-gray-400">Subscription end</dt>
            <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $tenant->subscription_ends_at?->format('d M Y') ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-gray-500 dark:text-gray-400">Payment mode</dt>
            <dd class="font-medium text-gray-900 dark:text-gray-100">{{ ucfirst($tenant->payment_mode ?? '—') }}</dd>
        </div>
        <div class="sm:col-span-2">
            <dt class="text-gray-500 dark:text-gray-400">Add-ons</dt>
            <dd class="font-medium text-gray-900 dark:text-gray-100 mt-0.5">
                @if($tenant->addon_security) <span class="inline-flex items-center rounded-md bg-blue-50 dark:bg-blue-900/30 px-2 py-1 text-xs font-medium text-blue-800 dark:text-blue-300 mr-1">Security</span> @endif
                @if($tenant->addon_sports) <span class="inline-flex items-center rounded-md bg-green-50 dark:bg-green-900/30 px-2 py-1 text-xs font-medium text-green-800 dark:text-green-300 mr-1">Sports</span> @endif
                @if($tenant->addon_laundry) <span class="inline-flex items-center rounded-md bg-amber-50 dark:bg-amber-900/30 px-2 py-1 text-xs font-medium text-amber-800 dark:text-amber-300 mr-1">Laundry</span> @endif
                @if(!$tenant->addon_security && !$tenant->addon_sports && !$tenant->addon_laundry)
                    <span class="text-gray-500 dark:text-gray-400">—</span>
                @endif
            </dd>
        </div>
    </dl>
@endif
