<x-filament-panels::page>
    @php
        $userData = $this->getUserData();
    @endphp

    <x-filament::section>
        <div class="space-y-6">
            <!-- Header with Name and Role -->
            <div class="text-center pb-6 border-b border-gray-200 dark:border-gray-700">
                <div class="flex justify-center mb-4">
                    <div class="h-20 w-20 rounded-full bg-primary-100 dark:bg-primary-900 flex items-center justify-center">
                        <x-heroicon-o-user class="h-12 w-12 text-primary-600 dark:text-primary-400" />
                    </div>
                </div>
                <h2 class="text-2xl font-bold text-[#2F4F2F] dark:text-[#2F4F2F]">
                    {{ $userData['name'] }}
                </h2>
                <p class="mt-1 text-sm text-[#2F4F2F]/80 dark:text-[#2F4F2F]/80 uppercase tracking-wide font-semibold">
                    {{ $userData['role'] }}
                </p>
            </div>

            <!-- Details Section -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-1">
                    <dt class="text-sm font-medium text-[#2F4F2F]/80 dark:text-[#2F4F2F]/80">
                        Unique ID
                    </dt>
                    <dd class="text-base font-semibold text-[#2F4F2F] dark:text-[#2F4F2F]">
                        #{{ $userData['unique_id'] }}
                    </dd>
                </div>

                <div class="space-y-1">
                    <dt class="text-sm font-medium text-[#2F4F2F]/80 dark:text-[#2F4F2F]/80">
                        Phone Number
                    </dt>
                    <dd class="text-base font-semibold text-[#2F4F2F] dark:text-[#2F4F2F]">
                        {{ $userData['phone'] }}
                    </dd>
                </div>

                <div class="space-y-1">
                    <dt class="text-sm font-medium text-[#2F4F2F]/80 dark:text-[#2F4F2F]/80">
                        Email
                    </dt>
                    <dd class="text-base font-semibold text-[#2F4F2F] dark:text-[#2F4F2F]">
                        {{ $userData['email'] }}
                    </dd>
                </div>

                <div class="space-y-1">
                    <dt class="text-sm font-medium text-[#2F4F2F]/80 dark:text-[#2F4F2F]/80">
                        Assigned College
                    </dt>
                    <dd class="text-base font-semibold text-[#2F4F2F] dark:text-[#2F4F2F]">
                        {{ $userData['college'] }}
                    </dd>
                </div>

                <div class="space-y-1 md:col-span-2">
                    <dt class="text-sm font-medium text-[#2F4F2F]/80 dark:text-[#2F4F2F]/80">
                        Tenant Code
                    </dt>
                    <dd class="text-base font-semibold text-[#2F4F2F] dark:text-[#2F4F2F]">
                        {{ $userData['tenant_code'] }}
                    </dd>
                </div>
            </div>

            <!-- Logout Button -->
            <div class="pt-6 border-t border-gray-200 dark:border-gray-700">
                <form method="POST" action="{{ route('filament.rector.auth.logout') }}">
                    @csrf
                    <x-filament::button
                        type="submit"
                        color="danger"
                        size="lg"
                        class="w-full"
                    >
                        <x-heroicon-o-arrow-right-on-rectangle class="w-5 h-5 mr-2" />
                        Logout
                    </x-filament::button>
                </form>
            </div>
        </div>
    </x-filament::section>
</x-filament-panels::page>

