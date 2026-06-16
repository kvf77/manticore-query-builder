<?php

declare(strict_types=1);

namespace Kvf77\Manticore\Tests;

use InvalidArgumentException;
use Kvf77\Manticore\Expression;
use Kvf77\Manticore\Geo;
use Kvf77\Manticore\SphinxQL;
use PHPUnit\Framework\TestCase;

class GeoCompileTest extends TestCase
{
    private function qb(): SphinxQL
    {
        return new SphinxQL(new ConnectionFake());
    }

    public function testGeodistDistanceSearch(): void
    {
        // Geo functions can only live in SELECT in Manticore; compute an alias,
        // then filter / sort on that alias.
        $q = $this->qb()
            ->select('id', new Expression('GEODIST(lat, lon, 40.7, -74.0, {in=degrees, out=miles}) AS distance'))
            ->from('companies')
            ->where('distance', '<=', 50)
            ->orderBy('distance', 'asc');

        $this->assertSame(
            'SELECT id, GEODIST(lat, lon, 40.7, -74.0, {in=degrees, out=miles}) AS distance '
            .'FROM companies WHERE distance <= 50 ORDER BY distance ASC',
            $q->compile()->getCompiled()
        );
    }

    public function testPolygonContainsSearch(): void
    {
        // contains(geopoly2d(<flat coord list>), lat, lon) -> 1/0, aliased as `inside`,
        // then filtered to keep only points inside the polygon.
        $polygon = '40.7,-74.0,40.8,-74.1,40.75,-73.9';

        $q = $this->qb()
            ->select('id', new Expression("CONTAINS(GEOPOLY2D($polygon), lat, lon) AS inside"))
            ->from('companies')
            ->where('inside', 1);

        $this->assertSame(
            'SELECT id, CONTAINS(GEOPOLY2D(40.7,-74.0,40.8,-74.1,40.75,-73.9), lat, lon) AS inside '
            .'FROM companies WHERE inside = 1',
            $q->compile()->getCompiled()
        );
    }

    public function testGeoDistanceHelper(): void
    {
        $q = $this->qb()
            ->select('id', Geo::distance(40.7, -74.0))
            ->from('companies')
            ->where('distance', '<=', 50)
            ->orderBy('distance', 'asc');

        $this->assertSame(
            'SELECT id, GEODIST(lat, lon, 40.7, -74, {in=degrees, out=miles}) AS distance '
            .'FROM companies WHERE distance <= 50 ORDER BY distance ASC',
            $q->compile()->getCompiled()
        );
    }

    public function testGeoDistanceHelperWithCustomColumnsAndUnit(): void
    {
        $expr = Geo::distance(40.7, -74.0, latColumn: 'start_lat', lonColumn: 'start_lon', as: 'dist', unit: 'km');

        $this->assertSame(
            'GEODIST(start_lat, start_lon, 40.7, -74, {in=degrees, out=km}) AS dist',
            (string) $expr
        );
    }

    public function testGeoDistanceHelperRejectsUnknownUnit(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Geo::distance(40.7, -74.0, unit: 'parsecs');
    }

    public function testGeoContainsFromGeoJson(): void
    {
        $geoJson = [
            'type' => 'Polygon',
            'coordinates' => [[[40.7, -74.0], [40.8, -74.1], [40.75, -73.9], [40.7, -74.0]]],
        ];

        $q = $this->qb()
            ->select('id', Geo::contains($geoJson))
            ->from('companies')
            ->where('inside', 1);

        $this->assertSame(
            'SELECT id, CONTAINS(GEOPOLY2D(40.7,-74,40.8,-74.1,40.75,-73.9,40.7,-74), lat, lon) AS inside '
            .'FROM companies WHERE inside = 1',
            $q->compile()->getCompiled()
        );
    }

    public function testFlattenRealMultiPolygon(): void
    {
        // A real MultiPolygon (4 levels of nesting) drawn on a map.
        $json = '{"type":"MultiPolygon","coordinates":[[[[32.1839088,34.8152584],'
            .'[32.1800428,34.8161601],[32.17977,34.8184872],[32.1839088,34.8152584]]]]}';

        $this->assertSame(
            '32.1839,34.8153,32.18,34.8162,32.1798,34.8185,32.1839,34.8153',
            Geo::flattenPolygon($json)
        );
    }

    public function testFlattenSwapsLonLatInput(): void
    {
        // Standard GeoJSON stores [lon, lat]; lonLatInput: true swaps to lat,lon for GEOPOLY2D.
        $coords = ['coordinates' => [[[-74.0, 40.7], [-74.1, 40.8], [-73.9, 40.75]]]];

        $this->assertSame(
            '40.7,-74,40.8,-74.1,40.75,-73.9',
            Geo::flattenPolygon($coords, lonLatInput: true)
        );
    }

    public function testFlattenRejectsTooFewPoints(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Geo::flattenPolygon(['coordinates' => [[[40.7, -74.0], [40.8, -74.1]]]]);
    }

    public function testFlattenRejectsInvalidJson(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Geo::flattenPolygon('not json');
    }
}
