<?php

namespace LeagueTest\Csv;

use ArrayIterator;
use League\Csv\Writer;
use PHPUnit_Framework_TestCase;
use SplFileObject;
use SplTempFileObject;
use stdClass;

/**
 * @group writer
 */
class WriterTest extends PHPUnit_Framework_TestCase
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
     * @expectedException InvalidArgumentException
     */
    public function testflushThreshold()
    {
        $this->csv->setFlushThreshold(12);
        $this->assertSame(12, $this->csv->getFlushThreshold());
        $this->csv->setFlushThreshold(-1);
    }

    public function testSupportsStreamFilter()
    {
        $csv = Writer::createFromPath(__DIR__.'/data/foo.csv');
        $this->assertTrue($csv->isActiveStreamFilter());
        $csv->setFlushThreshold(1);
        $csv->appendStreamFilter('string.toupper');
        $csv->insertOne(['jane', 'doe', 'jane@example.com']);
        $this->assertContains('JANE,DOE,JANE@EXAMPLE.COM', (string) $csv);
    }

    public function testInsert()
    {
        $expected = [
            ['john', 'doe', 'john.doe@example.com'],
        ];
        foreach ($expected as $row) {
            $this->csv->insertOne($row);
        }
        $this->assertContains('john,doe,john.doe@example.com', (string) $this->csv);
    }

    public function testInsertNormalFile()
    {
        $csv = Writer::createFromPath(__DIR__.'/data/foo.csv', 'a+');
        $csv->insertOne(['jane', 'doe', 'jane.doe@example.com']);
        $this->assertContains('jane,doe,jane.doe@example.com', (string) $csv);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testFailedSaveWithWrongType()
    {
        $this->csv->insertAll(new stdClass());
    }

    /**
     * @param  $argument
     * @param  $expected
     * @dataProvider dataToSave
     */
    public function testSave($argument, $expected)
    {
        $this->csv->insertAll($argument);
        $this->assertContains($expected, (string) $this->csv);
    }

    public function dataToSave()
    {
        $multipleArray = [
            ['john', 'doe', 'john.doe@example.com'],
        ];

        return [
            'array' => [$multipleArray, 'john,doe,john.doe@example.com'],
            'iterator' => [new ArrayIterator($multipleArray), 'john,doe,john.doe@example.com'],
        ];
    }

    public function testCustomNewline()
    {
        $this->assertSame("\n", $this->csv->getNewline());
        $this->csv->setNewline("\r\n");
        $this->csv->insertOne(['jane', 'doe']);
        $this->assertSame("jane,doe\r\n", (string) $this->csv);
    }

    public function testAddValidationRules()
    {
        $func = function (array $row) {
            return true;
        };

        $this->csv->addValidator($func, 'func1');
    }

    public function testFormatterRules()
    {
        $func = function (array $row) {
            return array_map('strtoupper', $row);
        };

        $this->csv->addFormatter($func);
        $this->csv->insertOne(['jane', 'doe']);
        $this->assertSame("JANE,DOE\n", (string) $this->csv);
    }
}