<?php

namespace League\Csv\Test;

use InvalidArgumentException;
use League\Csv\Reader;
use League\Csv\RecordSet;
use League\Csv\Statement;
use PHPUnit\Framework\TestCase;

/**
 * @group statement
 */
class StatementTest extends TestCase
{
    private $stmt;

    public function setUp()
    {
        $this->stmt = new Statement();
    }

    public function testProcess()
    {
        $expected = <<<EOF
Year,Make,Model,Description,Price
1997,Ford,E350,"ac, abs, moon",3000.00
1999,Chevy,"Venture ""Extended Edition""","",4900.00
1999,Chevy,"Venture ""Extended Edition, Very Large""",,5000.00
1996,Jeep,Grand Cherokee,"MUST SELL!
air, moon roof, loaded",4799.00
EOF;
        $csv = Reader::createFromString($expected);
        $records = $this->stmt->process($csv);
        $this->assertInstanceof(RecordSet::class, $records);
        $this->assertEquals($records, $csv->select($this->stmt));
        $this->assertEquals($records, new RecordSet($csv, $this->stmt));
    }

    public function testStatementImmutability()
    {
        $this->assertSame($this->stmt->setOffset(0)->setLimit(-1), $this->stmt);
        $this->assertNotSame($this->stmt->setOffset(1), $this->stmt);
        $this->assertNotSame($this->stmt->setLimit(1), $this->stmt);
        $this->assertNotSame($this->stmt->addFilter(function ($row) {
            return count($row) != 4;
        }), $this->stmt);
        $this->assertNotSame($this->stmt->addSortBy(function ($rowA, $rowB) {
            return strcasecmp($rowA[0], $rowB[0]);
        }), $this->stmt);
    }

    public function testConstructorSetAllProperties()
    {
        $this->assertEquals(0, $this->stmt->getOffset());
        $this->assertEquals(-1, $this->stmt->getLimit());
        $this->assertEquals([], $this->stmt->getSortBy());
        $this->assertEquals([], $this->stmt->getFilter());
    }

    public function testStatementDoesNotAllowSettingUnknownProperty()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->stmt->foo = 'bar';
    }

    public function testStatementDoesNotAllUnsetUnknownProperty()
    {
        $this->expectException(InvalidArgumentException::class);
        unset($this->stmt->foo);
    }

    public function testSetLimitThrowException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->stmt->setLimit(-4);
    }

    public function testSetOffsetThrowException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->stmt->setOffset('toto');
    }
}
