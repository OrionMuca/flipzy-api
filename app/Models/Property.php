<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Property extends Model
{
    use HasFactory, SoftDeletes, HasUuids;

    protected $fillable = [
        'wholesaler_id',
        'title',
        'description',
        'property_type',
        'status',
        'address',
        'city',
        'state',
        'zip_code',
        'country',
        'latitude',
        'longitude',
        'bedrooms',
        'bathrooms',
        'square_feet',
        'lot_size',
        'year_built',
        'condition',
        'asking_price',
        'arv',
        'repair_estimate',
        'potential_profit',
        'attom_data',
        'attom_sale_history',
        'attom_comparable_sales',
        'attom_property_events',
        'attom_enrichment_status',
        'attom_enriched_at',
        'estated_data',
        'enriched_at',
        'is_featured',
        'is_verified',
        'allow_inquiries',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'asking_price' => 'decimal:2',
        'arv' => 'decimal:2',
        'repair_estimate' => 'decimal:2',
        'potential_profit' => 'decimal:2',
        'attom_data' => 'array',
        'attom_sale_history' => 'array',
        'attom_comparable_sales' => 'array',
        'attom_property_events' => 'array',
        'attom_enrichment_status' => 'array',
        'attom_enriched_at' => 'datetime',
        'estated_data' => 'array',
        'enriched_at' => 'datetime',
        'is_featured' => 'boolean',
        'is_verified' => 'boolean',
        'allow_inquiries' => 'boolean',
    ];

    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }

    /**
     * Get the wholesaler (user) who owns this property
     */
    public function wholesaler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'wholesaler_id');
    }

    /**
     * Get all images for this property
     */
    public function images(): HasMany
    {
        return $this->hasMany(PropertyImage::class)->orderBy('order');
    }

    /**
     * Get the primary image
     */
    public function primaryImage(): HasMany
    {
        return $this->hasMany(PropertyImage::class)->where('is_primary', true);
    }

    /**
     * Get all analytics events for this property
     */
    public function analytics(): HasMany
    {
        return $this->hasMany(Analytic::class);
    }

    /**
     * Get all conversations about this property
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    /**
     * Get all rehab estimates for this property
     */
    public function rehabEstimates(): HasMany
    {
        return $this->hasMany(PropertyRehabEstimate::class);
    }

    /**
     * Get the latest rehab estimate (single record, by created_at)
     */
    public function latestRehabEstimate(): HasOne
    {
        return $this->hasOne(PropertyRehabEstimate::class)->latestOfMany('created_at');
    }

    /**
     * Get wishlist entries for this property
     */
    public function wishlistEntries(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }

    /**
     * Get users who wishlisted this property
     */
    public function wishlistedBy(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(User::class, 'wishlists', 'property_id', 'user_id')
            ->withTimestamps();
    }

    /**
     * Get analytics by event type
     */
    public function analyticsByType(string $type): HasMany
    {
        return $this->hasMany(Analytic::class)->where('event_type', $type);
    }

    /**
     * Scope: Active properties
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope: Featured properties
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope: Verified properties
     */
    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    /**
     * Scope: USA-only properties
     */
    public function scopeUsaOnly($query)
    {
        return $query->where('country', 'US');
    }

    /**
     * Check if property is in USA
     */
    public function isUsa(): bool
    {
        return $this->country === 'US';
    }

    /**
     * Validate US state code
     */
    public static function isValidUsState(string $state): bool
    {
        $validStates = [
            'AL', 'AK', 'AZ', 'AR', 'CA', 'CO', 'CT', 'DE', 'FL', 'GA',
            'HI', 'ID', 'IL', 'IN', 'IA', 'KS', 'KY', 'LA', 'ME', 'MD',
            'MA', 'MI', 'MN', 'MS', 'MO', 'MT', 'NE', 'NV', 'NH', 'NJ',
            'NM', 'NY', 'NC', 'ND', 'OH', 'OK', 'OR', 'PA', 'RI', 'SC',
            'SD', 'TN', 'TX', 'UT', 'VT', 'VA', 'WA', 'WV', 'WI', 'WY',
            'DC'
        ];
        
        return in_array(strtoupper($state), $validStates);
    }

    /**
     * Validate US ZIP code format
     */
    public static function isValidUsZip(string $zip): bool
    {
        // US ZIP: 5 digits or 5+4 format (12345 or 12345-6789)
        return (bool) preg_match('/^\d{5}(-\d{4})?$/', $zip);
    }

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        // Set default country to US if not provided
        static::creating(function ($property) {
            if (empty($property->country)) {
                $property->country = 'US';
            }
        });
    }


    /**
     * Check if property has been enriched with ATTOM data
     */
    public function hasAttomData(): bool
    {
        return !empty($this->attom_data) || !empty($this->attom_enriched_at);
    }

    /**
     * Check if specific ATTOM endpoint has been enriched
     */
    public function hasAttomEndpoint(string $endpoint): bool
    {
        $status = $this->attom_enrichment_status ?? [];
        return $status[$endpoint] ?? false;
    }

    /**
     * Get latest sale price from ATTOM data
     */
    public function getLatestSalePrice(): ?float
    {
        $saleHistory = $this->attom_sale_history;
        if (!$saleHistory || empty($saleHistory['latest_sale'])) {
            return null;
        }

        return $saleHistory['latest_sale']['sale_price'] ?? null;
    }

    /**
     * Get comparable sales count
     */
    public function getComparableSalesCount(): int
    {
        $comps = $this->attom_comparable_sales;
        return $comps['total_comps'] ?? 0;
    }

    /**
     * Get property events count by category
     */
    public function getPropertyEventsCount(string $category = 'all'): int
    {
        $events = $this->attom_property_events;
        if (!$events) {
            return 0;
        }

        if ($category === 'all') {
            return $events['total_events'] ?? 0;
        }

        $categorized = $events['categorized'] ?? [];
        return count($categorized[$category] ?? []);
    }
}
