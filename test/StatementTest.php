<?php

namespace League\Csv\Test;

use League\Csv\Reader;
use League\Csv\RecordSet;
use League\Csv\Statement;
use PHPUnit\Framework\TestCase;
use SplTempFileObject;

/**
 * @group statement
 */
class StatementTest extends TestCase
{
    private $csv;

    private $stmt;

    private $expected = [
        ['john', 'doe', 'john.doe@example.com'],
        ['jane', 'doe', 'jane.doe@example.com'],
    ];

    public function setUp()
    {
        $tmp = new SplTempFileObject();
        foreach ($this->expected as $row) {
            $tmp->fputcsv($row);
        }

        $this->csv = Reader::createFromFileObject($tmp);
        $this->stmt = new Statement();
    }

    public function testProcess()
    {
        $records = $this->stmt->process($this->csv);
        $this->assertInstanceof(RecordSet::class, $records);
        $this->assertEquals($records, $this->csv->select($this->stmt));
    }

    public function testStatementImmutability()
    {
        $this->assertSame($this->stmt->setOffset(0)->setLimit(-1), $this->stmt);
        $this->assertNotSame($this->stmt->setOffset(1), $this->stmt);
    }

    public function testStatementDoesNotAllowSettingUnknownProperty()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->stmt->foo = 'bar';
    }

    public function testStatementDoesNotAllUnsetUnknownProperty()
    {
        $this->expectException(\InvalidArgumentException::class);
        unset($this->stmt->foo);
    }

    public function testSetLimitThrowException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->stmt->setLimit(-4);
    }

    public function testSetOffsetThrowException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->stmt->setOffset('toto');
    }
}
