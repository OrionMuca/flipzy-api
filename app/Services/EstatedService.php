<?php

namespace App\Services;

use App\Models\ApiLog;
use App\Models\Property;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class EstatedService
{
    protected string $apiKey;
    protected string $baseUrl;
    protected int $timeout = 30;

    public function __construct()
    {
        $this->apiKey = config('services.estated.api_key', env('ESTATED_API_KEY'));
        $this->baseUrl = config('services.estated.api_url', env('ESTATED_API_URL', 'https://apis.estated.com'));
    }

    /**
     * Get property details from Estated API (backup/alternative to ATTOM)
     */
    public function getPropertyDetails(string $address, ?string $city = null, ?string $state = null, ?string $zip = null): ?array
    {
        $cacheKey = "estated:property:{$address}:{$city}:{$state}:{$zip}";
        
        return Cache::remember($cacheKey, now()->addDays(7), function () use ($address, $city, $state, $zip) {
            try {
                $startTime = microtime(true);
                
                // Build address query
                $query = $address;
                if ($city) $query .= ", {$city}";
                if ($state) $query .= ", {$state}";
                if ($zip) $query .= " {$zip}";

                $response = Http::timeout($this->timeout)
                    ->withHeaders([
                        'Authorization' => "Bearer {$this->apiKey}",
                        'Accept' => 'application/json',
                    ])
                    ->get("{$this->baseUrl}/v4/property", [
                        'token' => $this->apiKey,
                        'address' => $query,
                    ]);

                $responseTime = (microtime(true) - $startTime) * 1000;
                $success = $response->successful();
                $statusCode = $response->status();

                // Log API call
                $this->logApiCall(
                    'estated',
                    '/v4/property',
                    'GET',
                    ['address' => $query],
                    $response->body(),
                    $statusCode,
                    (int) $responseTime,
                    $success,
                    $success ? null : $response->body()
                );

                if (!$success) {
                    Log::warning('Estated API error', [
                        'status' => $statusCode,
                        'response' => $response->body(),
                    ]);
                    return null;
                }

                $data = $response->json();
                return $this->extractPropertyData($data);

            } catch (\Exception $e) {
                Log::error('Estated API exception', [
                    'message' => $e->getMessage(),
                    'address' => $address,
                ]);

                $this->logApiCall(
                    'estated',
                    '/v4/property',
                    'GET',
                    ['address' => $query ?? ''],
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
     * Extract relevant property data from Estated response
     */
    protected function extractPropertyData(array $data): array
    {
        $property = $data['data']['property'] ?? [];
        $structure = $property['structure'] ?? [];
        $assessment = $property['assessment'] ?? [];
        $location = $property['location'] ?? [];

        return [
            'square_feet' => $structure['size']['building_sqft'] ?? null,
            'lot_size' => $structure['size']['lot_sqft'] ?? null,
            'year_built' => $structure['year_built'] ?? null,
            'bedrooms' => $structure['beds'] ?? null,
            'bathrooms' => $structure['baths'] ?? null,
            'property_type' => $structure['type'] ?? null,
            'assessed_value' => $assessment['assessed_value'] ?? null,
            'market_value' => $assessment['market_value'] ?? null,
            'tax_amount' => $assessment['tax_amount'] ?? null,
            'full_address' => $location['address']['formatted_street_address'] ?? null,
            'latitude' => $location['address']['latitude'] ?? null,
            'longitude' => $location['address']['longitude'] ?? null,
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

