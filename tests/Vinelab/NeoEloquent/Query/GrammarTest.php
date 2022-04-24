<?php

namespace Vinelab\NeoEloquent\Tests\Query;

use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Mockery as M;
use PHPUnit\Framework\MockObject\MockObject;
use Vinelab\NeoEloquent\Query\CypherGrammar;
use Vinelab\NeoEloquent\Tests\TestCase;

class GrammarTest extends TestCase
{
    /**
     * @var CypherGrammar
     */
    private CypherGrammar $grammar;
    /** @var Connection&MockObject  */
    private Connection $connection;
    private Builder $table;

    public function setUp(): void
    {
        parent::setUp();
        $this->grammar = new CypherGrammar();
        $this->table = DB::table('Node');
        $this->connection = $this->createMock(Connection::class);
        $this->table->connection = $this->connection;
        $this->table->grammar = $this->grammar;
    }

    public function tearDown(): void
    {
        M::close();

        parent::tearDown();
    }

    public function testGettingQueryParameterFromRegularValue(): void
    {
        $p = $this->grammar->parameter('value');
        $this->assertStringStartsWith('$param', $p);
    }

    public function testGettingIdQueryParameter(): void
    {
        $p = $this->grammar->parameter('id');
        $this->assertStringStartsWith('$param', $p);

        $p1 = $this->grammar->parameter('id');
        $this->assertStringStartsWith('$param', $p);

        $this->assertNotEquals($p, $p1);
    }

    public function testTable(): void
    {
        $p = $this->grammar->wrapTable('Node');

        $this->assertEquals('(Node:Node)', $p);
    }

    public function testTableAlias(): void
    {
        $p = $this->grammar->wrapTable('Node AS x');

        $this->assertEquals('(x:Node)', $p);
    }

    public function testTablePrefixAlias(): void
    {
        $this->grammar->setTablePrefix('x_');
        $p = $this->grammar->wrapTable('Node AS x');

        $this->assertEquals('(`x_x`:`x_Node`)', $p);
    }

    public function testTablePrefix(): void
    {
        $this->grammar->setTablePrefix('x_');
        $p = $this->grammar->wrapTable('Node');

        $this->assertEquals('(`x_Node`:`x_Node`)', $p);
    }

    public function testSimpleFrom(): void
    {
        $this->connection->expects($this->once())
            ->method('select')
            ->with('MATCH (Node:Node) RETURN *', [], true);

        $this->table->get();
    }

    public function testOrderBy(): void
    {
        $this->connection->expects($this->once())
            ->method('select')
            ->with('MATCH (Node:Node) RETURN * ORDER BY Node.x, Node.y, Node.z DESC', [], true);

//        $this->table->grammar = new MySqlGrammar();
        $this->table->orderBy('x')->orderBy('y')->orderBy('z', 'desc')->get();
    }

    public function testBasicWhereEquals(): void
    {
        $this->connection->expects($this->once())
            ->method('select')
            ->with(
                $this->matchesRegularExpression('/MATCH \(Node:Node\) WHERE Node\.x = \$param[\dabcdef]+ RETURN */'),
                $this->countOf(1),
                true
            );

        $this->table->where('x', 'y')->get();
    }

    public function testBasicWhereLessThan(): void
    {
        $this->connection->expects($this->once())
            ->method('select')
            ->with(
                $this->matchesRegularExpression('/MATCH \(Node:Node\) WHERE Node\.x < \$param[\dabcdef]+ RETURN */'),
                $this->countOf(1),
                true
            );

        $this->table->where('x', '<', 'y')->get();
    }

    public function testWhereTime(): void
    {
        $this->connection->expects($this->once())
            ->method('select')
            ->with(
                $this->matchesRegularExpression('/MATCH \(Node:Node\) WHERE Node\.x < time\(\$param[\dabcdef]+\) RETURN */'),
                $this->countOf(1),
                true
            );

        $this->table->whereTime('x', '<', '20:00')->get();
    }

    public function testWhereDate(): void
    {
        $this->connection->expects($this->once())
            ->method('select')
            ->with(
                $this->matchesRegularExpression('/MATCH \(Node:Node\) WHERE Node\.x = date\(\$param[\dabcdef]+\) RETURN */'),
                $this->countOf(1),
                true
            );

        $this->table->whereDate('x', '2020-01-02')->get();
    }

    public function testWhereYear(): void
    {
        $this->connection->expects($this->once())
            ->method('select')
            ->with(
                $this->matchesRegularExpression('/MATCH \(Node:Node\) WHERE Node\.x.year = \$param[\dabcdef]+ RETURN */'),
                $this->countOf(1),
                true
            );

        $this->table->whereYear('x', 2023)->get();
    }

    public function testWhereMonth(): void
    {
        $this->connection->expects($this->once())
            ->method('select')
            ->with(
                $this->matchesRegularExpression('/MATCH \(Node:Node\) WHERE Node\.x.month = \$param[\dabcdef]+ RETURN */'),
                $this->countOf(1),
                true
            );

        $this->table->whereMonth('x', '05')->get();
    }

    public function testWhereDay(): void
    {
        $this->connection->expects($this->once())
            ->method('select')
            ->with(
                $this->matchesRegularExpression('/MATCH \(Node:Node\) WHERE Node\.x.day = \$param[\dabcdef]+ RETURN */'),
                $this->countOf(1),
                true
            );

        $this->table->whereDay('x', 5)->get();
    }

    public function testSimpleCrossJoin(): void
    {
        $this->connection->expects($this->once())
            ->method('select')
            ->with(
                'MATCH (Node:Node) WITH Node MATCH (NewTest:NewTest) RETURN *',
                [],
                true
            );

        $this->table->crossJoin('NewTest')->get();
    }

    public function testInnerJoin(): void
    {
        $this->connection->expects($this->once())
            ->method('select')
            ->with(
                'MATCH (Node:Node) WITH Node MATCH (NewTest:NewTest) WHERE Node.id = NewTest.`test_id` RETURN *',
                [],
                true
            );

        $this->table->join('NewTest', 'Node.id', '=', 'NewTest.test_id')->get();
    }

    public function testAggregate(): void
    {
        $this->connection->expects($this->once())
            ->method('select')
            ->with(
                'MATCH (Node:Node) RETURN count(Node.views) AS count',
                [],
                true
            );

        $this->table->aggregate('count', 'views');
    }

    public function testAggregateDefault(): void
    {
        $this->connection->expects($this->once())
            ->method('select')
            ->with(
                'MATCH (Node:Node) RETURN count(*) AS count',
                [],
                true
            );

        $this->table->aggregate('count');
    }

    public function testAggregateMultiple(): void
    {
        $this->connection->expects($this->once())
            ->method('select')
            ->with(
                'MATCH (Node:Node) WITH Node.views, Node.other WHERE Node.views IS NOT NULL OR Node.other IS NOT NULL RETURN count(*) AS count',
                [],
                true
            );

        $this->table->aggregate('count', ['views', 'other']);
    }
}
