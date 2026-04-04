<?php

namespace App\Services;

use App\Models\ApiLog;
use App\Models\Property;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AttomService
{
    protected string $apiKey;
    protected string $baseUrl;
    protected int $timeout;
    protected array $rateLimitConfig;
    protected array $retryConfig;
    protected array $cacheConfig;
    
    // Rate limiting tracking
    protected static array $requestTimestamps = [];
    protected static int $hourlyRequestCount = 0;
    protected static ?int $hourlyResetTime = null;

    public function __construct()
    {
        $config = config('services.attom', []);
        $this->apiKey = $config['api_key'] ?? env('ATTOM_API_KEY');
        $this->baseUrl = $config['api_url'] ?? env('ATTOM_API_URL', 'https://api.gateway.attomdata.com');
        $this->timeout = $config['timeout'] ?? 30;
        $this->rateLimitConfig = $config['rate_limit'] ?? [
            'requests_per_minute' => 60, 
            'requests_per_hour' => 1000
        ];
        $this->retryConfig = $config['retry'] ?? [
            'max_attempts' => 3, 
            'backoff_multiplier' => 2
        ];
        $this->cacheConfig = $config['cache'] ?? [
            'ttl_days' => 7, 
            'enabled' => true
        ];

        if (!$this->apiKey) {
            throw new \RuntimeException('ATTOM API key is not configured');
        }
    }

    /**
     * Unified request handler with retry logic and rate limiting
     */
    protected function makeRequest(
        string $endpoint,
        string $method = 'GET',
        array $params = [],
        ?string $cacheKey = null,
        bool $forceFresh = false,
        ?Property $property = null
    ): ?array {
        // Check rate limits before making request
        $this->checkRateLimit();

        // Check cache if enabled and not forcing fresh
        if ($cacheKey && $this->cacheConfig['enabled'] && !$forceFresh) {
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                Log::debug('ATTOM API cache hit', [
                    'endpoint' => $endpoint,
                    'cache_key' => $cacheKey,
                ]);
                return $cached;
            }
        }

        $maxAttempts = $this->retryConfig['max_attempts'] ?? 3;
        $backoffMultiplier = $this->retryConfig['backoff_multiplier'] ?? 2;
        $attempt = 0;
        $lastException = null;

        while ($attempt < $maxAttempts) {
            try {
                $startTime = microtime(true);
                
                $httpClient = Http::timeout($this->timeout)
                    ->withHeaders([
                        'apikey' => $this->apiKey,
                        'Accept' => 'application/json',
                    ]);

                if (strtoupper($method) === 'GET') {
                    $response = $httpClient->get("{$this->baseUrl}{$endpoint}", $params);
                } else {
                    $response = $httpClient->post("{$this->baseUrl}{$endpoint}", $params);
                }

                $responseTime = (int) ((microtime(true) - $startTime) * 1000);
                $httpStatusCode = $response->status();
                $data = $response->json();
                
                // Track rate limit
                $this->recordRequest();

                // Parse ATTOM response status
                $statusCode = $data['status']['code'] ?? null;
                $statusMessage = $data['status']['msg'] ?? 'Unknown';
                $total = $data['status']['total'] ?? 0;
                
                $hasResult = ($statusCode === 0 && $total > 0);
                $noResult = ($statusCode === 400 && $statusMessage === 'SuccessWithoutResult');
                $isError = (!$hasResult && !$noResult);

                // Log API call
                $this->logApiCall(
                    'attom',
                    $endpoint,
                    $method,
                    $params,
                    $response->body(),
                    $httpStatusCode,
                    $responseTime,
                    $hasResult,
                    $isError ? $statusMessage : ($noResult ? 'No result found' : null),
                    $property
                );

                // Handle permanent errors (don't retry)
                if ($this->isPermanentError($httpStatusCode, $statusCode, $statusMessage)) {
                    Log::warning('ATTOM API permanent error', [
                        'endpoint' => $endpoint,
                        'http_status' => $httpStatusCode,
                        'status_code' => $statusCode,
                        'message' => $statusMessage,
                    ]);
                    return null;
                }

                // Handle temporary errors (retry)
                if ($this->isTemporaryError($httpStatusCode, $statusCode)) {
                    $attempt++;
                    if ($attempt < $maxAttempts) {
                        $delay = $backoffMultiplier ** $attempt;
                        Log::warning("ATTOM API temporary error, retrying in {$delay}s", [
                            'endpoint' => $endpoint,
                            'attempt' => $attempt,
                            'http_status' => $httpStatusCode,
                            'status_code' => $statusCode,
                        ]);
                        sleep($delay);
                        continue;
                    }
                    Log::error('ATTOM API max retries exceeded', [
                        'endpoint' => $endpoint,
                        'attempts' => $attempt,
                    ]);
                    return null;
                }

                // Success or no result
                if ($noResult) {
                    Log::info('ATTOM API returned no results', [
                        'endpoint' => $endpoint,
                        'params' => $params,
                    ]);
                    return null;
                }

                $result = $data;

                // Cache result if enabled
                if ($cacheKey && $this->cacheConfig['enabled'] && $hasResult) {
                    $ttl = now()->addDays($this->cacheConfig['ttl_days'] ?? 7);
                    Cache::put($cacheKey, $result, $ttl);
                    Log::debug('ATTOM API result cached', [
                        'endpoint' => $endpoint,
                        'cache_key' => $cacheKey,
                        'ttl_days' => $this->cacheConfig['ttl_days'],
                    ]);
                }

                return $result;

            } catch (\Exception $e) {
                $lastException = $e;
                $attempt++;

                // Check if it's a retryable exception
                if ($this->isRetryableException($e) && $attempt < $maxAttempts) {
                    $delay = $backoffMultiplier ** $attempt;
                    Log::warning("ATTOM API exception, retrying in {$delay}s", [
                        'endpoint' => $endpoint,
                        'attempt' => $attempt,
                        'error' => $e->getMessage(),
                    ]);
                    sleep($delay);
                    continue;
                }

                // Log final failure
                $this->logApiCall(
                    'attom',
                    $endpoint,
                    $method,
                    $params,
                    null,
                    null,
                    null,
                    false,
                    $e->getMessage(),
                    $property
                );

                Log::error('ATTOM API exception', [
                    'endpoint' => $endpoint,
                    'message' => $e->getMessage(),
                    'attempt' => $attempt,
                    'trace' => $e->getTraceAsString(),
                ]);

                if ($attempt >= $maxAttempts) {
                    return null;
                }
            }
        }

        return null;
    }

    /**
     * Check if error is permanent (don't retry)
     */
    protected function isPermanentError(int $httpStatusCode, ?int $statusCode, string $statusMessage): bool
    {
        // Invalid API key
        if ($httpStatusCode === 401) {
            return true;
        }

        // Property not found
        if ($httpStatusCode === 400 && $statusMessage === 'SuccessWithoutResult') {
            return true;
        }

        // Invalid parameters (ATTOM status codes)
        $permanentStatusCodes = [-8, -6, -5, -4, 36, 37, 38, 39];
        if (in_array($statusCode, $permanentStatusCodes)) {
            return true;
        }

        // Other 4xx errors (except rate limit)
        if ($httpStatusCode >= 400 && $httpStatusCode < 500 && $httpStatusCode !== 429) {
            return true;
        }

        return false;
    }

    /**
     * Check if error is temporary (retry)
     */
    protected function isTemporaryError(int $httpStatusCode, ?int $statusCode): bool
    {
        // Rate limit
        if ($httpStatusCode === 429) {
            return true;
        }

        // Server errors
        if ($httpStatusCode >= 500) {
            return true;
        }

        return false;
    }

    /**
     * Check if exception is retryable
     */
    protected function isRetryableException(\Exception $e): bool
    {
        // Network/timeout errors are retryable
        $retryableMessages = ['timeout', 'connection', 'network', 'timed out', 'could not resolve host'];
        $message = strtolower($e->getMessage());
        
        foreach ($retryableMessages as $keyword) {
            if (str_contains($message, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check and enforce rate limits
     */
    protected function checkRateLimit(): void
    {
        $now = time();
        
        // Reset hourly counter if needed
        if (self::$hourlyResetTime === null || $now >= self::$hourlyResetTime) {
            self::$hourlyRequestCount = 0;
            self::$hourlyResetTime = $now + 3600; // Next hour
        }

        // Check hourly limit
        $hourlyLimit = $this->rateLimitConfig['requests_per_hour'] ?? 1000;
        if (self::$hourlyRequestCount >= $hourlyLimit) {
            $waitTime = self::$hourlyResetTime - $now;
            if ($waitTime > 0) {
                Log::warning("ATTOM API hourly rate limit reached, waiting {$waitTime}s");
                sleep($waitTime);
                self::$hourlyRequestCount = 0;
                self::$hourlyResetTime = time() + 3600;
            }
        }

        // Check per-minute limit
        $minuteLimit = $this->rateLimitConfig['requests_per_minute'] ?? 60;
        $oneMinuteAgo = $now - 60;
        
        // Remove timestamps older than 1 minute
        self::$requestTimestamps = array_filter(
            self::$requestTimestamps,
            fn($timestamp) => $timestamp > $oneMinuteAgo
        );

        // If at limit, wait
        if (count(self::$requestTimestamps) >= $minuteLimit) {
            $oldestRequest = min(self::$requestTimestamps);
            $waitTime = 60 - ($now - $oldestRequest) + 1; // Add 1 second buffer
            if ($waitTime > 0) {
                Log::info("ATTOM API per-minute rate limit reached, waiting {$waitTime}s");
                sleep($waitTime);
                // Re-filter after wait
                self::$requestTimestamps = array_filter(
                    self::$requestTimestamps,
                    fn($timestamp) => $timestamp > (time() - 60)
                );
            }
        }
    }

    /**
     * Record a request for rate limiting
     */
    protected function recordRequest(): void
    {
        self::$requestTimestamps[] = time();
        self::$hourlyRequestCount++;
    }

    // ============================================================
    // PUBLIC API METHODS - For fetching without storing
    // ============================================================

    /**
     * Fetch raw property data without storing (for preview/testing)
     */
    public function fetchPropertyData(
        string $address, 
        ?string $city = null, 
        ?string $state = null, 
        ?string $zip = null,
        bool $forceFresh = false
    ): ?array {
        $cacheKey = $this->cacheConfig['enabled']
            ? "attom:fetch:expanded:" . md5("{$address}:{$city}:{$state}:{$zip}")
            : null;
        
        if ($forceFresh && $cacheKey) {
            Cache::forget($cacheKey);
        }
        
        $params = $this->buildAddressParams($address, $city, $state, $zip);

        return $this->makeRequest(
            '/propertyapi/v1.0.0/property/expandedprofile',
            'GET',
            $params,
            $cacheKey,
            $forceFresh,
            null // No property to associate
        );
    }

    /**
     * Fetch property data using single full address string
     */
    public function fetchPropertyDataByFullAddress(
        string $fullAddress,
        bool $forceFresh = false
    ): ?array {
        $cacheKey = $this->cacheConfig['enabled']
            ? "attom:fetch:expanded:full:" . md5($fullAddress)
            : null;
        
        if ($forceFresh && $cacheKey) {
            Cache::forget($cacheKey);
        }
        
        $params = ['address' => trim($fullAddress)];

        return $this->makeRequest(
            '/propertyapi/v1.0.0/property/expandedprofile',
            'GET',
            $params,
            $cacheKey,
            $forceFresh,
            null
        );
    }

    /**
     * Fetch and extract property data in one call (without saving to DB)
     */
    public function fetchAndExtractPropertyData(
        string $address, 
        ?string $city = null, 
        ?string $state = null, 
        ?string $zip = null,
        bool $forceFresh = false
    ): ?array {
        $rawData = $this->fetchPropertyData($address, $city, $state, $zip, $forceFresh);
        
        if (!$rawData) {
            return null;
        }
        
        return $this->extractPropertyData($rawData);
    }

    /**
     * Fetch multiple endpoints for a property (without saving)
     */
    public function fetchAllPropertyData(
        string $address,
        ?string $city = null,
        ?string $state = null,
        ?string $zip = null,
        array $endpoints = ['detail', 'sale_history', 'events'],
        bool $forceFresh = false
    ): array {
        $results = [
            'detail' => null,
            'sale_history' => null,
            'comparable_sales' => null,
            'events' => null,
            'snapshot' => null,
        ];

        foreach ($endpoints as $endpoint) {
            try {
                $results[$endpoint] = match($endpoint) {
                    'detail' => $this->fetchPropertyData($address, $city, $state, $zip, $forceFresh),
                    'sale_history' => $this->getSaleHistory($address, $city, $state, $zip, $forceFresh, null),
                    'comparable_sales' => $this->getComparableSales($address, $city, $state, $zip, [], $forceFresh, null),
                    'events' => $this->getPropertyEvents($address, $city, $state, $zip, $forceFresh, null),
                    'snapshot' => $this->getPropertySnapshot($address, $city, $state, $forceFresh, null),
                    default => null,
                };
            } catch (\Exception $e) {
                Log::warning("Failed to fetch {$endpoint} for address", [
                    'address' => $address,
                    'city' => $city,
                    'state' => $state,
                    'endpoint' => $endpoint,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    // ============================================================
    // PUBLIC API METHODS - For enriching properties
    // ============================================================

    /**
     * Get property details from ATTOM API (enhanced, backward compatible)
     */
    public function getPropertyDetails(
        string $address, 
        ?string $city = null, 
        ?string $state = null, 
        ?string $zip = null, 
        bool $forceFresh = false, 
        ?Property $property = null
    ): ?array {
        $cacheKey = $this->cacheConfig['enabled']
            ? "attom:property:expanded:" . md5("{$address}:{$city}:{$state}:{$zip}")
            : null;

        if ($forceFresh && $cacheKey) {
            Cache::forget($cacheKey);
        }

        $params = $this->buildAddressParams($address, $city, $state, $zip);

        $data = $this->makeRequest(
            '/propertyapi/v1.0.0/property/expandedprofile',
            'GET',
            $params,
            $cacheKey,
            $forceFresh,
            $property
        );

        if (!$data) {
            return null;
        }

        return $this->extractPropertyData($data);
    }

    /**
     * Get property detail (alias for backward compatibility)
     */
    public function getPropertyDetail(
        string $address, 
        ?string $city = null, 
        ?string $state = null, 
        ?string $zip = null, 
        bool $forceFresh = false, 
        ?Property $property = null
    ): ?array {
        return $this->getPropertyDetails($address, $city, $state, $zip, $forceFresh, $property);
    }

    /**
     * Get sale history for a property
     */
    public function getSaleHistory(
        string $address, 
        ?string $city = null, 
        ?string $state = null, 
        ?string $zip = null, 
        bool $forceFresh = false, 
        ?Property $property = null
    ): ?array {
        $cacheKey = $this->cacheConfig['enabled']
            ? "attom:sale:history:" . md5("{$address}:{$city}:{$state}:{$zip}")
            : null;

        if ($forceFresh && $cacheKey) {
            Cache::forget($cacheKey);
        }

        $params = $this->buildAddressParams($address, $city, $state, $zip);

        return $this->makeRequest(
            '/propertyapi/v1.0.0/saleshistory/detail',
            'GET',
            $params,
            $cacheKey,
            $forceFresh,
            $property
        );
    }

    /**
     * Get comparable sales for a property
     */
    public function getComparableSales(
        string $address, 
        ?string $city = null, 
        ?string $state = null, 
        ?string $zip = null, 
        array $options = [], 
        bool $forceFresh = false, 
        ?Property $property = null
    ): ?array {
        $cacheKey = $this->cacheConfig['enabled']
            ? "attom:comps:" . md5("{$address}:{$city}:{$state}:{$zip}:" . json_encode($options))
            : null;

        if ($forceFresh && $cacheKey) {
            Cache::forget($cacheKey);
        }

        $params = $this->buildAddressParams($address, $city, $state, $zip);

        // Add optional parameters
        if (isset($options['radius'])) {
            $params['radius'] = $options['radius'];
        }
        if (isset($options['pageSize'])) {
            $params['pageSize'] = $options['pageSize'];
        }

        return $this->makeRequest(
            '/propertyapi/v1.0.0/sale/snapshot',
            'GET',
            $params,
            $cacheKey,
            $forceFresh,
            $property
        );
    }

    /**
     * Get property events (permits, liens, ownership changes)
     */
    public function getPropertyEvents(
        string $address, 
        ?string $city = null, 
        ?string $state = null, 
        ?string $zip = null, 
        bool $forceFresh = false, 
        ?Property $property = null
    ): ?array {
        $cacheKey = $this->cacheConfig['enabled']
            ? "attom:events:" . md5("{$address}:{$city}:{$state}:{$zip}")
            : null;

        if ($forceFresh && $cacheKey) {
            Cache::forget($cacheKey);
        }

        $params = $this->buildAddressParams($address, $city, $state, $zip);

        return $this->makeRequest(
            '/propertyapi/v1.0.0/allevents/detail',
            'GET',
            $params,
            $cacheKey,
            $forceFresh,
            $property
        );
    }

    /**
     * Get building permits for a property from ATTOM's dedicated buildingpermits endpoint.
     * Returns richer permit data than the allevents endpoint.
     */
    public function getBuildingPermits(
        string $address,
        ?string $city = null,
        ?string $state = null,
        ?string $zip = null,
        bool $forceFresh = false,
        ?Property $property = null
    ): ?array {
        $cacheKey = $this->cacheConfig['enabled']
            ? "attom:buildingpermits:" . md5("{$address}:{$city}:{$state}:{$zip}")
            : null;

        if ($forceFresh && $cacheKey) {
            Cache::forget($cacheKey);
        }

        $params = $this->buildAddressParams($address, $city, $state, $zip);

        return $this->makeRequest(
            '/propertyapi/v1.0.0/property/buildingpermits',
            'GET',
            $params,
            $cacheKey,
            $forceFresh,
            $property
        );
    }

    /**
     * Get property snapshot
     */
    public function getPropertySnapshot(
        string $address,
        ?string $city = null,
        ?string $state = null,
        bool $forceFresh = false, 
        ?Property $property = null
    ): ?array {
        $cacheKey = $this->cacheConfig['enabled']
            ? "attom:property:snapshot:" . md5("{$address}:{$city}:{$state}")
            : null;

        if ($forceFresh && $cacheKey) {
            Cache::forget($cacheKey);
        }

        $params = $this->buildAddressParams($address, $city, $state, null);

        return $this->makeRequest(
            '/propertyapi/v1.0.0/property/snapshot',
            'GET',
            $params,
            $cacheKey,
            $forceFresh,
            $property
        );
    }

    /**
     * Get sale snapshot (backward compatibility alias)
     */
    public function getSaleSnapshot(
        string $address, 
        ?string $city = null, 
        ?string $state = null
    ): ?array {
        return $this->getPropertySnapshot($address, $city, $state);
    }

    /**
     * Search for properties by criteria
     */
    public function searchProperties(array $criteria, bool $forceFresh = false): ?array
    {
        $cacheKey = $this->cacheConfig['enabled']
            ? "attom:search:" . md5(json_encode($criteria))
            : null;
    
        if ($forceFresh && $cacheKey) {
            Cache::forget($cacheKey);
        }
    
        // Build search parameters
        $params = [];
        
        // Address-based search
        if (isset($criteria['address'])) {
            $params['address1'] = $criteria['address'];
        }
        if (isset($criteria['city'])) {
            $params['address2'] = ($params['address2'] ?? '') . $criteria['city'];
        }
        if (isset($criteria['state'])) {
            $params['address2'] = ($params['address2'] ?? '') . ', ' . $criteria['state'];
        }
        if (isset($criteria['zip'])) {
            $params['postalcode'] = $criteria['zip'];
        }
    
        // Property criteria
        if (isset($criteria['minBeds'])) {
            $params['minBeds'] = $criteria['minBeds'];
        }
        if (isset($criteria['maxBeds'])) {
            $params['maxBeds'] = $criteria['maxBeds'];
        }
        if (isset($criteria['minBath'])) {
            $params['minBathsTotal'] = $criteria['minBath'];
        }
        if (isset($criteria['maxBath'])) {
            $params['maxBathsTotal'] = $criteria['maxBath'];
        }
        if (isset($criteria['minSquareFeet'])) {
            $params['minUniversalSize'] = $criteria['minSquareFeet'];
        }
        if (isset($criteria['maxSquareFeet'])) {
            $params['maxUniversalSize'] = $criteria['maxSquareFeet'];
        }
        if (isset($criteria['minYearBuilt'])) {
            $params['minYearBuilt'] = $criteria['minYearBuilt'];
        }
        if (isset($criteria['maxYearBuilt'])) {
            $params['maxYearBuilt'] = $criteria['maxYearBuilt'];
        }
        if (isset($criteria['propertyType'])) {
            $params['propertyType'] = $criteria['propertyType'];
        }
    
        // Price range
        if (isset($criteria['minPrice'])) {
            $params['minAssdTtlValue'] = $criteria['minPrice'];
        }
        if (isset($criteria['maxPrice'])) {
            $params['maxAssdTtlValue'] = $criteria['maxPrice'];
        }
    
        // Pagination
        if (isset($criteria['page'])) {
            $params['page'] = $criteria['page'];
        }
        if (isset($criteria['pageSize'])) {
            $params['pageSize'] = $criteria['pageSize'];
        }
    
        return $this->makeRequest(
            '/propertyapi/v1.0.0/property/detail',
            'GET',
            $params,
            $cacheKey,
            $forceFresh
        );
    }
    
    // ============================================================
    // HELPER METHODS
    // ============================================================
    
    /**
     * Build address parameters for API request
     */
    protected function buildAddressParams(
        string $address,
        ?string $city = null,
        ?string $state = null,
        ?string $zip = null
    ): array {
        $params = ['address1' => trim($address)];

        if ($city && $state) {
            $params['address2'] = trim($city) . ', ' . trim($state);
            if ($zip) {
                $params['address2'] .= ' ' . trim($zip);
            }
        } elseif ($city) {
            $params['address2'] = trim($city);
        } elseif ($state) {
            $params['address2'] = trim($state);
        }

        return $params;
    }
    
/**
 * Extract relevant property data from ATTOM response
 */
protected function extractPropertyData(array $data): array
{
    $property = $data['property'] ?? [];
    if (empty($property)) {
        return ['raw_data' => $data];
    }
    
    // ATTOM returns array of properties, get first one
    $propertyData = is_array($property) && isset($property[0]) ? $property[0] : $property;
    
    $assessment = $propertyData['assessment'] ?? [];
    $location = $propertyData['location'] ?? [];
    $summary = $propertyData['summary'] ?? [];
    $building = $propertyData['building'] ?? [];
    $buildingSize = $building['size'] ?? [];
    $buildingRooms = $building['rooms'] ?? [];
    $buildingInterior = $building['interior'] ?? [];
    $buildingConstruction = $building['construction'] ?? [];
    $lot = $propertyData['lot'] ?? [];
    $area = $propertyData['area'] ?? [];
    $utilities = $propertyData['utilities'] ?? [];
    $address = $propertyData['address'] ?? [];
    $identifier = $propertyData['identifier'] ?? [];
    $sale = $propertyData['sale'] ?? [];
    $saleAmount = $sale['amount'] ?? [];

    // Extract assessment data (handle nested structure)
    $assessedData = $assessment['assessed'] ?? $assessment;
    $marketData = $assessment['market'] ?? $assessment;
    $taxData = $assessment['tax'] ?? $assessment;

    return [
        // Size information (camelCase fallbacks for expandedprofile)
        'square_feet' => $buildingSize['bldgsize'] ?? $buildingSize['bldgSize'] ?? $buildingSize['livingsize'] ?? $buildingSize['livingSize'] ?? $buildingSize['universalsize'] ?? $buildingSize['universalSize'] ?? null,
        'gross_size' => $buildingSize['grosssize'] ?? $buildingSize['grossSize'] ?? null,
        'living_size' => $buildingSize['livingsize'] ?? $buildingSize['livingSize'] ?? null,
        'basement_size' => $buildingInterior['bsmtsize'] ?? $buildingInterior['bsmtSize'] ?? null,

        // Lot information
        'lot_size' => $lot['lotsize2'] ?? $lot['lotSize2'] ?? null,
        'lot_size_acres' => $lot['lotsize1'] ?? $lot['lotSize1'] ?? null,
        'lot_depth' => $lot['depth'] ?? null,
        'lot_frontage' => $lot['frontage'] ?? null,
        'lot_number' => $lot['lotnum'] ?? $lot['lotNum'] ?? null,

        // Basic property info
        'year_built' => $summary['yearbuilt'] ?? $summary['yearBuilt'] ?? null,
        'bedrooms' => $buildingRooms['beds'] ?? null,

        // Bathroom information (camelCase fallbacks)
        'bathrooms' => $buildingRooms['bathstotal'] ?? $buildingRooms['bathsTotal'] ?? null,
        'bathrooms_full' => $buildingRooms['bathsfull'] ?? $buildingRooms['bathsFull'] ?? null,
        'bathrooms_partial' => $buildingRooms['bathspartial'] ?? $buildingRooms['bathsPartial'] ?? null,
        'bathrooms_total_decimal' => $this->calculateBathroomDecimal($buildingRooms),

        // Property classification
        'property_type' => $summary['propertyType'] ?? $summary['proptype'] ?? $summary['propclass'] ?? $summary['propClass'] ?? null,
        'property_subtype' => $summary['propsubtype'] ?? $summary['propSubType'] ?? null,
        'property_class' => $summary['propclass'] ?? $summary['propClass'] ?? null,
        'property_indicator' => $summary['propIndicator'] ?? null,
        'property_land_use' => $summary['propLandUse'] ?? null,

        // Valuation data
        'assessed_value' => $assessedData['assdttlvalue'] ?? $assessedData['assdTtlValue'] ?? null,
        'assessed_land_value' => $assessedData['assdlandvalue'] ?? $assessedData['assdLandValue'] ?? null,
        'assessed_improvement_value' => $assessedData['assdimprvalue'] ?? $assessedData['assdImprValue'] ?? null,
        'market_value' => $marketData['mktttlvalue'] ?? $marketData['mktTtlValue'] ?? null,
        'market_land_value' => $marketData['mktlandvalue'] ?? $marketData['mktLandValue'] ?? null,
        'market_improvement_value' => $marketData['mktimprvalue'] ?? $marketData['mktImprValue'] ?? null,

        // Tax information
        'tax_amount' => $taxData['taxamt'] ?? $taxData['taxAmt'] ?? null,
        'tax_year' => $taxData['taxyear'] ?? $taxData['taxYear'] ?? null,
        'tax_code_area' => $area['taxcodearea'] ?? $area['taxCodeArea'] ?? null,

        // Building details
        'rooms_total' => $buildingRooms['roomsTotal'] ?? null,
        'stories' => $building['summary']['levels'] ?? null,
        'building_type' => $building['summary']['bldgType'] ?? null,
        'architectural_style' => $building['summary']['archStyle'] ?? null,
        'construction_type' => $buildingConstruction['constructiontype'] ?? $buildingConstruction['constructionType'] ?? null,
        'construction_condition' => $buildingConstruction['condition'] ?? null,
        'construction_quality' => $building['summary']['quality'] ?? null,
        'frame_type' => $buildingConstruction['frameType'] ?? null,
        'wall_type' => $buildingConstruction['wallType'] ?? null,
        'basement_type' => $buildingInterior['bsmttype'] ?? $buildingInterior['bsmtType'] ?? null,

        // Utilities (camelCase fallbacks)
        'heating_type' => $utilities['heatingtype'] ?? $utilities['heatingType'] ?? null,
        'heating_fuel' => $utilities['heatingfuel'] ?? $utilities['heatingFuel'] ?? null,
        'cooling_type' => $utilities['coolingtype'] ?? $utilities['coolingType'] ?? null,
        'pool_type' => $lot['pooltype'] ?? $lot['poolType'] ?? null,

        // Area information
        'zoning_type' => $lot['zoningType'] ?? $area['zoningType'] ?? null,
        'legal1' => $area['legal1'] ?? $summary['legal1'] ?? null,
        'subdivision' => $area['subdname'] ?? $area['subdName'] ?? null,
        'municipality' => $area['munname'] ?? $area['munName'] ?? null,
        'county' => $area['countrysecsubd'] ?? $area['countrySecSubd'] ?? null,
        'school_district' => $area['schooldist'] ?? $area['schoolDist'] ?? null,

        // Owner information
        'owner_occupied' => ($summary['absenteeInd'] ?? null) === 'OWNER OCCUPIED',
        'absentee_indicator' => $summary['absenteeInd'] ?? null,

        // Sale information (from expandedprofile)
        'last_sale_date' => $saleAmount['saleRecDate'] ?? $saleAmount['salerecdate'] ?? null,

        // Address and location
        'full_address' => $address['oneLine'] ?? null,
        'street_address' => $address['line1'] ?? null,
        'city_state_zip' => $address['line2'] ?? null,
        'city' => $address['locality'] ?? null,
        'state' => $address['countrySubd'] ?? null,
        'country' => $address['country'] ?? $address['countryCode'] ?? 'US',
        'zip_code' => $address['postal1'] ?? null,
        'zip_plus_4' => $address['postal2'] ?? null,
        'carrier_route' => $address['postal3'] ?? null,
        'match_code' => $address['matchCode'] ?? null,

        'latitude' => isset($location['latitude']) ? (float)$location['latitude'] : null,
        'longitude' => isset($location['longitude']) ? (float)$location['longitude'] : null,
        'location_accuracy' => $location['accuracy'] ?? null,

        // Identifiers
        'attom_id' => $identifier['attomId'] ?? $identifier['Id'] ?? null,
        'apn' => $identifier['apn'] ?? null,
        'fips' => $identifier['fips'] ?? null,

        // Metadata
        'last_modified' => $propertyData['vintage']['lastModified'] ?? null,
        'published_date' => $propertyData['vintage']['pubDate'] ?? null,

        // Keep raw data for reference
        'raw_data' => $data,
    ];
}

    /**
     * Calculate bathroom decimal representation (e.g., 2.5 for 2 full + 1 half)
     */
    protected function calculateBathroomDecimal(array $buildingRooms): ?float
    {
        $full = $buildingRooms['bathsfull'] ?? $buildingRooms['bathsFull'] ?? 0;
        $partial = $buildingRooms['bathspartial'] ?? $buildingRooms['bathsPartial'] ?? 0;
        
        if ($full === 0 && $partial === 0) {
            return null;
        }
        
        // Each partial bath counts as 0.5
        return $full + ($partial * 0.5);
    }
    
    /**
     * Log API call to database
     */
    protected function logApiCall(
        string $service,
        string $endpoint,
        string $method,
        array $requestBody,
        ?string $responseBody,
        ?int $statusCode,
        ?int $responseTime,
        bool $success,
        ?string $errorMessage = null,
        ?Property $property = null
    ): void {
        try {
            ApiLog::create([
                'service' => $service,
                'endpoint' => $endpoint,
                'method' => $method,
                'status_code' => $statusCode,
                'request_body' => json_encode($requestBody),
                'response_body' => $responseBody ? (strlen($responseBody) > 65000 ? substr($responseBody, 0, 65000) : $responseBody) : null,
                'response_time_ms' => $responseTime,
                'success' => $success,
                'error_message' => $errorMessage ? (strlen($errorMessage) > 500 ? substr($errorMessage, 0, 500) : $errorMessage) : null,
                'property_id' => $property?->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log API call', [
                'error' => $e->getMessage(),
                'service' => $service,
                'endpoint' => $endpoint,
            ]);
        }
    }
    
    /**
     * Clear all ATTOM caches
     */
    public function clearCache(): void
    {
        $patterns = [
            'attom:property:*',
            'attom:sale:*',
            'attom:fetch:*',
            'attom:comps:*',
            'attom:events:*',
            'attom:search:*',
        ];
    
        foreach ($patterns as $pattern) {
            try {
                Cache::forget($pattern);
            } catch (\Exception $e) {
                Log::warning('Failed to clear cache pattern', [
                    'pattern' => $pattern,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    
        Log::info('ATTOM API cache cleared');
    }
    
    /**
     * Get current rate limit status
     */
    public function getRateLimitStatus(): array
    {
        $now = time();
        $oneMinuteAgo = $now - 60;
        
        $recentRequests = array_filter(
            self::$requestTimestamps,
            fn($timestamp) => $timestamp > $oneMinuteAgo
        );
    
        return [
            'requests_last_minute' => count($recentRequests),
            'minute_limit' => $this->rateLimitConfig['requests_per_minute'],
            'requests_this_hour' => self::$hourlyRequestCount,
            'hour_limit' => $this->rateLimitConfig['requests_per_hour'],
            'hour_resets_at' => self::$hourlyResetTime ? date('Y-m-d H:i:s', self::$hourlyResetTime) : null,
        ];
    }
}