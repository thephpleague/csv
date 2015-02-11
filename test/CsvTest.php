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

    public function testJsonSerialize()
    {
        $this->csv->setFlags(SplFileObject::READ_AHEAD|SplFileObject::SKIP_EMPTY);
        $this->assertSame(json_encode($this->expected), json_encode($this->csv));
    }

    /**
     * @param $rawCsv
     *
     * @dataProvider getIso8859Csv
     */
    public function testJsonSerializeAffectedByReaderOptions($rawCsv)
    {
        $csv = Reader::createFromString($rawCsv);
        $csv->setEncodingFrom('iso-8859-15');
        $csv->setOffset(799);
        $csv->setLimit(50);
        json_encode($csv);
        $this->assertEquals(JSON_ERROR_NONE, json_last_error());
    }

    public static function getIso8859Csv()
    {
        return [[file_get_contents(__DIR__.'/data/prenoms.csv')]];
    }

    /**
     * @runInSeparateProcess
     */
    public function testOutput()
    {
        if (defined('HHVM_VERSION')) {
            $this->markTestSkipped();
        }
        $this->assertSame(60, $this->csv->output("test.csv"));
        $headers = \xdebug_get_headers();
        $this->assertSame($headers[0], "Content-Type: application/octet-stream");
        $this->assertSame($headers[1], "Content-Transfer-Encoding: binary");
        $this->assertSame($headers[2], "Content-Disposition: attachment; filename=\"test.csv\"");
    }

    /**
     * @expectedException PHPUnit_Framework_Error
     */
    public function testFailedOutput()
    {
        $this->csv->output(new DateTime);
    }

    public function testToString()
    {
        $expected = "john,doe,john.doe@example.com\njane,doe,jane.doe@example.com\n";
        $this->assertSame($expected, $this->csv->__toString());
    }
}
