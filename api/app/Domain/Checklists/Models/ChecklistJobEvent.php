<?php

namespace App\Domain\Checklists\Models;

use Illuminate\Database\Eloquent\Model;

class ChecklistJobEvent extends Model
{
    protected $fillable = [
        'tenant_id',
        'instance_id',
        'event_type',
        'phase',
    ];
}
