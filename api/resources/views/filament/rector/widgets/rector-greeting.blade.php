<x-filament-widgets::widget>
    <div class="rounded-xl p-6 shadow-lg" style="background: radial-gradient(143.2% 143.2% at 30% 30%, #F6C32E 0%, #F0B90B 50%, #D99E00 100%);">
        <div class="flex items-center gap-x-3">
            <div class="flex-1">
                <h2 class="text-2xl font-bold tracking-tight text-[#2F4F2F] sm:text-3xl">
                    {{ $this->getGreeting() }}, {{ $this->getRectorName() }}!
                </h2>
                <p class="mt-1 text-sm text-[#2F4F2F]/80">
                    Welcome to your Rector Dashboard. Here's an overview of your pending requests and hostel activities.
                </p>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>

