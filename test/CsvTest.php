<?php

namespace League\Csv\test;

use DateTime;
use League\Csv\Reader;
use League\Csv\Writer;
use PHPUnit_Framework_TestCase;
use SplFileInfo;
use SplFileObject;
use SplTempFileObject;

date_default_timezone_set('UTC');

/**
 * @group csv
 */
class CsvTest extends PHPUnit_Framework_TestCase
{
    private $csv;

    private $expected = [
        ['john', 'doe', 'john.doe@example.com'],
        ['jane','doe','jane.doe@example.com'],
    ];

    public function setUp()
    {
        $csv = new SplTempFileObject();
        foreach ($this->expected as $row) {
            $csv->fputcsv($row);
        }

        $this->csv = Reader::createFromFileObject($csv, "\n");
    }

    public function tearDown()
    {
        $this->csv = null;
    }

    public function testIterator()
    {
        $this->csv->setFlags(SplFileObject::READ_AHEAD|SplFileObject::SKIP_EMPTY);
        foreach ($this->csv as $key => $row) {
            $this->assertSame($this->expected[$key], $row);
        }
    }

    public function testToHTML()
    {
        $expected = <<<EOF
<table class="table-csv-data">
<tr>
<td>john</td>
<td>doe</td>
<td>john.doe@example.com</td>
</tr>
<tr>
<td>jane</td>
<td>doe</td>
<td>jane.doe@example.com</td>
</tr>
</table>
EOF;
        $this->csv->setFlags(SplFileObject::READ_AHEAD|SplFileObject::SKIP_EMPTY);
        $this->assertSame($expected, $this->csv->toHTML());
    }

    public function testToXML()
    {
        $expected = <<<EOF
<?xml version="1.0" encoding="UTF-8"?>
<csv>
  <row>
    <cell>john</cell>
    <cell>doe</cell>
    <cell>john.doe@example.com</cell>
  </row>
  <row>
    <cell>jane</cell>
    <cell>doe</cell>
    <cell>jane.doe@example.com</cell>
  </row>
</csv>

EOF;
        $this->csv->setFlags(SplFileObject::READ_AHEAD|SplFileObject::SKIP_EMPTY);
        $doc = $this->csv->toXML();
        $this->assertInstanceof('\DomDocument', $doc);
        $doc->formatOutput = true;
        $this->assertSame($expected, $doc->saveXML());
    }

    /**
     * @param $rawCsv
     *
     * @dataProvider getIso8859Csv
     */
    public function testJsonInterface($rawCsv)
    {
        $this->csv->setFlags(SplFileObject::READ_AHEAD|SplFileObject::SKIP_EMPTY);
        $this->assertSame(json_encode($this->expected), json_encode($this->csv));
        $csv = Reader::createFromString($rawCsv);
        $csv->setEncodingFrom('iso-8859-15');
        json_encode($csv);
        $this->assertEquals(JSON_ERROR_NONE, json_last_error());
    }

    public static function getIso8859Csv()
    {
        return [[file_get_contents(__DIR__.'/data/prenoms.csv')]];
    }

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
        (new Reader(new SplFileObject(__DIR__.'/foo.csv')))->getStreamFilterMode();
    }

    public function testappendStreamFilter()
    {
        $csv = new Reader(__DIR__.'/foo.csv');
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
        $csv = new Reader(new SplTempFileObject());
        $this->assertFalse($csv->isActiveStreamFilter());
        $csv->prependStreamFilter('string.toupper');
    }

    /**
     * @expectedException LogicException
     * @expectedExceptionMessage The stream filter API can not be used
     */
    public function testFailedapppendStreamFilter()
    {
        $csv = new Writer(new SplTempFileObject());
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
        $csv = new Writer(__DIR__.'/foo.csv');
        $csv->appendStreamFilter('string.rot13');
        $csv->prependStreamFilter('string.toupper');
        $this->assertFalse($csv->getIterator()->getRealPath());
    }
}
