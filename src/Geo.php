<?php

declare(strict_types=1);

namespace Kvf77\Manticore;

use InvalidArgumentException;

/**
 * Factory for Manticore geo-search expressions.
 *
 * Manticore geo functions (GEODIST, GEOPOLY2D, CONTAINS) may only appear in the
 * SELECT clause, never directly in WHERE. These helpers return aliased {@see Expression}
 * objects meant to be passed to select(); you then filter / sort on the alias:
 *
 *     $qb->select('id', Geo::distance($lat, $lon, as: 'distance'))
 *        ->from('idx')
 *        ->where('distance', '<=', 50)
 *        ->orderBy('distance', 'asc');
 *
 *     $qb->select('id', Geo::contains($geoJson, as: 'inside'))
 *        ->from('idx')
 *        ->where('inside', 1);
 *
 * All coordinates are validated as numeric and rendered as plain decimals, so no
 * untrusted input ever reaches the raw expression string.
 */
final class Geo
{
    /**
     * Distance unit aliases accepted by GEODIST's `out=` option.
     *
     * @var array<string, string>
     */
    private const UNITS = [
        'mi' => 'miles', 'mile' => 'miles', 'miles' => 'miles',
        'km' => 'km', 'kilometer' => 'km', 'kilometers' => 'km', 'kilometre' => 'km', 'kilometres' => 'km',
        'm' => 'm', 'meter' => 'm', 'meters' => 'm', 'metre' => 'm', 'metres' => 'm',
        'ft' => 'ft', 'foot' => 'ft', 'feet' => 'ft',
        'deg' => 'deg', 'degree' => 'deg', 'degrees' => 'deg',
    ];

    /**
     * Builds `GEODIST(<latColumn>, <lonColumn>, <lat>, <lon>, {in=degrees, out=<unit>}) AS <as>`.
     *
     * @param  float  $lat  Target point latitude (degrees)
     * @param  float  $lon  Target point longitude (degrees)
     * @param  string  $latColumn  Index column holding the row latitude
     * @param  string  $lonColumn  Index column holding the row longitude
     * @param  string  $as  Alias to expose the computed distance under
     * @param  string  $unit  Output unit: miles|km|m|ft|deg (and common aliases)
     */
    public static function distance(
        float $lat,
        float $lon,
        string $latColumn = 'lat',
        string $lonColumn = 'lon',
        string $as = 'distance',
        string $unit = 'miles'
    ): Expression {
        $out = self::UNITS[strtolower($unit)]
            ?? throw new InvalidArgumentException("Unsupported distance unit: {$unit}");

        return new Expression(sprintf(
            'GEODIST(%s, %s, %s, %s, {in=degrees, out=%s}) AS %s',
            $latColumn,
            $lonColumn,
            self::coord($lat),
            self::coord($lon),
            $out,
            $as
        ));
    }

    /**
     * Builds `CONTAINS(GEOPOLY2D(<flattened polygon>), <latColumn>, <lonColumn>) AS <as>`,
     * returning 1 for rows inside the polygon and 0 otherwise.
     *
     * @param  string|array<mixed>  $polygon  GeoJSON (array or JSON string), a bare coordinates
     *                                          array, or an already-flat "lat,lon,lat,lon,..." string
     * @param  string  $latColumn  Index column holding the row latitude
     * @param  string  $lonColumn  Index column holding the row longitude
     * @param  string  $as  Alias to expose the 1/0 flag under
     * @param  int  $precision  Decimal places to round each coordinate to
     * @param  bool  $lonLatInput  Set true when the input pairs are [lon, lat]
     *                                          (standard GeoJSON) instead of [lat, lon]
     */
    public static function contains(
        string|array $polygon,
        string $latColumn = 'lat',
        string $lonColumn = 'lon',
        string $as = 'inside',
        int $precision = 4,
        bool $lonLatInput = false
    ): Expression {
        return new Expression(sprintf(
            'CONTAINS(GEOPOLY2D(%s), %s, %s) AS %s',
            self::normalizePolygon($polygon, $precision, $lonLatInput),
            $latColumn,
            $lonColumn,
            $as
        ));
    }

    /**
     * Flattens a GeoJSON geometry (Polygon, MultiPolygon, or a bare coordinates array,
     * at any nesting depth) into the flat "lat,lon,lat,lon,..." list GEOPOLY2D expects.
     *
     * @param  string|array<mixed>  $geoJson  GeoJSON object/geometry as an array or JSON string
     * @param  int  $precision  Decimal places to round each coordinate to
     * @param  bool  $lonLatInput  Set true when input pairs are [lon, lat] (standard GeoJSON)
     */
    public static function flattenPolygon(string|array $geoJson, int $precision = 4, bool $lonLatInput = false): string
    {
        if (is_string($geoJson)) {
            $decoded = json_decode($geoJson, true);
            if (!is_array($decoded)) {
                throw new InvalidArgumentException('Invalid GeoJSON: not a JSON object/array.');
            }
            $geoJson = $decoded;
        }

        /** @var array<mixed> $coordinates */
        $coordinates = $geoJson['coordinates'] ?? $geoJson;

        $pairs = [];
        self::collectPairs($coordinates, $pairs, $lonLatInput);

        if (count($pairs) < 3) {
            throw new InvalidArgumentException('A polygon requires at least 3 coordinate pairs.');
        }

        $flat = [];
        foreach ($pairs as [$lat, $lon]) {
            $flat[] = self::coord($lat, $precision);
            $flat[] = self::coord($lon, $precision);
        }

        return implode(',', $flat);
    }

    /**
     * Accepts GeoJSON (array/JSON string), a bare coordinates array, or an already-flat
     * numeric string, and returns a validated flat coordinate list.
     *
     * @param  string|array<mixed>  $polygon
     */
    private static function normalizePolygon(string|array $polygon, int $precision, bool $lonLatInput): string
    {
        if (is_string($polygon) && self::isFlatList($polygon)) {
            return $polygon;
        }

        return self::flattenPolygon($polygon, $precision, $lonLatInput);
    }

    private static function isFlatList(string $value): bool
    {
        return (bool) preg_match('/^-?\d+(\.\d+)?(,-?\d+(\.\d+)?)*$/', trim($value));
    }

    /**
     * Recursively descends into nested coordinate arrays and collects the leaf
     * [a, b] pairs, normalising them to [lat, lon] order.
     *
     * @param  array<mixed>  $coordinates
     * @param  array<int, array{float, float}>  $pairs
     */
    private static function collectPairs(array $coordinates, array &$pairs, bool $lonLatInput): void
    {
        if (self::isPair($coordinates)) {
            $a = (float) $coordinates[0];
            $b = (float) $coordinates[1];
            $pairs[] = $lonLatInput ? [$b, $a] : [$a, $b];

            return;
        }

        foreach ($coordinates as $child) {
            if (is_array($child)) {
                self::collectPairs($child, $pairs, $lonLatInput);
            }
        }
    }

    /**
     * @param  array<mixed>  $value
     */
    private static function isPair(array $value): bool
    {
        return count($value) === 2
            && isset($value[0], $value[1])
            && is_numeric($value[0]) && is_numeric($value[1]);
    }

    /**
     * Renders a coordinate as a locale-independent plain decimal with no trailing zeros.
     */
    private static function coord(float $value, int $precision = 7): string
    {
        $formatted = rtrim(rtrim(sprintf('%.'.$precision.'F', $value), '0'), '.');

        return $formatted === '' || $formatted === '-0' ? '0' : $formatted;
    }
}
