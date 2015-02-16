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
        $this->csv = Writer::createFromFileObject(new SplTempFileObject());
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

    public function testInsertNormalFile()
    {
        $csv = Writer::createFromPath(__DIR__.'/foo.csv', 'a+');
        $csv->insertOne(['jane', 'doe', 'jane.doe@example.com']);
        $iterator = new LimitIterator($csv->getIterator(), 1, 1);
        $iterator->rewind();
        $this->assertSame(['jane', 'doe', 'jane.doe@example.com'], $iterator->getInnerIterator()->current());
    }

    /**
     * @expectedException PHPUnit_Framework_Error
     */
    public function testInsertWithoutValidation()
    {
        $expected = [
            ['john', 'doe', 'john.doe@example.com'],
            'john,doe,john.doe@example.com',
            ['john', null, 'john.doe@example.com'],
            new \StdClass,
        ];
        $this->csv->useValidation(false);
        $this->csv->insertAll($expected);
    }

    /**
     * @expectedException PHPUnit_Framework_Error
     */
    public function testFailedInsertWithWrongData()
    {
        $this->csv->insertOne(new DateTime());
    }

    /**
     * @expectedException PHPUnit_Framework_Error
     */
    public function testFailedInsertWithMultiDimensionArray()
    {
        $this->csv->insertOne(['john', new DateTime()]);
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
        $this->csv->insertAll(new DateTime());
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

        $csv->insertOne(["jane", "doe"]);
        $this->assertSame("jane,doe\r\n", (string) $csv);
    }

    public function testCustomNewlineFromCreateFromString()
    {
        $expected = "\r\n";
        $raw = "john,doe,john.doe@example.com".PHP_EOL
            ."jane,doe,jane.doe@example.com".PHP_EOL;
        $csv = Writer::createFromString($raw, $expected);
        $this->assertSame($expected, $csv->getNewline());
    }

    public function testAddRules()
    {
        $func = function (array $row) {
            return $row;
        };

        $this->csv->addValidationRule($func);
        $this->csv->addValidationRule($func);
        $this->assertTrue($this->csv->hasValidationRule($func));
        $this->csv->removeValidationRule($func);
        $this->assertTrue($this->csv->hasValidationRule($func));
        $this->csv->clearValidationRules();
        $this->assertFalse($this->csv->hasValidationRule($func));
    }
}
