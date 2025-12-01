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
        'subscription_plan_id', // Nullable, kept for backward compatibility
        'coupon_id',
        'coupon_code',
        'status',
        'verification_token',
        'email_verified_at',
        'metadata',
        'account_created_at',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'account_created_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Get the coupon used (for future use)
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /**
     * Get all transactions for this entry
     * @deprecated Transactions are no longer used in waiting list system
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
     * @deprecated Payment status no longer exists in waiting list system
     */
    public function scopePaymentCompleted($query)
    {
        // Return empty query since payment_completed status no longer exists
        return $query->whereRaw('1 = 0');
    }

    /**
     * Scope: Account created entries
     */
    public function scopeAccountCreated($query)
    {
        return $query->where('status', 'account_created');
    }
}
