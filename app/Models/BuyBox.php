<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuyBox extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'preferred_cities',
        'preferred_zip_codes',
        'target_counties',
        'target_neighborhoods',
        'must_have_amenities',
        'min_bedrooms',
        'max_bedrooms',
        'min_bathrooms',
        'max_bathrooms',
        'min_square_feet',
        'max_square_feet',
        'min_lot_size',
        'max_lot_size',
        'property_conditions',
        'property_types',
        'has_adu_potential',
        'construction_types',
        'has_pool',
        'is_waterfront',
        'layout_types',
        'funding_methods',
        'min_profit',
        'min_roi',
        'target_cap_rate',
        'desired_occupancy_rate',
        'expected_monthly_cash_flow',
        'expected_annual_cash_flow',
        'investment_strategies',
    ];

    protected $casts = [
        'preferred_cities' => 'array',
        'preferred_zip_codes' => 'array',
        'target_counties' => 'array',
        'target_neighborhoods' => 'array',
        'must_have_amenities' => 'array',
        'min_bathrooms' => 'decimal:2',
        'max_bathrooms' => 'decimal:2',
        'property_conditions' => 'array',
        'property_types' => 'array',
        'has_adu_potential' => 'boolean',
        'construction_types' => 'array',
        'has_pool' => 'boolean',
        'is_waterfront' => 'boolean',
        'layout_types' => 'array',
        'funding_methods' => 'array',
        'min_profit' => 'decimal:2',
        'min_roi' => 'decimal:2',
        'target_cap_rate' => 'decimal:2',
        'desired_occupancy_rate' => 'decimal:2',
        'expected_monthly_cash_flow' => 'decimal:2',
        'expected_annual_cash_flow' => 'decimal:2',
        'investment_strategies' => 'array',
    ];

    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }

    /**
     * Get the user who owns this buy box
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
