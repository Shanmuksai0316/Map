@php
    use Illuminate\Support\Str;
    use Illuminate\Support\Facades\Route;

    $panel = filament()->getCurrentPanel();
    $panelId = $panel?->getId();
    $user = auth()->user();

    $roleLabel = null;
    if ($user) {
        $roleLabel = $user->getRoleNames()->first();
        if (! $roleLabel && $user->kind) {
            $roleLabel = (string) Str::of($user->kind)->replace('_', ' ')->title();
        }
    }

    $profileUrl = null;
    if ($panelId) {
        $profileRoute = 'filament.' . $panelId . '.pages.profile';
        if (Route::has($profileRoute)) {
            $profileUrl = route($profileRoute);
        }
    }

    $initial = Str::of($user?->name ?? 'A')->trim()->substr(0, 1)->upper();
@endphp

<div class="account-sticky-footer sticky bottom-0 border-t border-gray-200/60 py-3 px-6">
    @if ($profileUrl)
        <a
            href="{{ $profileUrl }}"
            class="account-sticky-link flex w-full items-center gap-3 rounded-lg py-2 text-[#2F4F2F] transition hover:bg-gray-50/80"
        >
            <div class="account-sticky-avatar flex h-10 w-10 shrink-0 items-center justify-center rounded-full border-2 border-[#2F4F2F] bg-white text-sm font-semibold text-[#2F4F2F]">
                {{ $initial }}
            </div>
            <div class="account-sticky-details min-w-0 flex-1">
                <div class="truncate text-sm font-semibold text-[#2F4F2F]">
                    {{ $user?->name ?? 'Account' }}
                </div>
                @if ($roleLabel)
                    <div class="text-xs text-[#2F4F2F]/80">
                        {{ $roleLabel }}
                    </div>
                @endif
            </div>
        </a>
    @else
        <div class="account-sticky-link flex w-full items-center gap-3 rounded-lg py-2 text-[#2F4F2F]">
            <div class="account-sticky-avatar flex h-10 w-10 shrink-0 items-center justify-center rounded-full border-2 border-[#2F4F2F] bg-white text-sm font-semibold text-[#2F4F2F]">
                {{ $initial }}
            </div>
            <div class="account-sticky-details min-w-0 flex-1">
                <div class="truncate text-sm font-semibold text-[#2F4F2F]">
                    {{ $user?->name ?? 'Account' }}
                </div>
                @if ($roleLabel)
                    <div class="text-xs text-[#2F4F2F]/80">
                        {{ $roleLabel }}
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
