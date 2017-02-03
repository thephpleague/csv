<?php

namespace LeagueTest\Csv;

use League\Csv\Reader;
use League\Csv\Writer;
use PHPUnit_Framework_TestCase;
use SplTempFileObject;

/**
 * @group stream
 * @group csv
 */
class StreamFilterTest extends PHPUnit_Framework_TestCase
{
    public function testappendStreamFilter()
    {
        $csv = Reader::createFromPath(__DIR__.'/data/foo.csv');
        $csv->appendStreamFilter('string.toupper');
        foreach ($csv->getIterator() as $row) {
            $this->assertSame($row, ['JOHN', 'DOE', 'JOHN.DOE@EXAMPLE.COM']);
        }
    }

    public function testStreamFilterDetection()
    {
        $filtername = 'string.toupper';
        $csv = Reader::createFromPath(__DIR__.'/data/foo.csv');
        $this->assertFalse($csv->hasStreamFilter($filtername));
        $csv->appendStreamFilter($filtername);
        $this->assertTrue($csv->hasStreamFilter($filtername));
    }

    /**
     * @expectedException LogicException
     */
    public function testFailPrependStreamFilter()
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

    public function testRemoveStreamFilterAlwaysWorks()
    {
        $csv = Writer::createFromFileObject(new SplTempFileObject());
        $csv->removeStreamFilter('string.toupper');
    }

    public function testClearAttachedStreamFilters()
    {
        $csv = Reader::createFromPath(__DIR__.'/data/foo.csv');
        $csv->appendStreamFilter('string.tolower');
        $csv->appendStreamFilter('string.rot13');
        $csv->appendStreamFilter('string.toupper');
        $csv->clearStreamFilter();
        $this->assertNotContains('JOHN', (string) $csv);
    }

    public function testAddMultipleStreamFilter()
    {
        $csv = Reader::createFromPath(__DIR__.'/data/foo.csv');
        $csv->appendStreamFilter('string.tolower');
        $csv->prependStreamFilter('string.rot13');
        $csv->appendStreamFilter('string.toupper');
        foreach ($csv as $row) {
            $this->assertSame($row, ['WBUA', 'QBR', 'WBUA.QBR@RKNZCYR.PBZ']);
        }
    }

    public function testSetStreamFilterWriterNewLine()
    {
        stream_filter_register(FilterReplace::FILTER_NAME.'*', FilterReplace::class);
        $csv = Writer::createFromPath(__DIR__.'/data/newline.csv');
        $csv->appendStreamFilter(FilterReplace::FILTER_NAME."\r\n:\n");
        $csv->insertOne([1, 'two', 3, "new\r\nline"]);
    }

    public function testUrlEncodeFilterParameters()
    {
        $csv = Reader::createFromPath(__DIR__.'/data/foo.csv');
        $csv->appendStreamFilter('convert.iconv.UTF-8/ASCII//TRANSLIT');
        $this->assertCount(1, $csv->fetchAll());
    }
}