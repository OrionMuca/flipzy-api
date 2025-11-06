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
    protected int $timeout = 30;

    public function __construct()
    {
        $this->apiKey = config('services.attom.api_key', env('ATTOM_API_KEY'));
        $this->baseUrl = config('services.attom.api_url', env('ATTOM_API_URL', 'https://api.gateway.attomdata.com'));
    }

    /**
     * Get property details from ATTOM API
     * 
     * ATTOM API accepts:
     * - address1: Street address (e.g., "4529 WINONA CT")
     * - address2: City, State, ZIP combined (e.g., "DENVER, CO 80212")
     * OR
     * - attomid: Direct ATTOM ID lookup
     */
    public function getPropertyDetails(string $address, ?string $city = null, ?string $state = null, ?string $zip = null, bool $forceFresh = false): ?array
    {
        $cacheKey = "attom:property:{$address}:{$city}:{$state}:{$zip}";
        
        // If forcing fresh data, clear cache first
        if ($forceFresh) {
            Cache::forget($cacheKey);
        }
        
        return Cache::remember($cacheKey, now()->addDays(7), function () use ($address, $city, $state, $zip) {
            try {
                $startTime = microtime(true);
                
                // Build query parameters - ATTOM uses address1 and address2 format
                $params = [
                    'address1' => trim($address),
                ];
                
                // Build address2: "City, State ZIP" format
                // ATTOM expects: "DENVER, CO 80212" or "DENVER, CO" if no zip
                if ($city && $state) {
                    $address2Parts = [trim($city), trim($state)];
                    if ($zip) {
                        $address2Parts[] = trim($zip);
                    }
                    $params['address2'] = implode(', ', $address2Parts);
                }

                $response = Http::timeout($this->timeout)
                    ->withHeaders([
                        'apikey' => $this->apiKey,
                        'Accept' => 'application/json',
                    ])
                    ->get("{$this->baseUrl}/propertyapi/v1.0.0/property/detail", $params);

                $responseTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds

                $httpStatusCode = $response->status();
                $data = $response->json();
                
                // ATTOM API returns status in the response body, not just HTTP status
                // Check the status.code in the response body
                $statusCode = $data['status']['code'] ?? null;
                $statusMessage = $data['status']['msg'] ?? 'Unknown';
                $total = $data['status']['total'] ?? 0;
                
                // ATTOM status codes:
                // 0 = SuccessWithResult (property found)
                // 400 = SuccessWithoutResult (no property found, but API call was successful)
                // Other codes = actual errors
                
                $hasResult = ($statusCode === 0 && $total > 0);
                $noResult = ($statusCode === 400 && $statusMessage === 'SuccessWithoutResult');
                $isError = (!$hasResult && !$noResult && $statusCode !== 0);

                // Log API call (property will be passed when called from enrichment service)
                $this->logApiCall(
                    'attom',
                    '/propertyapi/v1.0.0/property/detail',
                    'GET',
                    $params,
                    $response->body(),
                    $httpStatusCode,
                    (int) $responseTime,
                    !$isError, // Success if not an error (even if no result)
                    $isError ? $statusMessage : null,
                    null // Property not available in standalone call
                );

                if ($isError) {
                    Log::warning('ATTOM API error', [
                        'http_status' => $httpStatusCode,
                        'status_code' => $statusCode,
                        'status_message' => $statusMessage,
                        'request_params' => $params,
                        'response' => $response->body(),
                    ]);
                    return null;
                }

                if ($noResult) {
                    Log::info('ATTOM API: No property found', [
                        'address1' => $params['address1'] ?? null,
                        'address2' => $params['address2'] ?? null,
                        'status_message' => $statusMessage,
                    ]);
                    return null;
                }

                // Extract relevant property data (only if we have results)
                return $this->extractPropertyData($data);

            } catch (\Exception $e) {
                Log::error('ATTOM API exception', [
                    'message' => $e->getMessage(),
                    'address' => $address,
                ]);

                $this->logApiCall(
                    'attom',
                    '/propertyapi/v1.0.0/property/detail',
                    'GET',
                    $params ?? [],
                    null,
                    null,
                    null,
                    false,
                    $e->getMessage()
                );

                return null;
            }
        });
    }

    /**
     * Get property sale snapshot
     */
    public function getSaleSnapshot(string $address, ?string $city = null, ?string $state = null): ?array
    {
        $cacheKey = "attom:sale:{$address}:{$city}:{$state}";
        
        return Cache::remember($cacheKey, now()->addDays(7), function () use ($address, $city, $state) {
            try {
                $startTime = microtime(true);
                
                $params = ['address1' => $address];
                if ($city) $params['city'] = $city;
                if ($state) $params['state'] = $state;

                $response = Http::timeout($this->timeout)
                    ->withHeaders([
                        'apikey' => $this->apiKey,
                        'Accept' => 'application/json',
                    ])
                    ->get("{$this->baseUrl}/propertyapi/v1.0.0/property/snapshot", $params);

                $responseTime = (microtime(true) - $startTime) * 1000;

                $success = $response->successful();
                $statusCode = $response->status();

                $this->logApiCall(
                    'attom',
                    '/propertyapi/v1.0.0/property/snapshot',
                    'GET',
                    $params,
                    $response->body(),
                    $statusCode,
                    (int) $responseTime,
                    $success,
                    $success ? null : $response->body()
                );

                return $success ? $response->json() : null;

            } catch (\Exception $e) {
                Log::error('ATTOM Sale Snapshot API exception', ['message' => $e->getMessage()]);
                return null;
            }
        });
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
            'lot_size' => ($propertyData['lot']['lotsize2'] ?? null) ? (int)($propertyData['lot']['lotsize2']) : null, // Convert to square feet
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
            'raw_data' => $data, // Store full response
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

