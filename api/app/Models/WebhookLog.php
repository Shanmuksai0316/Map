<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebhookLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'source',
        'event_type',
        'event_id',
        'valid_signature',
        'payload',
        'status',
        'attempts',
        'next_retry_at',
        'processed_at',
        'last_error',
        'received_at',
    ];

    protected $casts = [
        'valid_signature' => 'boolean',
        'payload' => 'array',
        'received_at' => 'datetime',
        'next_retry_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    /**
     * Check if event already exists (for deduplication)
     */
    public static function eventExists(string $eventId): bool
    {
        return self::where('event_id', $eventId)->exists();
    }

    /**
     * Log a webhook event
     */
    public static function logWebhook(
        string $source,
        string $eventType,
        string $eventId,
        bool $validSignature,
        array $payload
    ): self {
        return self::create([
            'source' => $source,
            'event_type' => $eventType,
            'event_id' => $eventId,
            'valid_signature' => $validSignature,
            'payload' => $payload,
            'status' => 'queued',
            'attempts' => 0,
            'received_at' => now(),
        ]);
    }
}

