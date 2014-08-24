<?php

namespace League\Csv\test;

use ArrayIterator;
use DateTime;
use League\Csv\Writer;
use LimitIterator;
use PHPUnit_Framework_TestCase;
use SplFileObject;
use SplTempFileObject;

date_default_timezone_set('UTC');

/**
 * @group writer
 */
class WriterTest extends PHPUnit_Framework_TestCase
{

    private $csv;

    public function setUp()
    {
        $this->csv = Writer::createFromFileObject(new SplTempFileObject);
    }

    public function tearDown()
    {
        $csv = new SplFileObject(__DIR__.'/foo.csv', 'w');
        $csv->setCsvControl();
        $csv->fputcsv(["john", "doe", "john.doe@example.com"], ",", '"');
        $this->csv = null;
    }

    public function testSupportsStreamFilter()
    {
        $csv = Writer::createFromPath(__DIR__.'/foo.csv');
        $this->assertTrue($csv->isActiveStreamFilter());
        $csv->appendStreamFilter('string.toupper');
        $csv->insertOne(['jane', 'doe', 'jane@example.com']);
        $this->assertFalse($csv->isActiveStreamFilter());
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
        $this->csv->setFlags(SplFileObject::READ_AHEAD|SplFileObject::SKIP_EMPTY);
        foreach ($this->csv as $row) {
            $this->assertSame(['john', 'doe', 'john.doe@example.com'], $row);
        }
    }

    /**
     * @expectedException OutOfBoundsException
     * @expectedExceptionMessage invalid value for null handling
     */
    public function testSetterGetterNullBehavior()
    {
        $this->csv->setNullHandlingMode(Writer::NULL_AS_SKIP_CELL);
        $this->assertSame(Writer::NULL_AS_SKIP_CELL, $this->csv->getNullHandlingMode());

        $this->csv->setNullHandlingMode(23);
    }

    public function testInsertNormalFile()
    {
        $csv = Writer::createFromPath(__DIR__.'/foo.csv', 'a+');
        $csv->insertOne(['jane', 'doe', 'jane.doe@example.com']);
        $iterator = new LimitIterator($csv->getIterator(), 1, 1);
        $iterator->rewind();
        $this->assertSame(['jane', 'doe', 'jane.doe@example.com'], $iterator->getInnerIterator()->current());
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
     * @expectedExceptionMessage
     */
    public function testInsertNullThrowsException()
    {
        $this->csv->setNullHandlingMode(Writer::NULL_AS_EXCEPTION);
        $this->csv->insertOne(['john', null, 'john.doe@example.com']);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage the column count must an integer greater or equals to -1
     */
    public function testColumsCountSetterGetter()
    {
        $this->assertSame(-1, $this->csv->getColumnsCount());
        $this->csv->setColumnsCount(3);
        $this->assertSame(3, $this->csv->getColumnsCount());
        $this->csv->setColumnsCount('toto');
    }

    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage You are trying to add
     */
    public function testColumsCountConsistency()
    {
        $this->csv->insertOne(['john', 'doe', 'john.doe@example.com']);
        $this->csv->setColumnsCount(2);
        $this->csv->insertOne(['jane', 'jane.doe@example.com']);
        $this->csv->setColumnsCount(3);
        $this->csv->insertOne(['jane', 'jane.doe@example.com']);
    }

    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage You are trying to add
     */
    public function testAutoDetectColumnsCount()
    {
        $this->csv->autodetectColumnsCount();
        $this->assertSame(-1, $this->csv->getColumnsCount());
        $this->csv->insertOne(['john', 'doe', 'john.doe@example.com']);
        $this->assertSame(3, $this->csv->getColumnsCount());
        $this->csv->insertOne(['jane', 'jane.doe@example.com']);
    }

    /**
     * @expectedException PHPUnit_Framework_Error
     */
    public function testFailedInsertWithWrongData()
    {
        $this->csv->insertOne(new DateTime);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage the values are not convertible into strings
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
        $this->csv->setFlags(SplFileObject::READ_AHEAD|SplFileObject::SKIP_EMPTY);
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
     * @expectedExceptionMessage the provided data must be an array OR a \Traversable object
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

        $reader = $this->csv->newReader();
        $this->assertSame(['john', 'doe', 'john.doe@example.com'], $reader->fetchOne(0));
    }
}
