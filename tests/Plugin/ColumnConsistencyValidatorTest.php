<?php

namespace LeagueTest\Csv\Plugin;

use League\Csv\Exception;
use League\Csv\InsertionException;
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
        $this->expectException(Exception::class);
        $consistency = new ColumnConsistencyValidator();
        $this->assertSame(-1, $consistency->getColumnsCount());
        $consistency->setColumnsCount(3);
        $this->assertSame(3, $consistency->getColumnsCount());
        $consistency->setColumnsCount(-3);
    }

    public function testColumsCountConsistency()
    {
        $this->expectException(InsertionException::class);
        $consistency = new ColumnConsistencyValidator();
        $this->csv->addValidator($consistency, 'consistency');
        $this->csv->insertOne(['john', 'doe', 'john.doe@example.com']);
        $consistency->setColumnsCount(2);
        $this->csv->insertOne(['jane', 'jane.doe@example.com']);
        $consistency->setColumnsCount(3);
        $this->csv->insertOne(['jane', 'jane.doe@example.com']);
    }

    public function testAutoDetectColumnsCount()
    {
        $this->expectException(InsertionException::class);
        $consistency = new ColumnConsistencyValidator();
        $this->csv->addValidator($consistency, 'consistency');
        $consistency->autodetectColumnsCount();
        $this->assertSame(-1, $consistency->getColumnsCount());
        $this->csv->insertOne(['john', 'doe', 'john.doe@example.com']);
        $this->assertSame(3, $consistency->getColumnsCount());
        $this->csv->insertOne(['jane', 'jane.doe@example.com']);
    }
}
