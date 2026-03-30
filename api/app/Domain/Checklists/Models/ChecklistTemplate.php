<?php

namespace App\Domain\Checklists\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChecklistTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'role',
        'title',
        'tasks',
        'active',
        'created_by_user_id',
    ];

    protected $casts = [
        'tasks' => 'array',
        'active' => 'boolean',
    ];

    public function instances(): HasMany
    {
        return $this->hasMany(ChecklistInstance::class, 'template_id');
    }
}

