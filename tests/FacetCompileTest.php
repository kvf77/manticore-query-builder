<?php

declare(strict_types=1);

namespace Kvf77\Manticore\Tests;

use Kvf77\Manticore\Expression;
use Kvf77\Manticore\Facet;
use Kvf77\Manticore\SphinxQL;
use PHPUnit\Framework\TestCase;

class FacetCompileTest extends TestCase
{
    private function qb(): SphinxQL
    {
        return new SphinxQL(new ConnectionFake());
    }

    public function testSingleColumnFacet(): void
    {
        $q = $this->qb()->select()->from('idx')->facet((new Facet())->facet('status'));
        $this->assertSame('SELECT * FROM idx FACET status', $q->compile()->getCompiled());
    }

    public function testFacetWithAliasExpression(): void
    {
        $q = $this->qb()->select()->from('idx')
            ->facet((new Facet())->facet(new Expression('INTEGER(price > 10) AS expensive'))->limit(0, 100));

        $this->assertSame(
            'SELECT * FROM idx FACET INTEGER(price > 10) AS expensive LIMIT 0, 100',
            $q->compile()->getCompiled()
        );
    }

    public function testMixedSimpleAndComputedFacets(): void
    {
        // Mirrors the real-world "legends/stats" pattern: count(distinct id) + a mix of
        // plain-column facets and computed `expression AS alias` facets in one query.
        $now = 1000;
        $fields = [
            'late_eta',
            'should_arrive' => "INTEGER(next_stop_time >= $now)",
        ];

        $q = $this->qb()
            ->select('count(distinct id) as count_record')
            ->from(['trips'])
            ->where('is_active', 1);

        foreach ($fields as $key => $field) {
            if (is_string($key)) {
                $q->facet((new Facet())->facet(new Expression("$field AS $key"))->limit(0, 100));
            } else {
                $q->facet((new Facet())->facet($field)->limit(0, 100));
            }
        }

        $this->assertSame(
            'SELECT count(distinct id) as count_record FROM trips WHERE is_active = 1 '
            .'FACET late_eta LIMIT 0, 100 '
            .'FACET INTEGER(next_stop_time >= 1000) AS should_arrive LIMIT 0, 100',
            $q->compile()->getCompiled()
        );
    }

    public function testFacetByAndOrderBy(): void
    {
        $q = $this->qb()->select()->from('idx')
            ->facet((new Facet())->facet('brand')->by('brand_id')->orderBy('count(*)', 'desc'));

        $this->assertSame(
            'SELECT * FROM idx FACET brand BY brand_id ORDER BY count(*) DESC',
            $q->compile()->getCompiled()
        );
    }
}
