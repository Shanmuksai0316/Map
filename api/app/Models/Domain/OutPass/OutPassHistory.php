<?php

namespace App\Models\Domain\OutPass;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutPassHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'out_pass_id',
        'acted_by',
        'from_status',
        'to_status',
        'note',
        'timeline_label',
        'timeline_description',
        'changed_at',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    public function outPass(): BelongsTo
    {
        return $this->belongsTo(OutPass::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acted_by');
    }
}
