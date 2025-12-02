<?php

namespace App\Services;

use App\Models\ApiLog;
use App\Models\Property;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
    protected static $hourlyResetTime = null;

    public function __construct()
    {
        $config = config('services.attom', []);
        $this->apiKey = $config['api_key'] ?? env('ATTOM_API_KEY');
        $this->baseUrl = $config['api_url'] ?? env('ATTOM_API_URL', 'https://api.gateway.attomdata.com');
        $this->timeout = $config['timeout'] ?? 30;
        $this->rateLimitConfig = $config['rate_limit'] ?? ['requests_per_minute' => 60, 'requests_per_hour' => 1000];
        $this->retryConfig = $config['retry'] ?? ['max_attempts' => 3, 'backoff_multiplier' => 2];
        $this->cacheConfig = $config['cache'] ?? ['ttl_days' => 7, 'enabled' => true];
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
        // Check rate limits
        $this->checkRateLimit();

        // Check cache if enabled and not forcing fresh
        if ($cacheKey && $this->cacheConfig['enabled'] && !$forceFresh) {
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
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
                $isError = (!$hasResult && !$noResult && $statusCode !== 0);

                // Log API call
                $this->logApiCall(
                    'attom',
                    $endpoint,
                    $method,
                    $params,
                    $response->body(),
                    $httpStatusCode,
                    $responseTime,
                    !$isError && !$noResult,
                    $isError ? $statusMessage : ($noResult ? 'No result found' : null),
                    $property
                );

                // Handle permanent errors (don't retry)
                if ($this->isPermanentError($httpStatusCode, $statusCode, $statusMessage)) {
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
                    return null;
                }

                // Success or no result
                if ($noResult) {
                    return null;
                }

                $result = $data;

                // Cache result if enabled
                if ($cacheKey && $this->cacheConfig['enabled']) {
                    $ttl = now()->addDays($this->cacheConfig['ttl_days'] ?? 7);
                    Cache::put($cacheKey, $result, $ttl);
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

        // Invalid parameters
        if ($httpStatusCode === 400 && $statusCode !== null && $statusCode !== 0) {
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
        $retryableMessages = ['timeout', 'connection', 'network', 'timed out'];
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
            $waitTime = 60 - ($now - $oldestRequest);
            if ($waitTime > 0) {
                Log::info("ATTOM API rate limit reached, waiting {$waitTime}s");
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

    /**
     * Get property details from ATTOM API (enhanced, backward compatible)
     */
    public function getPropertyDetails(string $address, ?string $city = null, ?string $state = null, ?string $zip = null, bool $forceFresh = false, ?Property $property = null): ?array
    {
        $cacheKey = $this->cacheConfig['enabled'] 
            ? "attom:property:detail:" . md5("{$address}:{$city}:{$state}:{$zip}")
            : null;
        
        if ($forceFresh && $cacheKey) {
            Cache::forget($cacheKey);
        }
        
        $params = ['address1' => trim($address)];
        if ($city && $state) {
            $params['address2'] = trim($city) . ', ' . trim($state);
        }

        $data = $this->makeRequest(
            '/propertyapi/v1.0.0/property/detail',
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
    public function getPropertyDetail(string $address, ?string $city = null, ?string $state = null, ?string $zip = null, bool $forceFresh = false, ?Property $property = null): ?array
    {
        return $this->getPropertyDetails($address, $city, $state, $zip, $forceFresh, $property);
    }

    /**
     * Get sale history for a property
     */
    public function getSaleHistory(string $address, ?string $city = null, ?string $state = null, ?string $zip = null, bool $forceFresh = false, ?Property $property = null): ?array
    {
        $cacheKey = $this->cacheConfig['enabled']
            ? "attom:sale:history:" . md5("{$address}:{$city}:{$state}:{$zip}")
            : null;

        $params = ['address1' => trim($address)];
        if ($city && $state) {
            $params['address2'] = trim($city) . ', ' . trim($state);
        }

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
    public function getComparableSales(string $address, ?string $city = null, ?string $state = null, ?string $zip = null, array $options = [], bool $forceFresh = false, ?Property $property = null): ?array
    {
        $cacheKey = $this->cacheConfig['enabled']
            ? "attom:comps:" . md5("{$address}:{$city}:{$state}:{$zip}:" . json_encode($options))
            : null;

        $params = ['address1' => trim($address)];
        if ($city && $state) {
            $params['address2'] = trim($city) . ', ' . trim($state);
        }

        // Add optional parameters
        if (isset($options['radius'])) {
            $params['radius'] = $options['radius'];
        }
        if (isset($options['maxResults'])) {
            $params['maxResults'] = $options['maxResults'];
        }

        return $this->makeRequest(
            '/propertyapi/v1.0.0/saleshistory/snapshot',
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
    public function getPropertyEvents(string $address, ?string $city = null, ?string $state = null, ?string $zip = null, bool $forceFresh = false, ?Property $property = null): ?array
    {
        $cacheKey = $this->cacheConfig['enabled']
            ? "attom:events:" . md5("{$address}:{$city}:{$state}:{$zip}")
            : null;

        $params = ['address1' => trim($address)];
        if ($city && $state) {
            $params['address2'] = trim($city) . ', ' . trim($state);
        }

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
     * Search for properties by criteria
     */
    public function searchProperties(array $criteria, bool $forceFresh = false): ?array
    {
        $cacheKey = $this->cacheConfig['enabled']
            ? "attom:search:" . md5(json_encode($criteria))
            : null;

        // Build search parameters
        $params = [];
        
        // Address-based search
        if (isset($criteria['address'])) {
            $params['address1'] = $criteria['address'];
        }
        if (isset($criteria['city'])) {
            $params['city'] = $criteria['city'];
        }
        if (isset($criteria['state'])) {
            $params['state'] = $criteria['state'];
        }
        if (isset($criteria['zip'])) {
            $params['postalcode'] = $criteria['zip'];
        }

        // Property criteria
        if (isset($criteria['minBeds'])) {
            $params['minbeds'] = $criteria['minBeds'];
        }
        if (isset($criteria['maxBeds'])) {
            $params['maxbeds'] = $criteria['maxBeds'];
        }
        if (isset($criteria['minBath'])) {
            $params['minbath'] = $criteria['minBath'];
        }
        if (isset($criteria['maxBath'])) {
            $params['maxbath'] = $criteria['maxBath'];
        }
        if (isset($criteria['minSquareFeet'])) {
            $params['minuniversalsize'] = $criteria['minSquareFeet'];
        }
        if (isset($criteria['maxSquareFeet'])) {
            $params['maxuniversalsize'] = $criteria['maxSquareFeet'];
        }
        if (isset($criteria['minYearBuilt'])) {
            $params['minyearbuilt'] = $criteria['minYearBuilt'];
        }
        if (isset($criteria['maxYearBuilt'])) {
            $params['maxyearbuilt'] = $criteria['maxYearBuilt'];
        }
        if (isset($criteria['propertyType'])) {
            $params['propertytype'] = $criteria['propertyType'];
        }

        // Price range
        if (isset($criteria['minPrice'])) {
            $params['minassdttlvalue'] = $criteria['minPrice'];
        }
        if (isset($criteria['maxPrice'])) {
            $params['maxassdttlvalue'] = $criteria['maxPrice'];
        }

        // Pagination
        if (isset($criteria['page'])) {
            $params['page'] = $criteria['page'];
        }
        if (isset($criteria['pageSize'])) {
            $params['pagesize'] = $criteria['pageSize'];
        }

        return $this->makeRequest(
            '/propertyapi/v1.0.0/property/basicprofile',
            'GET',
            $params,
            $cacheKey,
            $forceFresh
        );
    }

    /**
     * Get property sale snapshot (enhanced version)
     */
    public function getPropertySnapshot(string $address, ?string $city = null, ?string $state = null, bool $forceFresh = false, ?Property $property = null): ?array
    {
        $cacheKey = $this->cacheConfig['enabled']
            ? "attom:sale:snapshot:" . md5("{$address}:{$city}:{$state}")
            : null;

        $params = ['address1' => $address];
        if ($city) {
            $params['city'] = $city;
        }
        if ($state) {
            $params['state'] = $state;
        }

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
    public function getSaleSnapshot(string $address, ?string $city = null, ?string $state = null): ?array
    {
        return $this->getPropertySnapshot($address, $city, $state);
    }

    /**
     * Extract relevant property data from ATTOM response
     */
    protected function extractPropertyData(array $data): array
    {
        $property = $data['property'] ?? [];
        if (empty($property)) {
            return [];
        }
        
        $propertyData = $property[0] ?? [];
        $assessment = $propertyData['assessment'] ?? [];
        $location = $propertyData['location'] ?? [];
        $summary = $propertyData['summary'] ?? [];
        $building = $propertyData['building'] ?? [];
        $buildingSize = $building['size'] ?? [];
        $buildingRooms = $building['rooms'] ?? [];

        return [
            'square_feet' => $buildingSize['bldgsize'] ?? $buildingSize['livingsize'] ?? $buildingSize['universalsize'] ?? null,
            'lot_size' => ($propertyData['lot']['lotsize2'] ?? null) ? (int)($propertyData['lot']['lotsize2']) : null,
            'year_built' => $summary['yearbuilt'] ?? null,
            'bedrooms' => $buildingRooms['beds'] ?? null,
            'bathrooms' => $buildingRooms['bathstotal'] ?? $buildingRooms['bathsfull'] ?? null,
            'property_type' => $summary['propclass'] ?? $summary['propertyType'] ?? null,
            'assessed_value' => $assessment['assessedvalue'] ?? null,
            'market_value' => $assessment['marketvalue'] ?? null,
            'tax_amount' => $assessment['taxamount'] ?? null,
            'full_address' => $propertyData['address']['oneLine'] ?? null,
            'latitude' => $location['latitude'] ? (float)$location['latitude'] : null,
            'longitude' => $location['longitude'] ? (float)$location['longitude'] : null,
            'attom_id' => $propertyData['identifier']['attomId'] ?? null,
            'raw_data' => $data,
        ];
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
                'response_body' => $responseBody,
                'response_time_ms' => $responseTime,
                'success' => $success,
                'error_message' => $errorMessage,
                'property_id' => $property?->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log API call', ['error' => $e->getMessage()]);
        }
    }
}
