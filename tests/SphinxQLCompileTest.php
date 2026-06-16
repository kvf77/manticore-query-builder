<?php

declare(strict_types=1);

namespace Kvf77\Manticore\Tests;

use Kvf77\Manticore\SphinxQL;
use PHPUnit\Framework\TestCase;

class SphinxQLCompileTest extends TestCase
{
    private function qb(): SphinxQL
    {
        return new SphinxQL(new ConnectionFake());
    }

    private function compile(SphinxQL $q): string
    {
        return $q->compile()->getCompiled();
    }

    public function testSimpleSelect(): void
    {
        $q = $this->qb()->select('id', 'name')->from('idx');
        $this->assertSame('SELECT id, name FROM idx', $this->compile($q));
    }

    public function testSelectAll(): void
    {
        $q = $this->qb()->select()->from('idx');
        $this->assertSame('SELECT * FROM idx', $this->compile($q));
    }

    public function testWhereEquals(): void
    {
        $q = $this->qb()->select()->from('idx')->where('name', 'John');
        $this->assertSame("SELECT * FROM idx WHERE name = 'John'", $this->compile($q));
    }

    public function testWhereOperator(): void
    {
        $q = $this->qb()->select()->from('idx')->where('age', '>=', 18);
        $this->assertSame('SELECT * FROM idx WHERE age >= 18', $this->compile($q));
    }

    public function testWhereIdIsNotQuoted(): void
    {
        $q = $this->qb()->select()->from('idx')->where('id', 5);
        $this->assertSame('SELECT * FROM idx WHERE id = 5', $this->compile($q));
    }

    public function testWhereIn(): void
    {
        $q = $this->qb()->select()->from('idx')->where('id', 'IN', [1, 2, 3]);
        $this->assertSame('SELECT * FROM idx WHERE id IN (1, 2, 3)', $this->compile($q));
    }

    public function testWhereBetween(): void
    {
        $q = $this->qb()->select()->from('idx')->where('price', 'BETWEEN', [10, 100]);
        $this->assertSame('SELECT * FROM idx WHERE price BETWEEN 10 AND 100', $this->compile($q));
    }

    public function testMultipleWhereAreAnded(): void
    {
        $q = $this->qb()->select()->from('idx')->where('a', 1)->where('b', 2);
        $this->assertSame('SELECT * FROM idx WHERE a = 1 AND b = 2', $this->compile($q));
    }

    public function testOrWhere(): void
    {
        $q = $this->qb()->select()->from('idx')->where('a', 1)->orWhere('b', 2);
        $this->assertSame('SELECT * FROM idx WHERE a = 1 OR b = 2', $this->compile($q));
    }

    public function testNestedWhereGroup(): void
    {
        $q = $this->qb()->select()->from('idx')
            ->where('a', 1)
            ->where(function (SphinxQL $sub) {
                $sub->where('b', 2)->orWhere('c', 3);
            });

        $this->assertSame('SELECT * FROM idx WHERE a = 1 AND (b = 2 OR c = 3)', $this->compile($q));
    }

    public function testDeeplyNestedWhereGroup(): void
    {
        $q = $this->qb()->select()->from('idx')
            ->where('a', 1)
            ->where(function (SphinxQL $sub) {
                $sub->where('b', 2)->orWhere(function (SphinxQL $sub2) {
                    $sub2->where('c', 3)->orWhere('d', 4);
                });
            });

        $this->assertSame(
            'SELECT * FROM idx WHERE a = 1 AND (b = 2 OR (c = 3 OR d = 4))',
            $this->compile($q)
        );
    }

    public function testRegex(): void
    {
        $q = $this->qb()->select()->from('idx')->regex('title', '.*foo.*');
        $this->assertSame("SELECT * FROM idx WHERE REGEX(title, '.*foo.*')", $this->compile($q));
    }

    public function testRegexCombinedWithWhere(): void
    {
        $q = $this->qb()->select()->from('idx')->where('a', 1)->regex('title', 'foo');
        $this->assertSame("SELECT * FROM idx WHERE a = 1 AND REGEX(title, 'foo')", $this->compile($q));
    }

    public function testMatchWithWhereGlue(): void
    {
        $q = $this->qb()->select()->from('idx')->match('title', 'hello')->where('a', 1);
        $this->assertSame("SELECT * FROM idx WHERE MATCH('(@title hello)') AND a = 1", $this->compile($q));
    }

    public function testGroupByAndOrderBy(): void
    {
        $q = $this->qb()->select()->from('idx')->groupBy('cat')->orderBy('id', 'desc');
        $this->assertSame('SELECT * FROM idx GROUP BY cat ORDER BY id DESC', $this->compile($q));
    }

    public function testOrderByWithoutDirectionEmitsNoKeyword(): void
    {
        // No direction given => no ASC/DESC keyword (Manticore treats it as ascending).
        $q = $this->qb()->select()->from('idx')->orderBy('id');
        $this->assertSame('SELECT * FROM idx ORDER BY id', $this->compile($q));
    }

    public function testOrderByRand(): void
    {
        // Random ordering: Manticore requires ORDER BY RAND() with NO direction.
        $q = $this->qb()->select()->from('idx')->orderBy('rand()');
        $this->assertSame('SELECT * FROM idx ORDER BY rand()', $this->compile($q));
    }

    public function testMultipleOrderBy(): void
    {
        $q = $this->qb()->select()->from('idx')->orderBy('a')->orderBy('b', 'desc');
        $this->assertSame('SELECT * FROM idx ORDER BY a, b DESC', $this->compile($q));
    }

    public function testLimitOnly(): void
    {
        $q = $this->qb()->select()->from('idx')->limit(10);
        $this->assertSame('SELECT * FROM idx LIMIT 0, 10', $this->compile($q));
    }

    public function testOffsetAndLimit(): void
    {
        $q = $this->qb()->select()->from('idx')->limit(5, 10);
        $this->assertSame('SELECT * FROM idx LIMIT 5, 10', $this->compile($q));
    }

    public function testOption(): void
    {
        $q = $this->qb()->select()->from('idx')->option('ranker', 'bm25');
        $this->assertSame("SELECT * FROM idx OPTION ranker = 'bm25'", $this->compile($q));
    }

    public function testInsert(): void
    {
        $q = $this->qb()->insert()->into('idx')->set(['id' => 1, 'name' => 'John']);
        $this->assertSame("INSERT INTO idx (id, name) VALUES (1, 'John')", $this->compile($q));
        $this->assertSame(1, $q->valuesAmount());
    }

    public function testReplace(): void
    {
        $q = $this->qb()->replace()->into('idx')->set(['id' => 1, 'name' => 'John']);
        $this->assertSame("REPLACE INTO idx (id, name) VALUES (1, 'John')", $this->compile($q));
    }

    public function testUpdate(): void
    {
        $q = $this->qb()->update('idx')->value('views', 5)->where('id', 1);
        $this->assertSame('UPDATE idx SET views = 5 WHERE id = 1', $this->compile($q));
    }

    public function testDelete(): void
    {
        $q = $this->qb()->delete()->from('idx')->where('id', 1);
        $this->assertSame('DELETE FROM idx WHERE id = 1', $this->compile($q));
    }

    public function testReusedInsertAccumulatesRows(): void
    {
        // The bulk-reindex pattern: one insert builder, set() called per entity,
        // staging many rows into a single multi-row INSERT.
        $q = $this->qb()->insert()->into('idx');
        $q->set(['id' => 1, 'name' => 'a']);
        $q->set(['id' => 2, 'name' => 'b']);
        $q->set(['id' => 3, 'name' => 'c']);

        $this->assertSame(3, $q->valuesAmount());
        $this->assertSame(
            "INSERT INTO idx (id, name) VALUES (1, 'a'), (2, 'b'), (3, 'c')",
            $this->compile($q)
        );
    }

    public function testEmptyInsertHasZeroValuesAmount(): void
    {
        // valuesAmount() lets you skip executing an INSERT that staged no rows.
        $q = $this->qb()->insert()->into('idx');
        $this->assertSame(0, $q->valuesAmount());
    }
}
