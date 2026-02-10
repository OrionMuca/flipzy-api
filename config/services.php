<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'attom' => [
        'api_key' => env('ATTOM_API_KEY'),
        'api_url' => env('ATTOM_API_URL', 'https://api.gateway.attomdata.com'),
        'rate_limit' => [
            'requests_per_minute' => env('ATTOM_RATE_LIMIT', 60),
            'requests_per_hour' => env('ATTOM_RATE_LIMIT_HOUR', 1000),
        ],
        'timeout' => env('ATTOM_TIMEOUT', 30),
        'retry' => [
            'max_attempts' => env('ATTOM_MAX_RETRIES', 3),
            'backoff_multiplier' => env('ATTOM_BACKOFF', 2),
        ],
        'cache' => [
            'ttl_days' => env('ATTOM_CACHE_TTL', 7),
            'enabled' => env('ATTOM_CACHE_ENABLED', true),
        ],
        'use_estated_fallback' => env('ATTOM_USE_ESTATED_FALLBACK', true),
    ],

    'estated' => [
        'api_key' => env('ESTATED_API_KEY'),
        'api_url' => env('ESTATED_API_URL', 'https://apis.estated.com'),
        // Note: Estated is being migrated to ATTOM platform (deprecation by 2026)
        // Free trial: 100 API calls available
        // Pricing: Starts at $499/month for 5,000 calls
        // Fallback is only used when ATTOM data is incomplete (missing bedrooms/bathrooms)
    ],

    'geo' => [
        'service' => env('GEO_SERVICE', 'openstreetmap'), // openstreetmap, mapbox, or google
        'mapbox_token' => env('MAPBOX_ACCESS_TOKEN'),
        'google_places_api_key' => env('GOOGLE_PLACES_API_KEY'),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-3.5-turbo'),
        'organization' => env('OPENAI_ORGANIZATION'),
    ],

    'rehab_calculations' => [
        // Base cost per square foot by property type
        'cost_per_sqft' => [
            'house' => 50,
            'condo' => 45,
            'townhouse' => 48,
            'multifamily' => 55,
            'commercial' => 60,
            'land' => 0,
            'mobile' => 40,
        ],
        // Bedroom and bathroom multipliers
        'bedroom_multiplier' => 2000, // Cost per bedroom
        'bathroom_multiplier' => 5000, // Cost per bathroom
        // Condition multipliers (applied to base cost)
        'condition_multipliers' => [
            'excellent' => 0.3,  // 30% of base (minimal work)
            'good' => 0.5,       // 50% of base
            'fair' => 1.0,       // 100% of base (standard)
            'poor' => 1.5,       // 150% of base
            'needs_repair' => 2.0, // 200% of base
        ],
        // Age adjustment factors (based on year built)
        'age_adjustments' => [
            'new' => 0.3,      // Built after 2010
            'modern' => 0.5,   // Built 1990-2010
            'average' => 1.0,  // Built 1970-1990
            'old' => 1.3,      // Built 1950-1970
            'very_old' => 1.6, // Built before 1950
        ],
        // Regional cost multipliers by state (some examples)
        'regional_multipliers' => [
            'CA' => 1.3,  // California - higher costs
            'NY' => 1.25, // New York - higher costs
            'TX' => 0.9,  // Texas - lower costs
            'FL' => 0.95, // Florida - slightly lower
            'CO' => 1.0,  // Colorado - average
            'IL' => 1.0,  // Illinois - average
            // Default for other states
            'default' => 1.0,
        ],
        // Cost breakdown percentages (must sum to ~100%)
        'breakdown_percentages' => [
            'kitchen' => 25,
            'bathrooms' => 20,
            'flooring' => 12,
            'paint' => 8,
            'electrical' => 10,
            'plumbing' => 10,
            'hvac' => 8,
            'roof' => 0,    // Only if condition indicates
            'windows' => 0,  // Only if condition indicates
            'other' => 7,
        ],
        // Image count heuristic (more images = better documented = potentially better condition)
        'image_heuristic' => [
            'min_images_for_confidence' => 5,
            'confidence_adjustment' => 0.1, // 10% reduction if well documented
        ],
    ],

    'stripe' => [
        'secret_key' => env('STRIPE_SECRET_KEY'),
        'public_key' => env('STRIPE_PUBLIC_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Passport Password Grant (login / register token)
    |--------------------------------------------------------------------------
    | If set, auth works even when cache is lost (e.g. container restart, cache:clear).
    | Run: php artisan passport:ensure-password-grant-client
    | Then copy the printed ID and secret into .env.
    */
    'passport' => [
        'password_grant_client_id' => env('PASSPORT_PASSWORD_GRANT_CLIENT_ID'),
        'password_grant_client_secret' => env('PASSPORT_PASSWORD_GRANT_CLIENT_SECRET'),
    ],

];
