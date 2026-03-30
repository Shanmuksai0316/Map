<x-filament-panels::page>
    @php
        $userData = $this->getUserData();
        $panelId = filament()->getCurrentPanel()?->getId();
        $logoutRoute = $panelId ? route('filament.' . $panelId . '.auth.logout') : null;
    @endphp

    <x-filament::section>
        <div class="space-y-6">
            <div class="text-center pb-6 border-b border-gray-200">
                <div class="flex justify-center mb-4">
                    <div class="h-20 w-20 rounded-full bg-primary-100 flex items-center justify-center shadow-sm">
                        <x-heroicon-o-user class="h-12 w-12 text-primary-600" />
                    </div>
                </div>
                <h2 class="text-2xl font-bold text-[#2F4F2F]">
                    {{ $userData['name'] }}
                </h2>
                <p class="mt-1 text-sm text-[#2F4F2F]/80 uppercase tracking-wide font-semibold">
                    {{ $userData['role'] }}
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-1">
                    <dt class="text-sm font-medium text-[#2F4F2F]/80">Unique ID</dt>
                    <dd class="text-base font-semibold text-[#2F4F2F]">#{{ $userData['unique_id'] }}</dd>
                </div>

                <div class="space-y-1">
                    <dt class="text-sm font-medium text-[#2F4F2F]/80">Phone Number</dt>
                    <dd class="text-base font-semibold text-[#2F4F2F]">{{ $userData['phone'] }}</dd>
                </div>

                <div class="space-y-1">
                    <dt class="text-sm font-medium text-[#2F4F2F]/80">Email</dt>
                    <dd class="text-base font-semibold text-[#2F4F2F]">{{ $userData['email'] }}</dd>
                </div>

                <div class="space-y-1">
                    <dt class="text-sm font-medium text-[#2F4F2F]/80">Assigned Institution</dt>
                    <dd class="text-base font-semibold text-[#2F4F2F]">{{ $userData['college'] }}</dd>
                </div>

                <div class="space-y-1 md:col-span-2">
                    <dt class="text-sm font-medium text-[#2F4F2F]/80">Tenant Code</dt>
                    <dd class="text-base font-semibold text-[#2F4F2F]">{{ $userData['tenant_code'] }}</dd>
                </div>
            </div>

            @if ($logoutRoute)
                <div class="pt-6 border-t border-gray-200">
                    <form method="POST" action="{{ $logoutRoute }}">
                        @csrf
                        <x-filament::button type="submit" color="danger" size="lg" class="w-full">
                            <x-heroicon-o-arrow-right-on-rectangle class="w-5 h-5 mr-2" />
                            Logout
                        </x-filament::button>
                    </form>
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-panels::page>
