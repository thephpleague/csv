<?php

namespace League\Csv\test;

use SplFileInfo;
use SplFileObject;
use SplTempFileObject;
use PHPUnit_Framework_TestCase;
use DateTime;
use League\Csv\Reader;
use League\Csv\Writer;

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
        $csv = new SplTempFileObject;
        foreach ($this->expected as $row) {
            $csv->fputcsv($row);
        }

        $this->csv = new Reader($csv);
    }

    public function tearDown()
    {
        $this->csv = null;
    }

    public function testConstructorWithFilePath()
    {
        $path = __DIR__.'/foo.csv';

        $csv = new Reader($path);
        $this->assertSame($path, $csv->getIterator()->getRealPath());
    }

    public function testConstructorWithFileObject()
    {
        $path = __DIR__.'/foo.csv';

        $csv = new Reader(new SplFileInfo($path));
        $this->assertSame($path, $csv->getIterator()->getRealPath());
    }

    public function testContructorWithPHPWrapper()
    {
        $path = __DIR__.'/foo.csv';

        $csv = new Reader('php://filter/read=string.toupper/resource='.$path);
        $this->assertFalse($csv->getIterator()->getRealPath());
    }

    /**
     * @expectedException RuntimeException
     */
    public function testConstructorWithNotWritablePath()
    {
        (new Reader('/usr/bin/foo.csv'))->getIterator();
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testConstructorWithWrongType()
    {
        new Reader(['/usr/bin/foo.csv']);
    }

    public function testCreateFromPath()
    {
        $path = __DIR__.'/foo.csv';

        $csv = Reader::createFromPath(new SplFileInfo($path));
        $this->assertSame($path, $csv->getIterator()->getRealPath());
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testFailedCreateFromPath()
    {
        Reader::createFromPath(new SplTempFileObject);
    }

    public function testCreateFromString()
    {
        $expected = "john,doe,john.doe@example.com".PHP_EOL
            ."jane,doe,jane.doe@example.com".PHP_EOL;
        $reader = Reader::createFromString($expected);
        $this->assertSame($reader->fetchOne(0), ['john', 'doe', 'john.doe@example.com']);
        $this->assertSame($reader->fetchOne(1), ['jane', 'doe', 'jane.doe@example.com']);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testDelimeter()
    {
        $this->csv->setDelimiter('o');
        $this->assertSame('o', $this->csv->getDelimiter());

        $this->csv->setDelimiter('foo');
    }

    public function testDetectDelimiter()
    {
        $this->assertSame($this->csv->detectDelimiter(), ',');
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testDetectDelimiterWithInvalidRowLimit()
    {
        $this->csv->detectDelimiter(-4);
    }

    public function testDetectDelimiterWithNoCSV()
    {
        $file = new SplTempFileObject;
        $file->fwrite("How are you today ?\nI'm doing fine thanks!");
        $csv = new Writer($file);
        $this->assertNull($csv->detectDelimiter(5, ['toto', '|']));
    }

    /**
     * @expectedException RuntimeException
     */
    public function testDetectDelimiterWithInconsistentCSV()
    {
        $csv = new Writer(new SplTempFileObject);
        $csv->setDelimiter(';');
        $csv->insertOne(['toto', 'tata', 'tutu']);
        $csv->setDelimiter('|');
        $csv->insertAll([
            ['toto', 'tata', 'tutu'],
            ['toto', 'tata', 'tutu'],
            ['toto', 'tata', 'tutu']
        ]);

        $csv->detectDelimiter(5, ['toto', '|']);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testEscape()
    {
        $this->csv->setEscape('o');
        $this->assertSame('o', $this->csv->getEscape());

        $this->csv->setEscape('foo');
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testEnclosure()
    {
        $this->csv->setEnclosure('o');
        $this->assertSame('o', $this->csv->getEnclosure());

        $this->csv->setEnclosure('foo');
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testFailCreateFromString()
    {
        Reader::createFromString(new DateTime);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testEncoding()
    {
        $expected = 'iso-8859-15';
        $this->csv->setEncoding($expected);
        $this->assertSame(strtoupper($expected), $this->csv->getEncoding());

        $this->csv->setEncoding('');
    }

    public function testToString()
    {
        $expected = "john,doe,john.doe@example.com".PHP_EOL
            ."jane,doe,jane.doe@example.com".PHP_EOL;
        $this->assertSame($expected, $this->csv->__toString());
    }

    public function testIterator()
    {
        foreach ($this->csv as $key => $row) {
            $this->assertSame($this->expected[$key], $row);
        }
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testSetFlags()
    {
        $this->csv->setFlags(SplFileObject::SKIP_EMPTY);
        $this->assertSame(SplFileObject::SKIP_EMPTY, $this->csv->getFlags() & SplFileObject::SKIP_EMPTY);
        $this->assertSame(SplFileObject::READ_CSV, $this->csv->getFlags() & SplFileObject::READ_CSV);

        $this->csv->setFlags(-3);
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
        $csv = new Reader(new SplFileInfo($filter));
        $this->assertTrue($csv->hasStreamFilter('string.rot13'));
        $this->assertSame(STREAM_FILTER_WRITE, $csv->getStreamFilterMode());

        $filter = 'php://filter/read=string.toupper/resource='.__DIR__.'/foo.csv';
        $csv = new Reader(new SplFileInfo($filter));
        $this->assertTrue($csv->hasStreamFilter('string.toupper'));
        $this->assertSame(STREAM_FILTER_READ, $csv->getStreamFilterMode());
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testappendStreamFilter()
    {
        $path = __DIR__.'/foo.csv';
        $csv = new Reader(new SplFileInfo($path));
        $csv->appendStreamFilter('string.toupper');
        foreach ($csv->getIterator() as $row) {
            $this->assertSame($row, ['JOHN', 'DOE', 'JOHN.DOE@EXAMPLE.COM']);
        }
        $csv->appendStreamFilter(new DateTime);
    }

    /**
     * @expectedException RuntimeException
     */
    public function testprependStreamFilter()
    {
        (new Reader(new SplTempFileObject))->prependStreamFilter('string.toupper');
    }

    /**
     * @expectedException OutOfBoundsException
     */
    public function testaddMultipleStreamFilter()
    {
        $path = __DIR__.'/foo.csv';
        $csv = new Reader(new SplFileInfo($path));
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

    /**
     * @expectedException RuntimeException
     */
    public function testGetFilterPath()
    {
        $path = __DIR__.'/foo.csv';
        $csv = new Writer(new SplFileInfo($path));
        $csv->appendStreamFilter('string.rot13');
        $csv->prependStreamFilter('string.toupper');
        $this->assertFalse($csv->getIterator()->getRealPath());

        (new Reader(new SplTempFileObject))->appendStreamFilter('string.rot13');
    }
}
