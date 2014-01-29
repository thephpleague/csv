<?php

namespace Bakame\Csv;

use SplTempFileObject;
use ArrayIterator;
use PHPUnit_Framework_TestCase;

class WriterTest extends PHPUnit_Framework_TestCase
{

    private $csv;

    public function setUp()
    {
        $this->csv = new Writer(new SplTempFileObject);
    }

    public function testAppend()
    {
        $expected = [
            ['john', 'doe', 'john.doe@example.com'],
            'john,doe,john.doe@example.com',
        ];
        foreach ($expected as $row) {
            $this->csv->append($row);
        }

        foreach ($this->csv as $row) {
            $this->assertSame(['john', 'doe', 'john.doe@example.com'], $row);
        }
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testFailedAppendWithWrongData()
    {
        $this->csv->append(new \DateTime);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testFailedAppendWithMultiDimensionArray()
    {
        $this->csv->append(['john', new \DateTime]);
    }

    public function testSave()
    {
        $multipleArray = [
            ['john', 'doe', 'john.doe@example.com'],
            'jane,doe,jane.doe@example.com',
        ];
        $this->csv->save($multipleArray);
        $this->csv->save(new ArrayIterator($multipleArray));
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
        $this->csv->save(new \DateTime);
    }

    public function testGetReader()
    {
        $expected = [
            ['john', 'doe', 'john.doe@example.com'],
            'john,doe,john.doe@example.com',
        ];
        foreach ($expected as $row) {
            $this->csv->append($row);
        }

        $reader = $this->csv->getReader();
        $this->assertSame(['john', 'doe', 'john.doe@example.com'], $reader[0]);
    }
}
