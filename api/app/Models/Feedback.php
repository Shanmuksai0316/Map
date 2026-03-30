<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Feedback extends Model
{
    use HasFactory;

    protected $table = 'feedback';

    protected $fillable = [
        'tenant_id',
        'student_id',
        'hostel_id',
        'category',
        'rating',
        'comments',
        'submitted_at',
    ];

    protected $casts = [
        'rating' => 'integer',
        'submitted_at' => 'datetime',
    ];

    /**
     * Valid feedback categories.
     */
    public const CATEGORIES = [
        'housekeeping' => 'Housekeeping',
        'food' => 'Food',
        'maintenance' => 'Maintenance',
        'laundry' => 'Laundry',
        'sports' => 'Sports',
        'security' => 'Security',
        'general' => 'General',
    ];

    /**
     * Get the tenant that owns the feedback.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the student who submitted the feedback.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Get the hostel this feedback is about.
     */
    public function hostel(): BelongsTo
    {
        return $this->belongsTo(Hostel::class);
    }

    /**
     * Scope to filter by category.
     */
    public function scopeCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope to filter by rating.
     */
    public function scopeRating($query, int $rating)
    {
        return $query->where('rating', $rating);
    }

    /**
     * Get the category label.
     */
    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORIES[$this->category] ?? ucfirst($this->category);
    }
}

