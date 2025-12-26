<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, HasRoles, HasUuids;

    protected $guard_name = 'api';
    protected $table = 'users';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone_number',
        'company_name',
        'stripe_customer_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get properties owned by this user (as wholesaler)
     */
    public function properties(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Property::class, 'wholesaler_id');
    }

    /**
     * Get messages sent by this user
     */
    public function sentMessages(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    /**
     * Get messages received by this user
     */
    public function receivedMessages(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Message::class, 'receiver_id');
    }

    /**
     * Get conversations where user is participant one
     */
    public function conversationsAsParticipantOne(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Conversation::class, 'participant_one_id');
    }

    /**
     * Get conversations where user is participant two
     */
    public function conversationsAsParticipantTwo(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Conversation::class, 'participant_two_id');
    }

    /**
     * Get all conversations for this user
     */
    public function conversations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->conversationsAsParticipantOne()
            ->union($this->conversationsAsParticipantTwo()->toBase());
    }

    /**
     * Get analytics events triggered by this user
     */
    public function analytics(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Analytic::class);
    }

    /**
     * Get subscription for this user
     */
    public function subscription(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Subscription::class)->where('status', 'active')->latest();
    }

    /**
     * Get all subscriptions for this user
     */
    public function subscriptions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Get rehab estimates requested by this user
     */
    public function requestedRehabEstimates(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PropertyRehabEstimate::class, 'requested_by');
    }

    /**
     * Check if user has active subscription
     */
    public function hasActiveSubscription(): bool
    {
        return $this->subscription()->exists();
    }

    /**
     * Get current subscription plan
     */
    public function currentPlan(): ?\App\Models\SubscriptionPlan
    {
        $subscription = $this->subscription;
        return $subscription ? $subscription->plan : null;
    }

    /**
     * Get all transactions for this user
     */
    public function transactions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
