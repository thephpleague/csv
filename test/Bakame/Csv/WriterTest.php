<?php

namespace Bakame\Csv;

use SplTempFileObject;
use ArrayIterator;
use PHPUnit_Framework_TestCase;

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

    /**
     * @expectedException InvalidArgumentException
     */
    public function testConstructorWithWrongOpenMode()
    {
        new Writer('foo.csv', 'r');
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
     * @expectedException InvalidArgumentException
     */
    public function testFailedInsertWithWrongData()
    {
        $this->csv->insertOne(new \DateTime);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testFailedInsertWithMultiDimensionArray()
    {
        $this->csv->insertOne(['john', new \DateTime]);
    }

    public function testSave()
    {
        $multipleArray = [
            ['john', 'doe', 'john.doe@example.com'],
            'jane,doe,jane.doe@example.com',
        ];
        $this->csv->insertMany($multipleArray);
        $this->csv->insertMany(new ArrayIterator($multipleArray));
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
        $this->csv->insertMany(new \DateTime);
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
}
