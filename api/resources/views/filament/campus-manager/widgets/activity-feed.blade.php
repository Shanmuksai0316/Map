@php
    use Illuminate\Support\Str;
@endphp
<x-filament::section>
    <x-slot name="heading">Recent Activity</x-slot>
    <div class="space-y-4">
        @forelse ($entries as $entry)
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-sm font-semibold">{{ $entry->title }}</p>
                    <p class="text-xs text-[#2F4F2F]/80">{{ $entry->body ?? Str::headline($entry->type) }}</p>
                </div>
                <span class="text-xs text-[#2F4F2F]/80">{{ $entry->created_at?->diffForHumans() }}</span>
            </div>
        @empty
            <p class="text-sm text-[#2F4F2F]/80">No recent activity logged.</p>
        @endforelse
    </div>
    <form wire:submit.prevent="saveEntry" class="mt-4 border-t pt-4 space-y-3">
        {{ $form }}
        <x-filament::button type="submit" size="sm">Add Note</x-filament::button>
    </form>
</x-filament::section>

