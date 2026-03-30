<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Notice */
class NoticeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'tenant_id' => (string) $this->tenant_id,
            'campus_id' => $this->campus_id ? (string) $this->campus_id : null,
            'hostel_id' => $this->hostel_id ? (string) $this->hostel_id : null,
            'title' => $this->title,
            'body' => $this->body,
            'status' => $this->status,
            'audience' => $this->audience,
            'channels' => $this->channels,
            'publish_at' => $this->publish_at?->toIso8601String(),
            'published_at' => $this->published_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'is_active' => $this->isActive(),
            'attachments' => $this->whenLoaded('attachments', function () {
                return $this->attachments->map(function ($attachment) {
                    return [
                        'id' => (string) $attachment->id,
                        'filename' => $attachment->filename,
                        'mime_type' => $attachment->mime_type,
                        'size' => $attachment->size,
                        'download_url' => $attachment->status === 'clean'
                            ? ($attachment->key ? url('/storage/' . $attachment->key) : null)
                            : null,
                    ];
                });
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
