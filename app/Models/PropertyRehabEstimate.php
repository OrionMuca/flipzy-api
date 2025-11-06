<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyRehabEstimate extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'property_id',
        'requested_by',
        'ai_response',
        'property_data',
        'model_used',
        'estimated_cost',
        'tokens_used',
    ];

    protected $casts = [
        'property_data' => 'array',
        'estimated_cost' => 'decimal:2',
    ];

    /**
     * Get the property this estimate is for
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * Get the user who requested this estimate
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
