<?php

namespace League\Csv\Test\Validators;

use ArrayIterator;
use DateTime;
use League\Csv\Writer;
use League\Csv\Validators\ColumnConsistency;
use LimitIterator;
use PHPUnit_Framework_TestCase;
use SplFileObject;
use SplTempFileObject;

date_default_timezone_set('UTC');

/**
 * @group validators
 */
class ColumnConsistencyTest extends PHPUnit_Framework_TestCase
{
    private $csv;

    public function setUp()
    {
        $this->csv = Writer::createFromFileObject(new SplTempFileObject());
    }

    public function tearDown()
    {
        $csv = new SplFileObject(__DIR__.'/foo.csv', 'w');
        $csv->setCsvControl();
        $csv->fputcsv(["john", "doe", "john.doe@example.com"], ",", '"');
        $this->csv = null;
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage the column count must an integer greater or equals to -1
     */
    public function testColumsCountSetterGetter()
    {
        $consistency = new ColumnConsistency();
        $this->assertSame(-1, $consistency->getColumnsCount());
        $consistency->setColumnsCount(3);
        $this->assertSame(3, $consistency->getColumnsCount());
        $consistency->setColumnsCount('toto');
    }

    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessageRegexp Adding \d+ cells on a \d+ cells per row CSV
     */
    public function testColumsCountConsistency()
    {
        $consistency = new ColumnConsistency();
        $this->csv->addValidationRule($consistency);
        $this->csv->insertOne(['john', 'doe', 'john.doe@example.com']);
        $consistency->setColumnsCount(2);
        $this->csv->insertOne(['jane', 'jane.doe@example.com']);
        $consistency->setColumnsCount(3);
        $this->csv->insertOne(['jane', 'jane.doe@example.com']);
    }

    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessageRegexp Adding \d+ cells on a \d+ cells per row CSV
     */
    public function testAutoDetectColumnsCount()
    {
        $consistency = new ColumnConsistency();
        $this->csv->addValidationRule($consistency);
        $consistency->autodetectColumnsCount();
        $this->assertSame(-1, $consistency->getColumnsCount());
        $this->csv->insertOne(['john', 'doe', 'john.doe@example.com']);
        $this->assertSame(3, $consistency->getColumnsCount());
        $this->csv->insertOne(['jane', 'jane.doe@example.com']);
    }
}
