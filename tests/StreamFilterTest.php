<?php

namespace LeagueTest\Csv;

use League\Csv\Reader;
use League\Csv\Writer;
use LogicException;
use PHPUnit\Framework\TestCase;
use SplTempFileObject;

/**
 * @group stream
 * @group csv
 */
class StreamFilterTest extends TestCase
{
    public function testAddStreamFilter()
    {
        $csv = Reader::createFromPath(__DIR__.'/data/foo.csv');
        $csv->addStreamFilter('string.rot13');
        $csv->addStreamFilter('string.tolower');
        $csv->addStreamFilter('string.toupper');
        foreach ($csv as $row) {
            $this->assertSame($row, ['WBUA', 'QBR', 'WBUA.QBR@RKNZCYR.PBZ']);
        }
    }

    public function testFailedAddStreamFilter()
    {
        $this->expectException(LogicException::class);
        $csv = Writer::createFromFileObject(new SplTempFileObject());
        $this->assertFalse($csv->isStream());
        $csv->addStreamFilter('string.toupper');
    }

    public function testStreamFilterDetection()
    {
        $filtername = 'string.toupper';
        $csv = Reader::createFromPath(__DIR__.'/data/foo.csv');
        $this->assertFalse($csv->hasStreamFilter($filtername));
        $csv->addStreamFilter($filtername);
        $this->assertTrue($csv->hasStreamFilter($filtername));
    }

    public function testClearAttachedStreamFilters()
    {
        $path = __DIR__.'/data/foo.csv';
        $csv = Reader::createFromPath($path);
        $csv->addStreamFilter('string.toupper');
        $this->assertContains('JOHN', (string) $csv);
        $csv = Reader::createFromPath($path);
        $this->assertNotContains('JOHN', (string) $csv);
    }

    public function testRemoveStreamFilters()
    {
        $csv = Reader::createFromPath(__DIR__.'/data/foo.csv');
        $this->assertFalse($csv->hasStreamFilter('string.tolower'));
    }

    public function testSetStreamFilterWriterNewLine()
    {
        stream_filter_register(FilterReplace::FILTER_NAME.'*', FilterReplace::class);
        $csv = Writer::createFromPath(__DIR__.'/data/newline.csv');
        $csv->addStreamFilter(FilterReplace::FILTER_NAME."\r\n:\n");
        $csv->insertOne([1, 'two', 3, "new\r\nline"]);
        $this->assertContains("1,two,3,\"new\nline\"", (string) $csv);
    }
}
