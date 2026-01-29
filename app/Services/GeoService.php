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

    /** Shorter timeout for address suggestions so UI doesn't hang (Nominatim can be slow). */
    protected int $suggestionTimeout = 4;

    /**
     * US state name to 2-letter code (for ATTOM-style address format)
     */
    protected const US_STATE_ABBREV = [
        'alabama' => 'AL', 'alaska' => 'AK', 'arizona' => 'AZ', 'arkansas' => 'AR', 'california' => 'CA',
        'colorado' => 'CO', 'connecticut' => 'CT', 'delaware' => 'DE', 'district of columbia' => 'DC',
        'florida' => 'FL', 'georgia' => 'GA', 'hawaii' => 'HI', 'idaho' => 'ID', 'illinois' => 'IL',
        'indiana' => 'IN', 'iowa' => 'IA', 'kansas' => 'KS', 'kentucky' => 'KY', 'louisiana' => 'LA',
        'maine' => 'ME', 'maryland' => 'MD', 'massachusetts' => 'MA', 'michigan' => 'MI', 'minnesota' => 'MN',
        'mississippi' => 'MS', 'missouri' => 'MO', 'montana' => 'MT', 'nebraska' => 'NE', 'nevada' => 'NV',
        'new hampshire' => 'NH', 'new jersey' => 'NJ', 'new mexico' => 'NM', 'new york' => 'NY',
        'north carolina' => 'NC', 'north dakota' => 'ND', 'ohio' => 'OH', 'oklahoma' => 'OK', 'oregon' => 'OR',
        'pennsylvania' => 'PA', 'rhode island' => 'RI', 'south carolina' => 'SC', 'south dakota' => 'SD',
        'tennessee' => 'TN', 'texas' => 'TX', 'utah' => 'UT', 'vermont' => 'VT', 'virginia' => 'VA',
        'washington' => 'WA', 'west virginia' => 'WV', 'wisconsin' => 'WI', 'wyoming' => 'WY',
    ];

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
     * Get address suggestions for autocomplete (as user types).
     * Uses Mapbox when token is set, otherwise OpenStreetMap Nominatim.
     * Supports zip-only (e.g. "19977"), state+zip (e.g. "DE 19977"), or full US address (e.g. "468 SEQUOIA DR, SMYRNA, DE 19977").
     * Results are cached to improve response time.
     *
     * @return array<int, array{formatted_address: string, latitude: float, longitude: float, place_id: string, address_components?: array}>
     */
    public function getAddressSuggestions(string $query, int $limit = 8): array
    {
        $query = trim($query);
        if (strlen($query) < 2) {
            return [];
        }

        $normalized = preg_replace('/\s+/', ' ', strtolower($query));
        $cacheKey = 'address_suggestions:' . md5($normalized . '|' . $limit);
        $ttl = now()->addHours(24);

        return Cache::remember($cacheKey, $ttl, function () use ($query, $limit) {
            try {
                if ($this->service === 'mapbox' && $this->mapboxToken) {
                    return $this->getAddressSuggestionsMapbox($query, $limit);
                }
                return $this->getAddressSuggestionsOpenStreetMap($query, $limit);
            } catch (\Exception $e) {
                Log::error('Address suggestions exception', [
                    'message' => $e->getMessage(),
                    'query' => $query,
                ]);
                return [];
            }
        });
    }

    /**
     * Address suggestions via Mapbox Geocoding API
     */
    protected function getAddressSuggestionsMapbox(string $query, int $limit): array
    {
        $encoded = rawurlencode($query);
        $response = Http::timeout($this->suggestionTimeout)
            ->get("https://api.mapbox.com/geocoding/v5/mapbox.places/{$encoded}.json", [
                'access_token' => $this->mapboxToken,
                'types' => 'address,place,postcode',
                'country' => 'US',
                'limit' => min($limit, 10),
            ]);

        if (!$response->successful()) {
            return [];
        }

        $data = $response->json();
        $features = $data['features'] ?? [];
        $suggestions = [];

        foreach ($features as $feature) {
            $coordinates = $feature['geometry']['coordinates'] ?? [];
            $context = $feature['context'] ?? [];
            $components = $this->mapMapboxContextToComponents($context);

            // Build street: first part of place_name, or address number + text
            $placeName = $feature['place_name'] ?? '';
            $street = $this->extractStreetFromMapboxFeature($feature, $placeName);

            $city = $components['city'];
            $stateRaw = $components['state'];
            $stateCode = $this->usStateAbbrev($stateRaw);
            $postcode = $components['postcode'];

            $placeType = $feature['place_type'][0] ?? '';
            $displayStreet = in_array($placeType, ['place', 'postcode'], true) ? '' : ($street ?: $placeName);
            $formattedAddress = $this->formatAddressForAttom($displayStreet, $city, $stateCode ?? $stateRaw, $postcode);

            $components['state_code'] = $stateCode;
            $suggestions[] = [
                'formatted_address' => $formattedAddress,
                'latitude' => (float) ($coordinates[1] ?? 0),
                'longitude' => (float) ($coordinates[0] ?? 0),
                'place_id' => $feature['id'] ?? '',
                'address_components' => $components,
            ];
        }

        return $suggestions;
    }

    /**
     * Extract street line from Mapbox feature (for ATTOM format)
     */
    protected function extractStreetFromMapboxFeature(array $feature, string $placeName): string
    {
        $props = $feature['properties'] ?? [];
        $addressNumber = trim($props['address'] ?? '');
        $text = trim($feature['text'] ?? '');
        if ($addressNumber !== '' && $text !== '') {
            return $addressNumber . ' ' . $text;
        }
        if ($text !== '') {
            return $text;
        }
        $parts = array_map('trim', explode(',', $placeName));
        return $parts[0] ?? $placeName;
    }

    /**
     * Address suggestions via OpenStreetMap Nominatim.
     *
     * Policy note: Public Nominatim does not allow autocomplete; we throttle to 1 req/s and cache.
     * For production address suggestions, prefer Mapbox or a self-hosted Nominatim instance.
     * OSM address data is often incomplete – many fields can be null depending on the result type.
     */
    protected function getAddressSuggestionsOpenStreetMap(string $query, int $limit): array
    {
        $lock = Cache::lock('nominatim_suggestion', 1);
        $lock->block(2);

        try {
            $response = Http::timeout($this->suggestionTimeout)
                ->withHeaders(['User-Agent' => config('app.name', 'Flipzy') . '/1.0'])
                ->get('https://nominatim.openstreetmap.org/search', [
                    'q' => $query,
                    'format' => 'json',
                    'limit' => min($limit, 10),
                    'addressdetails' => 1,
                    'countrycodes' => 'us',
                ]);

            if (!$response->successful() || !is_array($response->json())) {
                return [];
            }

            $items = $response->json();
            $suggestions = [];

            foreach ($items as $item) {
                $addr = $item['address'] ?? [];
                $houseNumber = $this->nullIfEmpty(trim($addr['house_number'] ?? ''));
                $road = $this->nullIfEmpty(trim($addr['road'] ?? ''));
                $this->normalizeNominatimStreetComponents($houseNumber, $road);
                $street = trim(($houseNumber ?? '') . ' ' . ($road ?? ''));
                $city = $this->extractCityFromNominatimAddress($addr);
                $stateRaw = $this->nullIfEmpty($addr['state'] ?? null);
                $stateCode = $this->usStateAbbrev($stateRaw);
                $postcode = $this->nullIfEmpty($addr['postcode'] ?? null);

                $formattedAddress = $this->formatAddressForAttom($street, $city, $stateCode ?? $stateRaw, $postcode);
                if ($formattedAddress === '') {
                    $formattedAddress = strtoupper(trim(mb_substr($item['display_name'] ?? 'Address', 0, 80)));
                }

                $components = [
                    'street' => $houseNumber,
                    'road' => $road,
                    'city' => $city,
                    'state' => $stateRaw,
                    'state_code' => $stateCode,
                    'postcode' => $postcode,
                    'country' => $this->nullIfEmpty($addr['country'] ?? null),
                ];

                $suggestions[] = [
                    'formatted_address' => $formattedAddress,
                    'latitude' => (float) ($item['lat'] ?? 0),
                    'longitude' => (float) ($item['lon'] ?? 0),
                    'place_id' => (string) ($item['place_id'] ?? ''),
                    'address_components' => $components,
                ];
            }

            return $suggestions;
        } finally {
            $lock->release();
        }
    }

    /**
     * Normalize OSM quirks: Nominatim sometimes puts a house number in 'road' and leaves 'house_number' empty.
     * When road is just a number (e.g. "468"), treat it as house number so street/road components make sense.
     */
    protected function normalizeNominatimStreetComponents(?string &$houseNumber, ?string &$road): void
    {
        if ($houseNumber !== null && $houseNumber !== '') {
            return;
        }
        if ($road === null || $road === '') {
            return;
        }
        $trimmed = trim($road);
        if ($trimmed === '' || !ctype_digit($trimmed)) {
            return;
        }
        $houseNumber = $trimmed;
        $road = null;
    }

    /**
     * Extract city/locality from Nominatim address. OSM uses different keys by region and result type.
     */
    protected function extractCityFromNominatimAddress(array $addr): ?string
    {
        $keys = ['city', 'town', 'village', 'municipality', 'hamlet', 'locality', 'county', 'state_district'];
        foreach ($keys as $key) {
            $v = $addr[$key] ?? null;
            if ($v !== null && trim((string) $v) !== '') {
                return $this->nullIfEmpty(trim((string) $v));
            }
        }
        return null;
    }

    /** Normalize empty or non-string to null for consistent JSON (OSM data can be missing or inconsistent). */
    protected function nullIfEmpty(mixed $value): ?string
    {
        if ($value === null || !is_string($value)) {
            return null;
        }
        $trimmed = trim($value);
        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * Format address for ATTOM search: "STREET, CITY, STATE ZIP" (uppercase).
     * Example: "468 SEQUOIA DR, SMYRNA, DE 19977"
     */
    protected function formatAddressForAttom(string $street, ?string $city, ?string $state, ?string $postcode): string
    {
        $stateCode = $this->usStateAbbrev($state);
        $statePart = $stateCode ?? trim($state ?? '');
        $parts = array_filter([
            trim($street),
            trim($city ?? ''),
            trim($statePart . ($postcode ? ' ' . trim($postcode) : '')),
        ]);

        return strtoupper(implode(', ', $parts));
    }

    /**
     * US state name to 2-letter code (e.g. "Delaware" -> "DE")
     */
    protected function usStateAbbrev(?string $state): ?string
    {
        if ($state === null || $state === '') {
            return null;
        }
        $key = strtolower(trim($state));
        if (strlen($key) === 2) {
            return strtoupper($key);
        }
        return self::US_STATE_ABBREV[$key] ?? null;
    }

    /**
     * Map Mapbox context array to address_components
     */
    protected function mapMapboxContextToComponents(array $context): array
    {
        $components = [
            'street' => null,
            'road' => null,
            'city' => null,
            'state' => null,
            'postcode' => null,
            'country' => null,
        ];

        foreach ($context as $item) {
            $id = $item['id'] ?? '';
            $text = $item['text'] ?? null;
            if (str_contains($id, 'place.')) {
                $components['city'] = $text;
            } elseif (str_contains($id, 'region.')) {
                $components['state'] = $text;
            } elseif (str_contains($id, 'postcode.')) {
                $components['postcode'] = $text;
            } elseif (str_contains($id, 'country.')) {
                $components['country'] = $text;
            }
        }

        return $components;
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

