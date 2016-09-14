<?php

namespace League\Csv\Test;

use BadMethodCallException;
use League\Csv\Reader;
use League\Csv\RecordSet;
use PHPUnit\Framework\TestCase;

/**
 * @group reader
 */
class ReaderTest extends TestCase
{
    private $csv;

    private $expected = <<<EOF
Year,Make,Model,Description,Price
1997,Ford,E350,"ac, abs, moon",3000.00
1999,Chevy,"Venture ""Extended Edition""","",4900.00
1999,Chevy,"Venture ""Extended Edition, Very Large""",,5000.00
1996,Jeep,Grand Cherokee,"MUST SELL!
air, moon roof, loaded",4799.00
EOF;

    public function setUp()
    {
        $this->csv = Reader::createFromString($this->expected);
    }

    public function testSelect()
    {
        $this->assertInstanceof(RecordSet::class, $this->csv->select());
    }

    public function testCall()
    {
        $this->csv->setHeader(0);
        $this->assertEquals($this->csv->select()->count(), $this->csv->count());
        $this->assertEquals($this->csv->select()->jsonSerialize(), $this->csv->jsonSerialize());
        $this->assertEquals($this->csv->select()->fetchAll(), $this->csv->fetchAll());
        $this->assertEquals($this->csv->select()->fetchOne(), $this->csv->fetchOne());
        $this->assertEquals($this->csv->select()->fetchColumn(), $this->csv->fetchColumn());
        $this->assertEquals($this->csv->select()->fetchPairs(), $this->csv->fetchPairs());
        $this->assertEquals($this->csv->select()->toXML(), $this->csv->toXML());
        $this->assertEquals($this->csv->select()->toHTML(), $this->csv->toHTML());
    }

    public function testCallThrowsBadMethodCallException()
    {
        $this->expectException(BadMethodCallException::class);
        $this->csv->filterFieldName('john');
    }
}
