<?php

namespace League\Csv\Test;

use League\Csv\Reader;
use League\Csv\Writer;
use lib\FilterReplace;
use SplFileObject;
use SplTempFileObject;

/**
 * @group csv
 */
class StreamFilterTest extends AbstractTestCase
{
    public function testInitStreamFilterWithWriterStream()
    {
        $filter = 'php://filter/write=string.rot13/resource='.__DIR__.'/data/foo.csv';
        $csv = Reader::createFromPath($filter);
        $this->assertTrue($csv->hasStreamFilter('string.rot13'));
        $this->assertSame(STREAM_FILTER_WRITE, $csv->getStreamFilterMode());
    }

    public function testInitStreamFilterWithReaderStream()
    {
        $filter = 'php://filter/read=string.toupper/resource='.__DIR__.'/data/foo.csv';
        $csv = Reader::createFromPath($filter);
        $this->assertTrue($csv->hasStreamFilter('string.toupper'));
        $this->assertSame(STREAM_FILTER_READ, $csv->getStreamFilterMode());
    }

    public function testInitStreamFilterWithBothStream()
    {
        $filter = 'php://filter/string.toupper/resource='.__DIR__.'/data/foo.csv';
        $csv = Reader::createFromPath($filter);
        $this->assertTrue($csv->hasStreamFilter('string.toupper'));
        $this->assertSame(STREAM_FILTER_ALL, $csv->getStreamFilterMode());
    }

    /**
     * @expectedException LogicException
     */
    public function testInitStreamFilterWithSplFileObject()
    {
        Reader::createFromFileObject(new SplFileObject(__DIR__.'/data/foo.csv'))->getStreamFilterMode();
    }

    public function testappendStreamFilter()
    {
        $csv = Reader::createFromPath(__DIR__.'/data/foo.csv');
        $csv->appendStreamFilter('string.toupper');
        foreach ($csv->getIterator() as $row) {
            $this->assertSame($row, ['JOHN', 'DOE', 'JOHN.DOE@EXAMPLE.COM']);
        }
    }

    /**
     * @expectedException LogicException
     */
    public function testFailedprependStreamFilter()
    {
        $csv = Reader::createFromFileObject(new SplTempFileObject());
        $this->assertFalse($csv->isActiveStreamFilter());
        $csv->prependStreamFilter('string.toupper');
    }

    /**
     * @expectedException LogicException
     */
    public function testFailedapppendStreamFilter()
    {
        $csv = Writer::createFromFileObject(new SplTempFileObject());
        $this->assertFalse($csv->isActiveStreamFilter());
        $csv->appendStreamFilter('string.toupper');
    }

    /**
     * @expectedException OutOfBoundsException
     */
    public function testaddMultipleStreamFilter()
    {
        $csv = Reader::createFromPath(__DIR__.'/data/foo.csv');
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
        $csv = Writer::createFromPath(__DIR__.'/data/foo.csv');
        $csv->appendStreamFilter('string.rot13');
        $csv->prependStreamFilter('string.toupper');
        $this->assertFalse($csv->getIterator()->getRealPath());
    }

    public function testGetFilterPathWithAllStream()
    {
        $filter = 'php://filter/string.toupper/resource='.__DIR__.'/data/foo.csv';
        $csv = Reader::createFromPath($filter);
        $this->assertFalse($csv->getIterator()->getRealPath());
    }

    public function testSetStreamFilterWriterNewLine()
    {
        stream_filter_register(FilterReplace::FILTER_NAME.'*', '\lib\FilterReplace');
        $csv = Writer::createFromPath(__DIR__.'/data/newline.csv');
        $csv->appendStreamFilter(FilterReplace::FILTER_NAME."\r\n:\n");
        $this->assertTrue($csv->hasStreamFilter(FilterReplace::FILTER_NAME."\r\n:\n"));
        $csv->insertOne([1, 'two', 3, "new\r\nline"]);
    }
}
