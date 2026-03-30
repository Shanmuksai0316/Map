<div class="fi-wi-greeting rounded-xl p-6 shadow-lg" style="background: radial-gradient(143.2% 143.2% at 30% 30%, #F6C32E 0%, #F0B90B 50%, #D99E00 100%);">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="flex flex-wrap items-center gap-2 text-2xl font-bold text-[#2F4F2F]">
                <span>{{ $this->getGreeting() }}, {{ $this->getUserName() }}!</span>
                <svg
                    class="fi-wi-greeting-hand inline-block h-7 w-7 shrink-0 text-[#2F4F2F]"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke-width="1.5"
                    stroke="currentColor"
                    aria-hidden="true"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M10.05 4.575a1.575 1.575 0 1 0-3.15 0v3m3.15-3v-1.5a1.575 1.575 0 0 1 3.15 0v1.5m-3.15 0 .075 5.925m3.075.75V4.575m0 0a1.575 1.575 0 0 1 3.15 0V15M6.9 7.575a1.575 1.575 0 1 0-3.15 0v8.175a6.75 6.75 0 0 0 6.75 6.75h2.018a5.25 5.25 0 0 0 3.712-1.538l1.732-1.732a5.25 5.25 0 0 0 1.538-3.712l.003-2.024a.668.668 0 0 1 .198-.471 1.575 1.575 0 1 0-2.228-2.228 3.818 3.818 0 0 0-1.12 2.687M6.9 7.575V12m6.27 4.318A4.49 4.49 0 0 1 16.35 15m.002 0h-.002"
                    />
                </svg>
            </h1>
            <p class="mt-1 text-[#2F4F2F]/80">
                {{ $this->getFormattedDate() }}
            </p>
        </div>
    </div>
</div>
