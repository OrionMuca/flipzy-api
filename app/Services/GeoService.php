<?php

namespace App\Services;

use App\Models\ApiLog;
use App\Models\Property;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GeoService
{
    protected string $service;
    protected ?string $mapboxToken;
    protected int $timeout = 10;

    public function __construct()
    {
        $this->service = config('services.geo.service', env('GEO_SERVICE', 'openstreetmap'));
        $this->mapboxToken = config('services.geo.mapbox_token', env('MAPBOX_ACCESS_TOKEN'));
    }

    /**
     * Geocode an address to get coordinates
     */
    public function geocode(string $address, ?string $city = null, ?string $state = null, ?string $zip = null, bool $forceFresh = false): ?array
    {
        $fullAddress = $this->buildAddress($address, $city, $state, $zip);
        $cacheKey = "geo:{$fullAddress}";
        
        // If forcing fresh data, clear cache first
        if ($forceFresh) {
            Cache::forget($cacheKey);
        }
        
        return Cache::remember($cacheKey, now()->addDays(30), function () use ($fullAddress) {
            try {
                if ($this->service === 'mapbox' && $this->mapboxToken) {
                    return $this->geocodeWithMapbox($fullAddress);
                } else {
                    return $this->geocodeWithOpenStreetMap($fullAddress);
                }
            } catch (\Exception $e) {
                Log::error('Geocoding exception', [
                    'message' => $e->getMessage(),
                    'address' => $fullAddress,
                ]);
                return null;
            }
        });
    }

    /**
     * Get state from IP address using IP geolocation
     */
    public function getStateFromIp(string $ipAddress): ?string
    {
        // Skip private/local IPs
        if (!filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return null;
        }
        
        $cacheKey = "ip_geo:{$ipAddress}";
        
        return Cache::remember($cacheKey, now()->addDays(30), function () use ($ipAddress) {
            try {
                // Use ipapi.co (free tier: 1,000 requests/day)
                $response = Http::timeout(5)->get("https://ipapi.co/{$ipAddress}/json/");
                
                if ($response->successful()) {
                    $data = $response->json();
                    // Return US state code (2-letter abbreviation)
                    $state = $data['region_code'] ?? null;
                    
                    // Only return if it's a valid US state code (2 letters)
                    if ($state && strlen($state) === 2 && ctype_alpha($state)) {
                        return strtoupper($state);
                    }
                }
            } catch (\Exception $e) {
                Log::warning('IP geolocation failed', [
                    'ip' => $ipAddress,
                    'error' => $e->getMessage(),
                ]);
            }
            
            return null;
        });
    }

    /**
     * Reverse geocode coordinates to get address
     */
    public function reverseGeocode(float $latitude, float $longitude): ?array
    {
        $cacheKey = "geo:reverse:{$latitude}:{$longitude}";
        
        return Cache::remember($cacheKey, now()->addDays(30), function () use ($latitude, $longitude) {
            try {
                if ($this->service === 'mapbox' && $this->mapboxToken) {
                    return $this->reverseGeocodeWithMapbox($latitude, $longitude);
                } else {
                    return $this->reverseGeocodeWithOpenStreetMap($latitude, $longitude);
                }
            } catch (\Exception $e) {
                Log::error('Reverse geocoding exception', ['message' => $e->getMessage()]);
                return null;
            }
        });
    }

    /**
     * Geocode using Mapbox API
     */
    protected function geocodeWithMapbox(string $address): ?array
    {
        $startTime = microtime(true);

        $response = Http::timeout($this->timeout)
            ->get("https://api.mapbox.com/geocoding/v5/mapbox.places/{$address}.json", [
                'access_token' => $this->mapboxToken,
                'limit' => 1,
            ]);

        $responseTime = (microtime(true) - $startTime) * 1000;

        if (!$response->successful()) {
            $this->logApiCall('mapbox', '/geocoding/v5/mapbox.places', 'GET', ['address' => $address], $response->body(), $response->status(), (int) $responseTime, false, $response->body());
            return null;
        }

        $data = $response->json();
        $features = $data['features'] ?? [];

        if (empty($features)) {
            return null;
        }

        $feature = $features[0];
        $coordinates = $feature['geometry']['coordinates'] ?? [];

        $this->logApiCall('mapbox', '/geocoding/v5/mapbox.places', 'GET', ['address' => $address], $response->body(), $response->status(), (int) $responseTime, true);

        return [
            'latitude' => $coordinates[1] ?? null,
            'longitude' => $coordinates[0] ?? null,
            'formatted_address' => $feature['place_name'] ?? null,
            'raw_data' => $data,
        ];
    }

    /**
     * Geocode using OpenStreetMap (free, no API key needed)
     */
    protected function geocodeWithOpenStreetMap(string $address): ?array
    {
        $startTime = microtime(true);

        $response = Http::timeout($this->timeout)
            ->get('https://nominatim.openstreetmap.org/search', [
                'q' => $address,
                'format' => 'json',
                'limit' => 1,
                'addressdetails' => 1,
            ]);

        $responseTime = (microtime(true) - $startTime) * 1000;

        if (!$response->successful() || empty($response->json())) {
            $this->logApiCall('openstreetmap', '/search', 'GET', ['address' => $address], $response->body(), $response->status(), (int) $responseTime, false);
            return null;
        }

        $data = $response->json();
        $result = $data[0] ?? null;

        if (!$result) {
            return null;
        }

        $this->logApiCall('openstreetmap', '/search', 'GET', ['address' => $address], $response->body(), $response->status(), (int) $responseTime, true);

        return [
            'latitude' => (float) ($result['lat'] ?? 0),
            'longitude' => (float) ($result['lon'] ?? 0),
            'formatted_address' => $result['display_name'] ?? null,
            'raw_data' => $result,
        ];
    }

    /**
     * Reverse geocode using Mapbox
     */
    protected function reverseGeocodeWithMapbox(float $latitude, float $longitude): ?array
    {
        $startTime = microtime(true);

        $response = Http::timeout($this->timeout)
            ->get("https://api.mapbox.com/geocoding/v5/mapbox.places/{$longitude},{$latitude}.json", [
                'access_token' => $this->mapboxToken,
                'limit' => 1,
            ]);

        $responseTime = (microtime(true) - $startTime) * 1000;

        if (!$response->successful()) {
            return null;
        }

        $data = $response->json();
        $features = $data['features'] ?? [];

        if (empty($features)) {
            return null;
        }

        $feature = $features[0];

        return [
            'address' => $feature['text'] ?? null,
            'formatted_address' => $feature['place_name'] ?? null,
            'raw_data' => $data,
        ];
    }

    /**
     * Reverse geocode using OpenStreetMap
     */
    protected function reverseGeocodeWithOpenStreetMap(float $latitude, float $longitude): ?array
    {
        $startTime = microtime(true);

        $response = Http::timeout($this->timeout)
            ->get('https://nominatim.openstreetmap.org/reverse', [
                'lat' => $latitude,
                'lon' => $longitude,
                'format' => 'json',
                'addressdetails' => 1,
            ]);

        $responseTime = (microtime(true) - $startTime) * 1000;

        if (!$response->successful()) {
            return null;
        }

        $data = $response->json();
        $address = $data['address'] ?? [];

        return [
            'address' => $address['house_number'] . ' ' . $address['road'] ?? null,
            'city' => $address['city'] ?? $address['town'] ?? null,
            'state' => $address['state'] ?? null,
            'zip_code' => $address['postcode'] ?? null,
            'formatted_address' => $data['display_name'] ?? null,
            'raw_data' => $data,
        ];
    }

    /**
     * Build full address string
     */
    protected function buildAddress(string $address, ?string $city, ?string $state, ?string $zip): string
    {
        $parts = [$address];
        if ($city) $parts[] = $city;
        if ($state) $parts[] = $state;
        if ($zip) $parts[] = $zip;
        return implode(', ', $parts);
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

