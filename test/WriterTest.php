<?php

namespace League\Csv\Test;

use ArrayIterator;
use League\Csv\Writer;
use SplFileObject;
use SplTempFileObject;
use stdClass;

/**
 * @group writer
 */
class WriterTest extends AbstractTestCase
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

    public function testSupportsStreamFilter()
    {
        $csv = Writer::createFromPath(__DIR__.'/data/foo.csv');
        $this->assertTrue($csv->isActiveStreamFilter());
        $csv->appendStreamFilter('string.toupper');
        $csv->insertOne(['jane', 'doe', 'jane@example.com']);
        $this->assertFalse($csv->isActiveStreamFilter());
    }

    public function testInsert()
    {
        $expected = [
            ['john', 'doe', 'john.doe@example.com'],
            'jane,doe,jane.doe@example.com',
        ];
        foreach ($expected as $row) {
            $this->csv->insertOne($row);
        }
        $this->assertContains(['john', 'doe', 'john.doe@example.com'], $this->csv);
        $this->assertContains(['jane', 'doe', 'jane.doe@example.com'], $this->csv);
    }

    public function testInsertNormalFile()
    {
        $csv = Writer::createFromPath(__DIR__.'/data/foo.csv', 'a+');
        $csv->insertOne(['jane', 'doe', 'jane.doe@example.com']);
        $this->assertContains(['jane', 'doe', 'jane.doe@example.com'], $csv);
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
        $this->assertContains($expected, $this->csv);
    }

    public function dataToSave()
    {
        $multipleArray = [
            ['john', 'doe', 'john.doe@example.com'],
            'jane,doe,jane.doe@example.com',
        ];

        return [
            'array' => [$multipleArray, $multipleArray[0]],
            'iterator' => [new ArrayIterator($multipleArray), ['jane', 'doe', 'jane.doe@example.com']],
        ];
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

    public function testCustomNewline()
    {
        $csv = Writer::createFromFileObject(new SplTempFileObject());
        $this->assertSame("\n", $csv->getNewline());
        $csv->setNewline("\r\n");

        $csv->insertOne(['jane', 'doe']);
        $this->assertSame("jane,doe\r\n", (string) $csv);
    }

    public function testAddValidationRules()
    {
        $func = function (array $row) {
            return $row;
        };

        $this->csv->addValidator($func, 'func1');
        $this->csv->addValidator($func, 'func2');
        $this->assertTrue($this->csv->hasValidator('func1'));
        $this->csv->removeValidator('func1');
        $this->assertTrue($this->csv->hasValidator('func2'));
        $this->csv->clearValidators();
        $this->assertFalse($this->csv->hasValidator('func2'));
    }

    public function testFormatterRules()
    {
        $func = function (array $row) {
            return array_map('strtoupper', $row);
        };

        $this->csv->addFormatter($func);
        $this->csv->addFormatter($func);
        $this->assertTrue($this->csv->hasFormatter($func));
        $this->csv->removeFormatter($func);
        $this->assertTrue($this->csv->hasFormatter($func));
        $this->csv->clearFormatters();
        $this->assertFalse($this->csv->hasFormatter($func));
    }

    public function testConversionWithWriter()
    {
        $this->csv->insertAll([
            ['john', 'doe', 'john.doe@example.com'],
            ['jane', 'doe', 'jane.doe@example.com'],
            ['toto', 'le', 'herisson'],
        ]);
        $this->assertStringStartsWith('<table', $this->csv->toHTML());
    }
}
