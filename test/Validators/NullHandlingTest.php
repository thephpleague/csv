<?php

namespace League\Csv\Test\Validators;

use ArrayIterator;
use DateTime;
use League\Csv\Writer;
use League\Csv\Validators\NullHandling;
use LimitIterator;
use PHPUnit_Framework_TestCase;
use SplFileObject;
use SplTempFileObject;

date_default_timezone_set('UTC');

/**
 * @group validators
 */
class NullHandlingTest extends PHPUnit_Framework_TestCase
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
     * @expectedException OutOfBoundsException
     * @expectedExceptionMessage invalid value for null handling
     */
    public function testSetterGetterNullBehavior()
    {
        $plugin = new NullHandling();
        $plugin->setNullHandlingMode(NullHandling::NULL_AS_SKIP_CELL);
        $this->assertSame(NullHandling::NULL_AS_SKIP_CELL, $plugin->getNullHandlingMode());

        $plugin->setNullHandlingMode(23);
    }


    public function testInsertNullToSkipCell()
    {
        $expected = [
            ['john', 'doe', 'john.doe@example.com'],
            'john,doe,john.doe@example.com',
            ['john', null, 'john.doe@example.com'],
        ];
        $plugin = new NullHandling();
        $plugin->setNullHandlingMode(NullHandling::NULL_AS_SKIP_CELL);
        $this->csv->addValidationRule($plugin);
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
        $plugin = new NullHandling();
        $plugin->setNullHandlingMode(NullHandling::NULL_AS_EMPTY);
        $this->csv->addValidationRule($plugin);
        foreach ($expected as $row) {
            $this->csv->insertOne($row);
        }
        $iterator = new LimitIterator($this->csv->getIterator(), 2, 1);
        $iterator->rewind();
        $res = $iterator->getInnerIterator()->current();
        $this->assertSame(['john', '', 'john.doe@example.com'], $res);
    }

    public function testInsertWithoutNullHandlingMode()
    {
        $expected = [
            ['john', 'doe', 'john.doe@example.com'],
            'john,doe,john.doe@example.com',
            ['john', null, 'john.doe@example.com'],
        ];
        $plugin = new NullHandling();
        $plugin->setNullHandlingMode(NullHandling::NULL_HANDLING_DISABLED);
        $this->csv->addValidationRule($plugin);
        $this->csv->insertAll($expected);

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
        $plugin = new NullHandling();
        $plugin->setNullHandlingMode(NullHandling::NULL_AS_EXCEPTION);
        $this->csv->addValidationRule($plugin);
        $this->csv->insertOne(['john', null, 'john.doe@example.com']);
    }
}
