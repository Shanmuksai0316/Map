<div class="space-y-4">
    <div>
        <h3 class="text-base font-semibold text-[#2F4F2F]">{{ $title }}</h3>
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div>
            <span class="text-sm font-medium text-[#2F4F2F]/80">Status</span>
            <p class="text-sm text-[#2F4F2F]">{{ $status }}</p>
        </div>
        <div>
            <span class="text-sm font-medium text-[#2F4F2F]/80">Audience</span>
            <p class="text-sm text-[#2F4F2F]">{{ $audience }}</p>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div>
            <span class="text-sm font-medium text-[#2F4F2F]/80">Hostel</span>
            <p class="text-sm text-[#2F4F2F]">{{ $hostel }}</p>
        </div>
        <div>
            <span class="text-sm font-medium text-[#2F4F2F]/80">Channels</span>
            <p class="text-sm text-[#2F4F2F]">{{ $channels }}</p>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div>
            <span class="text-sm font-medium text-[#2F4F2F]/80">Publish At</span>
            <p class="text-sm text-[#2F4F2F]">{{ $publishAt }}</p>
        </div>
        <div>
            <span class="text-sm font-medium text-[#2F4F2F]/80">Expires At</span>
            <p class="text-sm text-[#2F4F2F]">{{ $expiresAt }}</p>
        </div>
    </div>

    <div>
        <span class="text-sm font-medium text-[#2F4F2F]/80">Created By</span>
        <p class="text-sm text-[#2F4F2F]">{{ $createdBy }}</p>
    </div>

    @if($attachmentUrl)
        <div>
            <span class="text-sm font-medium text-[#2F4F2F]/80">Attachment</span>
            <p class="text-sm">
                <a href="{{ $attachmentUrl }}" target="_blank" class="text-[#2F4F2F] hover:text-[#2F4F2F]/90 underline">
                    Open attachment
                </a>
            </p>
        </div>
    @endif

    <div>
        <span class="text-sm font-medium text-[#2F4F2F]/80">Content</span>
        <p class="mt-1 whitespace-pre-line text-sm text-[#2F4F2F]">{{ $content }}</p>
    </div>
</div>

