<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
        'description',
    ];

    /**
     * Get the value attribute with proper type casting
     */
    public function getValueAttribute($value)
    {
        $type = $this->attributes['type'] ?? 'string';
        
        switch ($type) {
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'integer':
                return (int) $value;
            case 'json':
                return json_decode($value, true);
            default:
                return $value;
        }
    }
    
    /**
     * Set the value attribute with proper type conversion
     */
    public function setValueAttribute($value)
    {
        // Use the type attribute if already set, otherwise default to 'string'
        $type = $this->attributes['type'] ?? $this->type ?? 'string';
        
        // Only convert if type is set and value needs conversion
        if ($type !== 'string') {
            $this->attributes['value'] = match ($type) {
                'boolean' => $value ? 'true' : 'false',
                'integer' => (string) $value,
                'json' => json_encode($value),
                default => (string) $value,
            };
        } else {
            $this->attributes['value'] = (string) $value;
        }
    }

    /**
     * Get a setting value by key
     */
    public static function get(string $key, $default = null)
    {
        $setting = static::where('key', $key)->first();
        
        if (!$setting) {
            return $default;
        }
        
        return $setting->value;
    }

    /**
     * Set a setting value by key
     */
    public static function set(string $key, $value, string $type = 'string', ?string $description = null): void
    {
        $setting = static::firstOrNew(['key' => $key]);
        
        $setting->value = match ($type) {
            'boolean' => $value ? 'true' : 'false',
            'integer' => (string) $value,
            'json' => json_encode($value),
            default => (string) $value,
        };
        
        $setting->type = $type;
        
        if ($description !== null) {
            $setting->description = $description;
        }
        
        $setting->save();
    }
}

