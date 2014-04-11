<?php

namespace League\Csv\test;

use SplTempFileObject;
use ArrayIterator;
use LimitIterator;
use PHPUnit_Framework_TestCase;
use DateTime;
use League\Csv\Writer;

date_default_timezone_set('UTC');

/**
 * @group writer
 */
class WriterTest extends PHPUnit_Framework_TestCase
{

    private $csv;

    public function setUp()
    {
        $this->csv = new Writer(new SplTempFileObject);
    }

    public function testInsert()
    {
        $expected = [
            ['john', 'doe', 'john.doe@example.com'],
            'john,doe,john.doe@example.com',
        ];
        foreach ($expected as $row) {
            $this->csv->insertOne($row);
        }

        foreach ($this->csv as $row) {
            $this->assertSame(['john', 'doe', 'john.doe@example.com'], $row);
        }
    }

    /**
     * @expectedException OutOfBoundsException
     */
    public function testSetterGetterNullBehavior()
    {
        $this->csv->setNullHandlingMode(Writer::NULL_AS_SKIP_CELL);
        $this->assertSame(Writer::NULL_AS_SKIP_CELL, $this->csv->getNullHandlingMode());

        $this->csv->setNullHandlingMode(23);
    }

    public function testInsertNullToSkipCell()
    {
        $expected = [
            ['john', 'doe', 'john.doe@example.com'],
            'john,doe,john.doe@example.com',
            ['john', null, 'john.doe@example.com'],
        ];
        $this->csv->setNullHandlingMode(Writer::NULL_AS_SKIP_CELL);
        foreach ($expected as $row) {
            $this->csv->insertOne($row);
        }
        $iterator = new LimitIterator($this->csv->getIterator(), 2, 1);
        $iterator->rewind();
        $res = $iterator->getInnerIterator()->current();
        $this->assertSame(['john', 'john.doe@example.com'], $res);
    }

    public function testInsertNullToEmpty()
    {
        $expected = [
            ['john', 'doe', 'john.doe@example.com'],
            'john,doe,john.doe@example.com',
            ['john', null, 'john.doe@example.com'],
        ];
        $this->csv->setNullHandlingMode(Writer::NULL_AS_EMPTY);
        foreach ($expected as $row) {
            $this->csv->insertOne($row);
        }
        $iterator = new LimitIterator($this->csv->getIterator(), 2, 1);
        $iterator->rewind();
        $res = $iterator->getInnerIterator()->current();
        $this->assertSame(['john', '', 'john.doe@example.com'], $res);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testFailedInsertWithWrongData()
    {
        $this->csv->insertOne(new DateTime);
    }

    /**
     * @expectedException RuntimeException
     */
    public function testFailedInsertWithMultiDimensionArray()
    {
        $this->csv->insertOne(['john', new DateTime]);
    }

    public function testSave()
    {
        $multipleArray = [
            ['john', 'doe', 'john.doe@example.com'],
            'jane,doe,jane.doe@example.com',
        ];
        $this->csv->insertAll($multipleArray);
        $this->csv->insertAll(new ArrayIterator($multipleArray));
        foreach ($this->csv as $key => $row) {
            $expected = ['jane', 'doe', 'jane.doe@example.com'];
            if ($key%2 == 0) {
                $expected = ['john', 'doe', 'john.doe@example.com'];
            }
            $this->assertSame($expected, $row);
        }
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testFailedSaveWithWrongType()
    {
        $this->csv->insertAll(new DateTime);
    }

    public function testGetReader()
    {
        $expected = [
            ['john', 'doe', 'john.doe@example.com'],
            'john,doe,john.doe@example.com',
        ];
        foreach ($expected as $row) {
            $this->csv->insertOne($row);
        }

        $reader = $this->csv->getReader();
        $this->assertSame(['john', 'doe', 'john.doe@example.com'], $reader->fetchOne(0));
    }

    public function testStrictMode()
    {
        $this->csv->setStrictMode('o');
        $this->assertTrue($this->csv->getStrictMode());
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testFailedConsistencyCheck()
    {
        $expected = [
            ['john', 'doe', 'john.doe@example.com'],
            ['john', 'doe'],
        ];
        $this->csv->setStrictMode(true);
        foreach ($expected as $row) {
            $this->csv->insertOne($row);
        }
    }
}
