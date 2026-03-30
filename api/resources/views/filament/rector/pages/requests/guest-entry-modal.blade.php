<div class="space-y-4">
    <div class="grid grid-cols-2 gap-4">
        <div>
            <span class="text-sm font-medium text-[#2F4F2F]/80">Request ID</span>
            <p class="text-sm text-[#2F4F2F]">{{ $requestId }}</p>
        </div>
        <div>
            <span class="text-sm font-medium text-[#2F4F2F]/80">Status</span>
            <p class="text-sm text-[#2F4F2F]">{{ $status }}</p>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div>
            <span class="text-sm font-medium text-[#2F4F2F]/80">Student Name</span>
            <p class="text-sm text-[#2F4F2F]">{{ $studentName }}</p>
        </div>
        <div>
            <span class="text-sm font-medium text-[#2F4F2F]/80">Room Number</span>
            <p class="text-sm text-[#2F4F2F]">{{ $roomNumber }}</p>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div>
            <span class="text-sm font-medium text-[#2F4F2F]/80">Guest Name</span>
            <p class="text-sm text-[#2F4F2F]">{{ $guestName }}</p>
        </div>
        <div>
            <span class="text-sm font-medium text-[#2F4F2F]/80">Guest Relation</span>
            <p class="text-sm text-[#2F4F2F]">{{ $guestRelation }}</p>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div>
            <span class="text-sm font-medium text-[#2F4F2F]/80">Guest ID Proof</span>
            <p class="text-sm text-[#2F4F2F]">{{ $guestIdProof }}</p>
        </div>
        <div>
            <span class="text-sm font-medium text-[#2F4F2F]/80">Primary Contact Number</span>
            <p class="text-sm text-[#2F4F2F]">{{ $primaryContact }}</p>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div>
            <span class="text-sm font-medium text-[#2F4F2F]/80">Guest Arrival Date</span>
            <p class="text-sm text-[#2F4F2F]">{{ $guestArrivalDate }}</p>
        </div>
        <div>
            <span class="text-sm font-medium text-[#2F4F2F]/80">Submitted At</span>
            <p class="text-sm text-[#2F4F2F]">{{ $submittedAt }}</p>
        </div>
    </div>

    @if($description !== '—')
        <div>
            <span class="text-sm font-medium text-[#2F4F2F]/80">Comments</span>
            <p class="text-sm whitespace-pre-line text-[#2F4F2F]">{{ $description }}</p>
        </div>
    @endif
</div>

