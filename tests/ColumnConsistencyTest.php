<?php

namespace LeagueTest\Csv;

use League\Csv\ColumnConsistency;
use League\Csv\Exception\InsertionException;
use League\Csv\Exception\OutOfRangeException;
use League\Csv\Writer;
use PHPUnit\Framework\TestCase;
use SplFileObject;
use SplTempFileObject;

/**
 * @group writer
 * @coversDefaultClass League\Csv\ColumnConsistency
 */
class ColumnConsistencyTest extends TestCase
{
    private $csv;

    public function setUp()
    {
        $this->csv = Writer::createFromFileObject(new SplTempFileObject());
    }

    public function tearDown()
    {
        $csv = new SplFileObject(__DIR__.'/data/foo.csv', 'w');
        $csv->setCsvControl();
        $csv->fputcsv(['john', 'doe', 'john.doe@example.com'], ',', '"');
        $this->csv = null;
    }

    /**
     * @covers ::__construct
     * @covers ::getColumnCount
     * @covers ::__invoke
     * @covers League\Csv\Exception\InsertionException
     */
    public function testAutoDetect()
    {
        try {
            $expected = ['jane', 'jane.doe@example.com'];
            $validator = new ColumnConsistency();
            $this->csv->addValidator($validator, 'consistency');
            $this->assertSame(-1, $validator->getColumnCount());
            $this->csv->insertOne(['john', 'doe', 'john.doe@example.com']);
            $this->assertSame(3, $validator->getColumnCount());
            $this->csv->insertOne($expected);
        } catch (InsertionException $e) {
            $this->assertSame($e->getName(), 'consistency');
            $this->assertEquals($e->getRecord(), ['jane', 'jane.doe@example.com']);
        }
    }

    /**
     * @covers ::__construct
     * @covers ::__invoke
     * @covers League\Csv\Exception\InsertionException
     */
    public function testColumnsCount()
    {
        $this->expectException(InsertionException::class);
        $this->csv->addValidator(new ColumnConsistency(3), 'consistency');
        $this->csv->insertOne(['john', 'doe', 'john.doe@example.com']);
        $this->csv->insertOne(['jane', 'jane.doe@example.com']);
    }

    /**
     * @covers ::__construct
     * @covers League\Csv\Exception\OutOfRangeException
     */
    public function testColumsCountTriggersException()
    {
        $this->expectException(OutOfRangeException::class);
        new ColumnConsistency(-2);
    }
}
