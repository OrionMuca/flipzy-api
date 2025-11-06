<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiLog extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'service',
        'endpoint',
        'method',
        'status_code',
        'request_body',
        'response_body',
        'response_time_ms',
        'success',
        'error_message',
        'property_id',
        'user_id',
    ];

    protected $casts = [
        'success' => 'boolean',
        'response_time_ms' => 'integer',
        'status_code' => 'integer',
    ];

    /**
     * Get the property this API call was for (if any)
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * Get the user who triggered this API call (if any)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope: Successful API calls
     */
    public function scopeSuccessful($query)
    {
        return $query->where('success', true);
    }

    /**
     * Scope: Failed API calls
     */
    public function scopeFailed($query)
    {
        return $query->where('success', false);
    }

    /**
     * Scope: By service
     */
    public function scopeByService($query, string $service)
    {
        return $query->where('service', $service);
    }
}
