<x-filament::section>
    <x-slot name="heading">Checklist Compliance</x-slot>
    <div class="grid gap-4 md:grid-cols-3">
        <div>
            <div class="text-3xl font-semibold">{{ $complianceRate }}%</div>
            <p class="text-sm text-[#2F4F2F]/80">Approved today</p>
        </div>
        <div>
            <div class="text-lg font-semibold">{{ $submitted }}/{{ $total }}</div>
            <p class="text-sm text-[#2F4F2F]/80">Submitted / Total</p>
        </div>
        <div>
            <div class="text-lg font-semibold">{{ $overdue }}</div>
            <p class="text-sm text-[#2F4F2F]/80">Overdue (past due date)</p>
        </div>
    </div>

    @if (!empty($roleCompliance))
        <div class="mt-4 border-t pt-4">
            <p class="text-sm text-[#2F4F2F]/80 mb-2 font-semibold">Role compliance (7d)</p>
            <div class="grid gap-3 md:grid-cols-3">
                @foreach ($roleCompliance as $role => $rate)
                    <div>
                        <p class="text-sm text-[#2F4F2F]/80">{{ $role }}</p>
                        <p class="text-lg font-semibold">{{ $rate }}%</p>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</x-filament::section>

