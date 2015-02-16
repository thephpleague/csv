<?php

namespace League\Csv\Test\Formatter;

use League\Csv\Writer;
use League\Csv\Exporter\Formatters\NullFormatter;
use LimitIterator;
use PHPUnit_Framework_TestCase;
use SplFileObject;
use SplTempFileObject;

/**
 * @group formatter
 */
class NullFormatterTest extends PHPUnit_Framework_TestCase
{
    private $csv;

    public function setUp()
    {
        $this->csv = Writer::createFromFileObject(new SplTempFileObject());
    }

    public function tearDown()
    {
        $csv = new SplFileObject(dirname(__DIR__).'/foo.csv', 'w');
        $csv->setCsvControl();
        $csv->fputcsv(["john", "doe", "john.doe@example.com"], ",", '"');
        $this->csv = null;
    }

    /**
     * @expectedException OutOfBoundsException
     */
    public function testSetterGetterNullBehavior()
    {
        $formatter = new NullFormatter();
        $formatter->setMode(NullFormatter::NULL_AS_SKIP_CELL);
        $this->assertSame(NullFormatter::NULL_AS_SKIP_CELL, $formatter->getMode());

        $formatter->setMode(23);
    }


    public function testInsertNullToSkipCell()
    {
        $expected = [
            ['john', 'doe', 'john.doe@example.com'],
            'john,doe,john.doe@example.com',
            ['john', null, 'john.doe@example.com'],
        ];
        $formatter = new NullFormatter();
        $formatter->setMode(NullFormatter::NULL_AS_SKIP_CELL);
        $this->csv->addFormatter($formatter);
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
        $formatter = new NullFormatter();
        $formatter->setMode(NullFormatter::NULL_AS_EMPTY);
        $this->csv->addFormatter($formatter);
        foreach ($expected as $row) {
            $this->csv->insertOne($row);
        }
        $iterator = new LimitIterator($this->csv->getIterator(), 2, 1);
        $iterator->rewind();
        $res = $iterator->getInnerIterator()->current();
        $this->assertSame(['john', '', 'john.doe@example.com'], $res);
    }
}
