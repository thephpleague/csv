<?php

namespace League\Csv\Test\Plugin;

use InvalidArgumentException;
use League\Csv\Plugin\ColumnConsistencyValidator;
use League\Csv\Writer;
use PHPUnit\Framework\TestCase;
use SplFileObject;
use SplTempFileObject;

/**
 * @group validators
 */
class ColumnConsistencyValidatorTest extends TestCase
{
    private $csv;

    public function setUp()
    {
        $this->csv = Writer::createFromFileObject(new SplTempFileObject());
    }

    public function tearDown()
    {
        $csv = new SplFileObject(dirname(__DIR__).'/data/foo.csv', 'w');
        $csv->setCsvControl();
        $csv->fputcsv(['john', 'doe', 'john.doe@example.com'], ',', '"');
        $this->csv = null;
    }

    public function testColumsCountSetterGetter()
    {
        $consistency = new ColumnConsistencyValidator();
        $this->assertSame(-1, $consistency->getColumnsCount());
        $consistency->setColumnsCount(3);
        $this->assertSame(3, $consistency->getColumnsCount());
        $this->expectException(InvalidArgumentException::class);
        $consistency->setColumnsCount('toto');
    }

    public function testColumsCountConsistency()
    {
        $consistency = new ColumnConsistencyValidator();
        $this->csv->addValidator($consistency, 'consistency');
        $this->csv->insertOne(['john', 'doe', 'john.doe@example.com']);
        $consistency->setColumnsCount(2);
        $this->csv->insertOne(['jane', 'jane.doe@example.com']);
        $consistency->setColumnsCount(3);
        $this->expectException(InvalidArgumentException::class);
        $this->csv->insertOne(['jane', 'jane.doe@example.com']);
    }

    public function testAutoDetectColumnsCount()
    {
        $consistency = new ColumnConsistencyValidator();
        $this->csv->addValidator($consistency, 'consistency');
        $consistency->autodetectColumnsCount();
        $this->assertSame(-1, $consistency->getColumnsCount());
        $this->csv->insertOne(['john', 'doe', 'john.doe@example.com']);
        $this->assertSame(3, $consistency->getColumnsCount());
        $this->expectException(InvalidArgumentException::class);
        $this->csv->insertOne(['jane', 'jane.doe@example.com']);
    }
}
