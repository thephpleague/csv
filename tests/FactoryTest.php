<?php

namespace LeagueTest\Csv;

use ArrayIterator;
use League\Csv\Reader;
use PHPUnit_Framework_TestCase;
use SplFileInfo;
use SplTempFileObject;

/**
 * @group factory
 */
class FactoryTest extends PHPUnit_Framework_TestCase
{
    public function testCreateFromPathWithSplFileInfo()
    {
        $path = __DIR__.'/data/foo.csv';
        $reader = Reader::createFromPath(new SplFileInfo($path));
        $this->assertInstanceof(Reader::class, $reader);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testCreateFromPathWithSplTempFileObject()
    {
        Reader::createFromPath(new SplTempFileObject());
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testCreateFromPathWithInvalidObject()
    {
        Reader::createFromPath(new ArrayIterator([]));
    }

    public function testCreateFromString()
    {
        $expected = 'john,doe,john.doe@example.com'.PHP_EOL
            .'jane,doe,jane.doe@example.com'.PHP_EOL;
        $reader = Reader::createFromString($expected);
        $this->assertInstanceof(Reader::class, $reader);
    }

    public function testCreateFromFileObjectPreserveFileObjectCsvControls()
    {
        $delimiter = "\t";
        $enclosure = '?';
        $escape = '^';
        $file = new SplTempFileObject();
        $file->setCsvControl($delimiter, $enclosure, $escape);
        $obj = Reader::createFromFileObject($file);
        $this->assertSame($delimiter, $obj->getDelimiter());
        $this->assertSame($enclosure, $obj->getEnclosure());
        if (3 === count($file->getCsvControl())) {
            $this->assertSame($escape, $obj->getEscape());
        }
    }
}
