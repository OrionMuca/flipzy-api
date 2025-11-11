<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'code',
        'name',
        'description',
        'discount_type',
        'discount_value',
        'minimum_amount',
        'maximum_discount',
        'valid_from',
        'valid_until',
        'usage_limit',
        'usage_count',
        'user_limit',
        'is_active',
        'applicable_plans',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'minimum_amount' => 'decimal:2',
        'maximum_discount' => 'decimal:2',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
        'usage_limit' => 'integer',
        'usage_count' => 'integer',
        'user_limit' => 'integer',
        'is_active' => 'boolean',
        'applicable_plans' => 'array',
    ];

    /**
     * Get all waiting list entries that used this coupon
     */
    public function waitingListEntries(): HasMany
    {
        return $this->hasMany(WaitingListEntry::class);
    }

    /**
     * Check if coupon is currently valid
     */
    public function isValid(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $now = now();
        if ($this->valid_from->isFuture()) {
            return false;
        }

        if ($this->valid_until && $this->valid_until->isPast()) {
            return false;
        }

        if ($this->usage_limit && $this->usage_count >= $this->usage_limit) {
            return false;
        }

        return true;
    }

    /**
     * Check if coupon can be used for a specific plan
     */
    public function isApplicableToPlan(string $planId): bool
    {
        if ($this->applicable_plans === null) {
            return true; // Applies to all plans
        }

        return in_array($planId, $this->applicable_plans);
    }

    /**
     * Check if user has reached usage limit for this coupon
     */
    public function hasReachedUserLimit(string $email): bool
    {
        $usageCount = $this->waitingListEntries()
            ->where('email', $email)
            ->count();

        return $usageCount >= $this->user_limit;
    }

    /**
     * Calculate discount amount for a given price
     */
    public function calculateDiscount(float $price): float
    {
        if ($this->discount_type === 'percentage') {
            $discount = ($price * $this->discount_value) / 100;
            
            // Apply maximum discount limit if set
            if ($this->maximum_discount && $discount > $this->maximum_discount) {
                $discount = $this->maximum_discount;
            }
            
            return round($discount, 2);
        } else {
            // Fixed amount
            return min($this->discount_value, $price); // Can't discount more than the price
        }
    }

    /**
     * Increment usage count
     */
    public function incrementUsage(): void
    {
        $this->increment('usage_count');
    }

    /**
     * Scope: Active coupons
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Valid coupons (active and within date range)
     */
    public function scopeValid($query)
    {
        $now = now();
        return $query->where('is_active', true)
            ->where('valid_from', '<=', $now)
            ->where(function ($q) use ($now) {
                $q->whereNull('valid_until')
                  ->orWhere('valid_until', '>=', $now);
            })
            ->where(function ($q) {
                $q->whereNull('usage_limit')
                  ->orWhereColumn('usage_count', '<', 'usage_limit');
            });
    }
}
