<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class WaitingListEntry extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'email',
        'name',
        'subscription_plan_id',
        'coupon_id',
        'coupon_code',
        'status',
        'stripe_customer_id',
        'stripe_subscription_id',
        'stripe_checkout_session_id',
        'original_price',
        'discounted_price',
        'discount_amount',
        'verification_token',
        'email_verified_at',
        'metadata',
        'account_created_at',
    ];

    protected $casts = [
        'original_price' => 'decimal:2',
        'discounted_price' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'email_verified_at' => 'datetime',
        'account_created_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Get the subscription plan
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    /**
     * Get the coupon used
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /**
     * Get all transactions for this entry
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(WaitingListTransaction::class);
    }

    /**
     * Generate verification token
     */
    public function generateVerificationToken(): string
    {
        $token = Str::random(64);
        $this->update(['verification_token' => $token]);
        return $token;
    }

    /**
     * Mark email as verified
     */
    public function markEmailAsVerified(): void
    {
        $this->update([
            'email_verified_at' => now(),
        ]);
    }

    /**
     * Check if email is verified
     */
    public function isEmailVerified(): bool
    {
        return $this->email_verified_at !== null;
    }

    /**
     * Mark payment as completed
     */
    public function markPaymentCompleted(): void
    {
        $this->update([
            'status' => 'payment_completed',
        ]);
    }

    /**
     * Mark account as created
     */
    public function markAccountCreated(): void
    {
        $this->update([
            'status' => 'account_created',
            'account_created_at' => now(),
        ]);
    }

    /**
     * Check if payment is completed
     */
    public function isPaymentCompleted(): bool
    {
        return $this->status === 'payment_completed';
    }

    /**
     * Check if account is created
     */
    public function isAccountCreated(): bool
    {
        return $this->status === 'account_created';
    }

    /**
     * Scope: Pending entries
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope: Payment completed entries
     */
    public function scopePaymentCompleted($query)
    {
        return $query->where('status', 'payment_completed');
    }

    /**
     * Scope: Account created entries
     */
    public function scopeAccountCreated($query)
    {
        return $query->where('status', 'account_created');
    }
}
