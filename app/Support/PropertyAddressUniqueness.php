<?php

namespace App\Support;

use App\Models\Property;
use Illuminate\Database\Eloquent\Builder;

/**
 * Detects duplicate property listings by normalized US-style address (street, city, state, ZIP).
 * Soft-deleted properties are ignored (Eloquent default scope).
 */
final class PropertyAddressUniqueness
{
    /**
     * Canonical comparison key: street and city are uppercased with collapsed whitespace;
     * state is uppercased; US ZIP+4 is reduced to 5 digits, other postal codes are uppercased with spaces removed.
     */
    public static function normalizedKey(string $address, string $city, string $state, string $zip): string
    {
        return implode("\0", [
            self::normalizeStreet($address),
            self::normalizeCity($city),
            strtoupper(trim($state)),
            self::normalizeZip($zip),
        ]);
    }

    /**
     * @return string|null Conflicting property id, if any
     */
    public static function findConflictId(
        string $address,
        string $city,
        string $state,
        string $zip,
        ?string $ignorePropertyId = null
    ): ?string {
        $target = self::normalizedKey($address, $city, $state, $zip);

        $query = self::narrowQuery($city, $state, $zip)
            ->select(['id', 'address', 'city', 'state', 'zip_code']);

        if ($ignorePropertyId !== null) {
            $query->where('id', '!=', $ignorePropertyId);
        }

        foreach ($query->cursor() as $property) {
            if (self::normalizedKey(
                (string) $property->address,
                (string) $property->city,
                (string) $property->state,
                (string) $property->zip_code
            ) === $target) {
                return $property->id;
            }
        }

        return null;
    }

    private static function narrowQuery(string $city, string $state, string $zip): Builder
    {
        $stateNorm = strtoupper(trim($state));
        $cityCompare = strtolower(trim($city));

        $query = Property::query()
            ->where('state', $stateNorm)
            ->whereRaw('LOWER(TRIM(city)) = ?', [$cityCompare]);

        self::applyZipConstraint($query, $zip);

        return $query;
    }

    private static function applyZipConstraint(Builder $query, string $zip): void
    {
        $zipTrim = trim($zip);

        if (preg_match('/^(\d{5})(-\d{4})?$/', $zipTrim, $m)) {
            $zip5 = $m[1];
            $query->where(function ($q) use ($zip5, $zipTrim) {
                $q->where('zip_code', $zip5)
                    ->orWhere('zip_code', 'like', $zip5.'-%')
                    ->orWhere('zip_code', $zipTrim);
            });

            return;
        }

        $collapsed = str_replace(' ', '', strtoupper($zipTrim));
        $query->whereRaw('REPLACE(UPPER(TRIM(zip_code)), " ", "") = ?', [$collapsed]);
    }

    private static function normalizeStreet(string $address): string
    {
        return strtoupper(trim(preg_replace('/\s+/', ' ', $address)));
    }

    private static function normalizeCity(string $city): string
    {
        return strtoupper(trim(preg_replace('/\s+/', ' ', $city)));
    }

    private static function normalizeZip(string $zip): string
    {
        $z = trim($zip);

        if (preg_match('/^(\d{5})(-\d{4})?$/', $z, $m)) {
            return $m[1];
        }

        return str_replace(' ', '', strtoupper($z));
    }
}
