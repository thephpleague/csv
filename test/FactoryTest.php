<?php

namespace League\Csv\Test;

use ArrayIterator;
use League\Csv\Reader;
use SplFileInfo;
use SplFileObject;
use SplTempFileObject;

/**
 * @group factory
 */
class FactoryTest extends AbstractTestCase
{
    public function testCreateFromPathWithFilePath()
    {
        $path = __DIR__.'/data/foo.csv';
        $csv  = Reader::createFromPath($path);
        $this->assertSame($path, $csv->getIterator()->getRealPath());
    }

    public function testCreateFromPathWithSplFileInfo()
    {
        $path = __DIR__.'/data/foo.csv';
        $csv  = Reader::createFromPath(new SplFileInfo($path));
        $this->assertSame($path, $csv->getIterator()->getRealPath());
    }

    public function testCreateFromPathWithPHPWrapper()
    {
        $path = __DIR__.'/data/foo.csv';
        $csv = Reader::createFromPath('php://filter/read=string.toupper/resource='.$path);
        $this->assertFalse($csv->getIterator()->getRealPath());
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

    public function testCreateFromFileObject()
    {
        $reader = Reader::createFromFileObject(new SplTempFileObject());
        $this->assertInstanceof(Reader::class, $reader);
        $this->assertInstanceof(SplTempFileObject::class, $reader->getIterator());
    }

    public function testCreateFromFileObjectWithSplFileObject()
    {
        $path   = __DIR__.'/data/foo.csv';
        $obj    = new SplFileObject($path);
        $reader = Reader::createFromFileObject($obj);
        $this->assertInstanceof(Reader::class, $reader);
        $this->assertInstanceof(SplFileObject::class, $reader->getIterator());
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
