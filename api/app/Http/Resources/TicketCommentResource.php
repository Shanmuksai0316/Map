<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketCommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'attachments' => $this->attachments,
            'is_internal' => $this->is_internal,
            'created_at' => $this->created_at->toISOString(),
            'author' => [
                'id' => $this->user?->id,
                'name' => $this->user?->name ?? 'System',
                'role' => $this->user?->roles->first()?->name ?? 'Unknown',
            ],
        ];
    }
}
