@php
    $tenant = $getRecord();
    $data = $tenant ? ($tenant->data ?? []) : [];
    $wizard = is_array($data['wizard'] ?? null) ? $data['wizard'] : [];
    $tenantInfo = $wizard['tenant_info'] ?? [];
    $hostelsWizard = $wizard['hostels'] ?? [];
    $amenitiesWizard = $wizard['amenities']['selected'] ?? [];
    $staffWizard = $wizard['staff'] ?? [];
    $roomConfig = $wizard['room_config'] ?? [];

    $hostelsDb = $tenant
        ? \App\Models\Hostel::withoutGlobalScopes()->where('tenant_id', $tenant->id)->with(['campus', 'amenities'])->orderBy('code')->get()
        : collect();
@endphp

<div class="space-y-6">
    {{-- Header / hero --}}
    <div class="rounded-2xl bg-gradient-to-r from-emerald-900 via-emerald-800 to-emerald-700 px-6 py-5 text-white shadow-sm">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
            <div>
                <div class="text-xs uppercase tracking-wide text-emerald-200/80 mb-1">Archived tenant</div>
                <div class="flex items-center gap-2 flex-wrap">
                    <h1 class="text-xl font-semibold">{{ $tenant->name ?? '—' }}</h1>
                    @if(!empty($tenant->code))
                        <span class="inline-flex items-center rounded-full bg-emerald-950/40 px-2.5 py-0.5 text-xs font-mono">{{ $tenant->code }}</span>
                    @endif
                </div>
                @if(!empty($tenantInfo['subdomain']))
                    <p class="mt-1 text-xs text-emerald-100/90">
                        Subdomain: {{ $tenantInfo['subdomain'] }}.{{ config('app.domain', 'mapservices.in') }}
                    </p>
                @endif
            </div>
            <div class="flex flex-col items-start md:items-end gap-1 text-xs text-emerald-100/90">
                <div>
                    <span class="font-semibold">Created</span>
                    <span class="ml-1 opacity-80">{{ optional($tenant->created_at)->format('d M Y') ?? '—' }}</span>
                </div>
                <div>
                    <span class="font-semibold">Archived</span>
                    <span class="ml-1 opacity-80">{{ optional($tenant->archived_at)->format('d M Y') ?? '—' }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Tenant Info & Contacts --}}
    <section class="space-y-3">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Tenant & contacts</h2>
        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900/60 p-4 shadow-sm md:col-span-2">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">Tenant & campus</h3>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-2 text-sm">
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Tenant name</dt>
                        <dd class="font-medium text-gray-900 dark:text-gray-100">
                            {{ $tenantInfo['name'] ?? $tenant->name ?? '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Tenant code</dt>
                        <dd class="font-mono text-gray-900 dark:text-gray-100">
                            {{ $tenantInfo['code'] ?? $tenant->code ?? '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Campus name</dt>
                        <dd class="font-medium text-gray-900 dark:text-gray-100">
                            {{ $tenantInfo['campus_name'] ?? '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Subdomain</dt>
                        <dd class="font-mono text-gray-900 dark:text-gray-100">
                            {{ $tenantInfo['subdomain'] ?? '—' }}
                        </dd>
                    </div>
                </dl>
            </div>
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900/60 p-4 shadow-sm">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">Key contacts</h3>
                @include('filament.infolists.archived-tenant-onboarding-contacts')
            </div>
        </div>
    </section>

    {{-- Hostels (from onboarding) --}}
    <section class="space-y-3">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Hostel details (as configured during onboarding)</h2>
        @if(empty($hostelsWizard))
            <p class="text-sm text-gray-500 dark:text-gray-400">
                No hostel data was captured in the onboarding wizard.
            </p>
        @else
            <div class="grid gap-4 md:grid-cols-2">
                @foreach($hostelsWizard as $hostel)
                    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900/60 p-4 shadow-sm">
                        <div class="flex items-center justify-between mb-2">
                            <div>
                                <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Hostel</div>
                                <div class="font-semibold text-gray-900 dark:text-gray-100">
                                    {{ $hostel['name'] ?? '—' }}
                                </div>
                            </div>
                            <div class="text-right text-xs">
                                <div class="font-mono text-gray-900 dark:text-gray-100">
                                    {{ $hostel['code'] ?? '—' }}
                                </div>
                                @if(!empty($hostel['gender']))
                                    <div class="mt-1 inline-flex items-center rounded-full bg-gray-100 dark:bg-gray-800 px-2 py-0.5 text-[11px] font-medium text-gray-700 dark:text-gray-300">
                                        {{ ucfirst($hostel['gender']) }} hostel
                                    </div>
                                @endif
                            </div>
                        </div>
                        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-1 text-xs">
                            <div>
                                <dt class="text-gray-500 dark:text-gray-400">Street</dt>
                                <dd class="text-gray-900 dark:text-gray-100">
                                    {{ $hostel['address_street'] ?? '—' }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 dark:text-gray-400">City</dt>
                                <dd class="text-gray-900 dark:text-gray-100">
                                    {{ $hostel['address_city'] ?? '—' }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 dark:text-gray-400">State</dt>
                                <dd class="text-gray-900 dark:text-gray-100">
                                    {{ $hostel['address_state'] ?? '—' }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 dark:text-gray-400">Pincode</dt>
                                <dd class="text-gray-900 dark:text-gray-100">
                                    {{ $hostel['address_pincode'] ?? '—' }}
                                </dd>
                            </div>
                        </dl>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    {{-- Staff and Rooms (live data snapshot) --}}
    <section class="space-y-3">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Staff & rooms snapshot</h2>
        <div class="grid gap-4 md:grid-cols-2">
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900/60 p-4 shadow-sm">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">Campus Manager & hostel staff</h3>
                @include('filament.infolists.archived-tenant-staff')
            </div>
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900/60 p-4 shadow-sm">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">Rooms, beds & students (by hostel)</h3>
                @include('filament.infolists.archived-tenant-hostels')
            </div>
        </div>
    </section>

    {{-- Amenities --}}
    <section class="space-y-3">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Amenities</h2>
        @php
            $amenityIds = is_array($amenitiesWizard) ? $amenitiesWizard : [];
            $amenityLabels = $amenityIds
                ? \App\Models\Amenity::whereIn('id', $amenityIds)->pluck('label', 'id')
                : collect();
        @endphp
        @if(empty($amenityIds))
            <p class="text-sm text-gray-500 dark:text-gray-400">
                No amenities were selected in the onboarding wizard.
            </p>
        @else
            <div class="flex flex-wrap gap-1.5">
                @foreach($amenityIds as $id)
                    <span class="inline-flex items-center rounded-full bg-gray-100 dark:bg-gray-800 px-3 py-0.5 text-xs font-medium text-gray-800 dark:text-gray-200">
                        {{ $amenityLabels[$id] ?? ('Amenity #' . $id) }}
                    </span>
                @endforeach
            </div>
        @endif
    </section>

    {{-- Confirmation --}}
    <section class="space-y-3">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Onboarding confirmation</h2>
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900/60 p-4 shadow-sm">
            <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">Confirmation</h3>
            <p class="text-sm text-gray-900 dark:text-gray-100">
                Onboarding confirmation:
                @if($wizard && ($wizard['activation']['confirmed'] ?? false))
                    <span class="font-semibold text-emerald-600 dark:text-emerald-400">Completed</span>
                @else
                    <span class="font-semibold text-amber-600 dark:text-amber-400">Not fully confirmed in wizard</span>
                @endif
            </p>
        </div>
    </section>
</div>

