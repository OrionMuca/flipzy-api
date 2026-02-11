<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class AttomDataMapper
{
    /**
     * Map property detail endpoint response
     */
    public function mapPropertyDetail(array $data): array
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
        $lot = $propertyData['lot'] ?? [];
        $area = $propertyData['area'] ?? [];
        $utilities = $propertyData['utilities'] ?? [];
        $identifier = $propertyData['identifier'] ?? [];
        $address = $propertyData['address'] ?? [];
        $taxData = ($assessment['tax'] ?? $assessment);
        $sale = $propertyData['sale'] ?? [];
        $saleAmount = $sale['amount'] ?? [];

        return [
            'square_feet' => $this->extractSquareFeet($buildingSize),
            'living_size' => $buildingSize['livingsize'] ?? $buildingSize['livingSize'] ?? null,
            'gross_size' => $buildingSize['grosssize'] ?? $buildingSize['grossSize'] ?? null,
            'lot_size' => $this->extractLotSize($lot),
            'year_built' => $this->extractYearBuilt($summary),
            'bedrooms' => $this->extractBedrooms($buildingRooms),
            'bathrooms' => $this->extractBathrooms($buildingRooms),
            'bathrooms_total' => $buildingRooms['bathstotal'] ?? $buildingRooms['bathsTotal'] ?? null,
            'property_type' => $this->mapPropertyType($summary),
            'assessed_value' => $this->extractValue($assessment, 'assessedvalue'),
            'market_value' => $this->extractValue($assessment, 'marketvalue'),
            'tax_amount' => $this->extractTaxAmount($taxData),
            'tax_year' => $taxData['taxyear'] ?? $taxData['taxYear'] ?? null,
            'full_address' => $address['oneLine'] ?? null,
            'one_line' => $address['oneLine'] ?? null,
            'state' => $address['countrySubd'] ?? null,
            'country' => $address['country'] ?? $address['countryCode'] ?? 'US',
            'latitude' => $this->extractCoordinate($location, 'latitude'),
            'longitude' => $this->extractCoordinate($location, 'longitude'),
            'attom_id' => $identifier['attomId'] ?? null,
            'fips' => $identifier['fips'] ?? null,
            'apn' => $identifier['apn'] ?? null,
            'zoning_type' => $lot['zoningType'] ?? $area['zoningType'] ?? null,
            'legal1' => $area['legal1'] ?? $summary['legal1'] ?? null,
            'pool_type' => $lot['pooltype'] ?? $lot['poolType'] ?? null,
            'municipality' => $area['munname'] ?? $area['munName'] ?? null,
            'cooling_type' => $utilities['coolingtype'] ?? $utilities['coolingType'] ?? null,
            'heating_type' => $utilities['heatingtype'] ?? $utilities['heatingType'] ?? null,
            'heating_fuel' => $utilities['heatingfuel'] ?? $utilities['heatingFuel'] ?? null,
            'last_sale_date' => $saleAmount['saleRecDate'] ?? $saleAmount['salerecdate'] ?? null,
            'raw_data' => $data,
        ];
    }

    /**
     * Map sale history endpoint response
     */
    public function mapSaleHistory(array $data): array
    {
        $property = $data['property'] ?? [];
        if (empty($property)) {
            return [];
        }

        $propertyData = $property[0] ?? [];
        $saleHistory = $propertyData['salehistory'] ?? [];
        $sales = $saleHistory['sale'] ?? [];

        $mappedSales = [];
        foreach ($sales as $sale) {
            $mappedSales[] = [
                'sale_date' => $sale['saleTransDate'] ?? $sale['saleDate'] ?? null,
                'sale_price' => $this->extractValue($sale, 'saleAmt'),
                'sale_type' => $sale['saleType'] ?? null,
                'document_type' => $sale['documentType'] ?? null,
                'recording_date' => $sale['recordingDate'] ?? null,
                'buyer' => $sale['buyer'] ?? null,
                'seller' => $sale['seller'] ?? null,
                'deed_type' => $sale['deedType'] ?? null,
            ];
        }

        return [
            'total_sales' => count($mappedSales),
            'sales' => $mappedSales,
            'latest_sale' => !empty($mappedSales) ? $mappedSales[0] : null,
            'raw_data' => $data,
        ];
    }

    /**
     * Map comparable sales endpoint response
     */
    public function mapComparableSales(array $data): array
    {
        $property = $data['property'] ?? [];
        if (empty($property)) {
            return [];
        }

        $comps = [];
        foreach ($property as $compProperty) {
            $assessment = $compProperty['assessment'] ?? [];
            $location = $compProperty['location'] ?? [];
            $summary = $compProperty['summary'] ?? [];
            $building = $compProperty['building'] ?? [];
            $buildingSize = $building['size'] ?? [];
            $buildingRooms = $building['rooms'] ?? [];
            $address = $compProperty['address'] ?? [];
            $saleHistory = $compProperty['salehistory'] ?? [];
            $latestSale = $saleHistory['sale'][0] ?? [];

            $comps[] = [
                'attom_id' => $compProperty['identifier']['attomId'] ?? null,
                'address' => $address['oneLine'] ?? null,
                'latitude' => $this->extractCoordinate($location, 'latitude'),
                'longitude' => $this->extractCoordinate($location, 'longitude'),
                'square_feet' => $this->extractSquareFeet($buildingSize),
                'bedrooms' => $this->extractBedrooms($buildingRooms),
                'bathrooms' => $this->extractBathrooms($buildingRooms),
                'year_built' => $this->extractYearBuilt($summary),
                'property_type' => $this->mapPropertyType($summary),
                'assessed_value' => $this->extractValue($assessment, 'assessedvalue'),
                'market_value' => $this->extractValue($assessment, 'marketvalue'),
                'latest_sale_price' => $this->extractValue($latestSale, 'saleAmt'),
                'latest_sale_date' => $latestSale['saleTransDate'] ?? $latestSale['saleDate'] ?? null,
                'distance' => $compProperty['distance'] ?? null,
            ];
        }

        return [
            'total_comps' => count($comps),
            'comparable_sales' => $comps,
            'raw_data' => $data,
        ];
    }

    /**
     * Map property events endpoint response
     */
    public function mapPropertyEvents(array $data): array
    {
        $property = $data['property'] ?? [];
        if (empty($property)) {
            return [];
        }

        $propertyData = $property[0] ?? [];
        $events = $propertyData['event'] ?? [];

        $mappedEvents = [];
        foreach ($events as $event) {
            $mappedEvents[] = [
                'event_type' => $event['eventType'] ?? null,
                'event_date' => $event['eventDate'] ?? null,
                'event_description' => $event['eventDescription'] ?? null,
                'document_type' => $event['documentType'] ?? null,
                'document_number' => $event['documentNumber'] ?? null,
                'recording_date' => $event['recordingDate'] ?? null,
                'amount' => $this->extractValue($event, 'amount'),
                'party' => $event['party'] ?? null,
            ];
        }

        // Categorize events
        $categorized = [
            'permits' => [],
            'liens' => [],
            'ownership_changes' => [],
            'other' => [],
        ];

        foreach ($mappedEvents as $event) {
            $type = strtolower($event['event_type'] ?? '');
            if (str_contains($type, 'permit')) {
                $categorized['permits'][] = $event;
            } elseif (str_contains($type, 'lien') || str_contains($type, 'mortgage')) {
                $categorized['liens'][] = $event;
            } elseif (str_contains($type, 'deed') || str_contains($type, 'sale')) {
                $categorized['ownership_changes'][] = $event;
            } else {
                $categorized['other'][] = $event;
            }
        }

        return [
            'total_events' => count($mappedEvents),
            'events' => $mappedEvents,
            'categorized' => $categorized,
            'raw_data' => $data,
        ];
    }

    /**
     * Merge data from multiple ATTOM endpoints
     */
    public function mergeAttomData(array $detailData, ?array $saleHistoryData = null, ?array $comparableSalesData = null, ?array $eventsData = null): array
    {
        $merged = $this->mapPropertyDetail($detailData);

        if ($saleHistoryData) {
            $saleHistory = $this->mapSaleHistory($saleHistoryData);
            $merged['sale_history'] = $saleHistory;
        }

        if ($comparableSalesData) {
            $comps = $this->mapComparableSales($comparableSalesData);
            $merged['comparable_sales'] = $comps;
        }

        if ($eventsData) {
            $events = $this->mapPropertyEvents($eventsData);
            $merged['property_events'] = $events;
        }

        return $merged;
    }

    /**
     * Validate property data before saving
     */
    public function validatePropertyData(array $data): array
    {
        $errors = [];

        // Validate required fields for property creation
        if (empty($data['full_address']) && empty($data['address'])) {
            $errors[] = 'Address is required';
        }

        // Validate data types and ranges
        if (isset($data['square_feet']) && $data['square_feet'] !== null) {
            if (!is_numeric($data['square_feet']) || $data['square_feet'] < 0 || $data['square_feet'] > 100000) {
                $errors[] = 'Invalid square_feet value';
            }
        }

        if (isset($data['bedrooms']) && $data['bedrooms'] !== null) {
            if (!is_numeric($data['bedrooms']) || $data['bedrooms'] < 0 || $data['bedrooms'] > 50) {
                $errors[] = 'Invalid bedrooms value';
            }
        }

        if (isset($data['bathrooms']) && $data['bathrooms'] !== null) {
            if (!is_numeric($data['bathrooms']) || $data['bathrooms'] < 0 || $data['bathrooms'] > 30) {
                $errors[] = 'Invalid bathrooms value';
            }
        }

        if (isset($data['year_built']) && $data['year_built'] !== null) {
            $currentYear = (int) date('Y');
            if (!is_numeric($data['year_built']) || $data['year_built'] < 1800 || $data['year_built'] > $currentYear) {
                $errors[] = 'Invalid year_built value';
            }
        }

        // Consistency checks
        if (isset($data['bedrooms']) && isset($data['square_feet']) && $data['bedrooms'] > 0 && $data['square_feet'] > 0) {
            $sqftPerBedroom = $data['square_feet'] / $data['bedrooms'];
            if ($sqftPerBedroom < 50 || $sqftPerBedroom > 2000) {
                $errors[] = 'Inconsistent bedrooms/square_feet ratio';
            }
        }

        // Validate coordinates
        if (isset($data['latitude']) && $data['latitude'] !== null) {
            if (!is_numeric($data['latitude']) || $data['latitude'] < -90 || $data['latitude'] > 90) {
                $errors[] = 'Invalid latitude value';
            }
        }

        if (isset($data['longitude']) && $data['longitude'] !== null) {
            if (!is_numeric($data['longitude']) || $data['longitude'] < -180 || $data['longitude'] > 180) {
                $errors[] = 'Invalid longitude value';
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Map ATTOM property type to our property type
     */
    protected function mapPropertyType(array $summary): ?string
    {
        $propClass = strtolower($summary['propclass'] ?? $summary['propClass'] ?? $summary['propertyType'] ?? '');
        
        $mapping = [
            'single family' => 'house',
            'single family residence' => 'house',
            'condominium' => 'condo',
            'townhouse' => 'townhouse',
            'multi-family' => 'multifamily',
            'commercial' => 'commercial',
            'land' => 'land',
            'mobile home' => 'mobile',
        ];

        foreach ($mapping as $attomType => $ourType) {
            if (str_contains($propClass, $attomType)) {
                return $ourType;
            }
        }

        return 'house'; // Default
    }

    /**
     * Extract square feet from building size data
     */
    protected function extractSquareFeet(array $buildingSize): ?int
    {
        $value = $buildingSize['bldgsize'] ?? $buildingSize['bldgSize'] ?? $buildingSize['livingsize'] ?? $buildingSize['livingSize'] ?? $buildingSize['universalsize'] ?? $buildingSize['universalSize'] ?? null;
        return $value ? (int) $value : null;
    }

    /**
     * Extract lot size
     */
    protected function extractLotSize(array $lot): ?int
    {
        $value = $lot['lotsize2'] ?? $lot['lotSize2'] ?? $lot['lotsize1'] ?? $lot['lotSize1'] ?? null;
        return $value ? (int) $value : null;
    }

    /**
     * Extract year built
     */
    protected function extractYearBuilt(array $summary): ?int
    {
        $value = $summary['yearbuilt'] ?? $summary['yearBuilt'] ?? null;
        return $value ? (int) $value : null;
    }

    /**
     * Extract bedrooms
     */
    protected function extractBedrooms(array $buildingRooms): ?int
    {
        $value = $buildingRooms['beds'] ?? $buildingRooms['bedrooms'] ?? null;
        return $value ? (int) $value : null;
    }

    /**
     * Extract bathrooms
     */
    protected function extractBathrooms(array $buildingRooms): ?int
    {
        $value = $buildingRooms['bathstotal'] ?? $buildingRooms['bathsTotal'] ?? $buildingRooms['bathsfull'] ?? $buildingRooms['bathsFull'] ?? $buildingRooms['baths'] ?? null;
        return $value ? (int) $value : null;
    }

    /**
     * Extract numeric value (handles various formats)
     */
    protected function extractValue(array $data, string $key): ?float
    {
        $value = $data[$key] ?? null;
        if ($value === null || $value === '') {
            return null;
        }
        
        // Remove currency symbols and commas
        if (is_string($value)) {
            $value = preg_replace('/[^0-9.-]/', '', $value);
        }
        
        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * Extract tax amount from tax data
     */
    protected function extractTaxAmount(array $taxData): ?float
    {
        $value = $taxData['taxamt'] ?? $taxData['taxAmt'] ?? $taxData['taxamount'] ?? null;
        if ($value === null || $value === '') {
            return null;
        }
        if (is_string($value)) {
            $value = preg_replace('/[^0-9.-]/', '', $value);
        }
        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * Extract coordinate (latitude/longitude)
     */
    protected function extractCoordinate(array $location, string $key): ?float
    {
        $value = $location[$key] ?? null;
        return $value ? (float) $value : null;
    }
}

