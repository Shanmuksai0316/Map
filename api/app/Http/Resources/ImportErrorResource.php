<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ImportError */
class ImportErrorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'row_number' => $this->row_number,
            'code' => $this->code,
            'message' => $this->message,
            'row_snapshot' => $this->row_snapshot,
        ];
    }
}
