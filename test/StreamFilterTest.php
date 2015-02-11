<?php

namespace League\Csv\test;

use League\Csv\Reader;
use League\Csv\Writer;
use PHPUnit_Framework_TestCase;
use SplFileObject;
use SplTempFileObject;

/**
 * @group csv
 */
class StreamFilterTest extends PHPUnit_Framework_TestCase
{
    public function testInitStreamFilter()
    {
        $filter = 'php://filter/write=string.rot13/resource='.__DIR__.'/foo.csv';
        $csv = Reader::createFromPath($filter);
        $this->assertTrue($csv->hasStreamFilter('string.rot13'));
        $this->assertSame(STREAM_FILTER_WRITE, $csv->getStreamFilterMode());

        $filter = 'php://filter/read=string.toupper/resource='.__DIR__.'/foo.csv';
        $csv = Reader::createFromPath($filter);
        $this->assertTrue($csv->hasStreamFilter('string.toupper'));
        $this->assertSame(STREAM_FILTER_READ, $csv->getStreamFilterMode());
    }

    /**
     * @expectedException LogicException
     * @expectedExceptionMessage The stream filter API can not be used
     */
    public function testInitStreamFilterWithSplFileObject()
    {
        Reader::createFromFileObject(new SplFileObject(__DIR__.'/foo.csv'))->getStreamFilterMode();
    }

    public function testappendStreamFilter()
    {
        $csv = Reader::createFromPath(__DIR__.'/foo.csv');
        $csv->appendStreamFilter('string.toupper');
        $csv->setFlags(SplFileObject::READ_AHEAD|SplFileObject::SKIP_EMPTY);
        foreach ($csv->getIterator() as $row) {
            $this->assertSame($row, ['JOHN', 'DOE', 'JOHN.DOE@EXAMPLE.COM']);
        }
    }

    /**
     * @expectedException LogicException
     * @expectedExceptionMessage The stream filter API can not be used
     */
    public function testFailedprependStreamFilter()
    {
        $csv = Reader::createFromFileObject(new SplTempFileObject());
        $this->assertFalse($csv->isActiveStreamFilter());
        $csv->prependStreamFilter('string.toupper');
    }

    /**
     * @expectedException LogicException
     * @expectedExceptionMessage The stream filter API can not be used
     */
    public function testFailedapppendStreamFilter()
    {
        $csv = Writer::createFromFileObject(new SplTempFileObject());
        $this->assertFalse($csv->isActiveStreamFilter());
        $csv->appendStreamFilter('string.toupper');
    }

    /**
     * @expectedException OutOfBoundsException
     * @expectedExceptionMessage the $mode should be a valid `STREAM_FILTER_*` constant
     */
    public function testaddMultipleStreamFilter()
    {
        $csv = Reader::createFromPath(__DIR__.'/foo.csv');
        $csv->setFlags(SplFileObject::READ_AHEAD|SplFileObject::SKIP_EMPTY);
        $csv->appendStreamFilter('string.tolower');
        $csv->prependStreamFilter('string.rot13');
        $csv->appendStreamFilter('string.toupper');
        $this->assertTrue($csv->hasStreamFilter('string.tolower'));
        $csv->removeStreamFilter('string.tolower');
        $this->assertFalse($csv->hasStreamFilter('string.tolower'));

        foreach ($csv->getIterator() as $row) {
            $this->assertSame($row, ['WBUA', 'QBR', 'WBUA.QBR@RKNZCYR.PBZ']);
        }
        $csv->clearStreamFilter();
        $this->assertFalse($csv->hasStreamFilter('string.rot13'));

        $csv->appendStreamFilter('string.toupper');
        $this->assertSame(STREAM_FILTER_READ, $csv->getStreamFilterMode());
        $csv->setStreamFilterMode(STREAM_FILTER_WRITE);
        $this->assertSame(STREAM_FILTER_WRITE, $csv->getStreamFilterMode());
        foreach ($csv->getIterator() as $row) {
            $this->assertSame($row, ['john', 'doe', 'john.doe@example.com']);
        }
        $csv->setStreamFilterMode(34);
    }

    public function testGetFilterPath()
    {
        $csv = Writer::createFromPath(__DIR__.'/foo.csv');
        $csv->appendStreamFilter('string.rot13');
        $csv->prependStreamFilter('string.toupper');
        $this->assertFalse($csv->getIterator()->getRealPath());
    }
}
