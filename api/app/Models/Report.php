<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'params',
        'status',
        'storage_path',
    ];

    protected $casts = [
        'params' => 'array',
    ];
}

