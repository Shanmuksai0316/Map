<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ImportJob */
class ImportJobResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'tenant_id' => (string) $this->tenant_id,
            'kind' => $this->kind,
            'status' => $this->status,
            'filename' => $this->filename,
            'total_rows' => $this->total_rows,
            'error_rows' => $this->error_rows,
            'processed_rows' => $this->processed_rows,
            'inserted_rows' => $this->inserted_rows,
            'updated_rows' => $this->updated_rows,
            'meta' => $this->meta,
            'committed_at' => $this->committed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'errors' => ImportErrorResource::collection($this->whenLoaded('errors')),
        ];
    }
}
