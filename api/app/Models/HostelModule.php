<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HostelModule extends Model
{
    protected $fillable = ['hostel_id', 'module_key'];

    public function hostel()
    {
        return $this->belongsTo(Hostel::class);
    }
}
