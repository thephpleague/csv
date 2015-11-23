<?php

namespace League\Csv\Test;

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
        $path = __DIR__.'/foo.csv';
        $csv  = Reader::createFromPath($path);
        $this->assertSame($path, $csv->getIterator()->getRealPath());
    }

    public function testCreateFromPathWithSplFileInfo()
    {
        $path = __DIR__.'/foo.csv';
        $csv  = Reader::createFromPath(new SplFileInfo($path));
        $this->assertSame($path, $csv->getIterator()->getRealPath());
    }

    public function testCreateFromPathWithPHPWrapper()
    {
        $path = __DIR__.'/foo.csv';
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

    public function testCreateFromString()
    {
        $expected = 'john,doe,john.doe@example.com'.PHP_EOL
            .'jane,doe,jane.doe@example.com'.PHP_EOL;
        $reader = Reader::createFromString($expected);
        $this->assertInstanceof('League\Csv\Reader', $reader);
    }

    public function testCreateFromFileObject()
    {
        $reader = Reader::createFromFileObject(new SplTempFileObject());
        $this->assertInstanceof('League\Csv\Reader', $reader);
        $this->assertInstanceof('SplTempFileObject', $reader->getIterator());
    }

    public function testCreateFromFileObjectWithSplFileObject()
    {
        $path   = __DIR__.'/foo.csv';
        $obj    = new SplFileObject($path);
        $reader = Reader::createFromFileObject($obj);
        $this->assertInstanceof('League\Csv\Reader', $reader);
        $this->assertInstanceof('SplFileObject', $reader->getIterator());
    }
}
