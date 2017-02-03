<?php

namespace LeagueTest\Csv;

use Countable;
use DOMDocument;
use IteratorAggregate;
use JsonSerializable;
use League\Csv\Exception;
use League\Csv\Reader;
use PHPUnit\Framework\TestCase;
use SplTempFileObject;

/**
 * @group reader
 */
class ReaderTest extends TestCase
{
    private $csv;

    private $expected = [
        ['john', 'doe', 'john.doe@example.com'],
        ['jane', 'doe', 'jane.doe@example.com'],
    ];

    public function setUp()
    {
        $tmp = new SplTempFileObject();
        foreach ($this->expected as $row) {
            $tmp->fputcsv($row);
        }

        $this->csv = Reader::createFromFileObject($tmp);
    }

    public function tearDown()
    {
        $this->csv = null;
    }

    public function testCountable()
    {
        $this->assertInstanceOf(Countable::class, $this->csv);
        $this->assertCount(2, $this->csv);
    }

    public function testInterface()
    {
        $this->assertInstanceOf(IteratorAggregate::class, $this->csv);
    }

    public function testToHTML()
    {
        $this->assertContains('<table', $this->csv->toHTML());
    }

    public function testGetHeader()
    {
        $this->csv->setHeaderOffset(1);
        $this->assertSame($this->expected[1], $this->csv->getHeader());
    }

    public function testToXML()
    {
        $this->assertInstanceOf(DOMDocument::class, $this->csv->toXML());
    }

    public function testJsonSerialize()
    {
        $this->assertInstanceOf(JsonSerializable::class, $this->csv);
        $this->assertSame($this->expected, json_decode(json_encode($this->csv), true));
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

    public function testFetchColumn()
    {
        $this->assertContains('john', $this->csv->fetchColumn(0));
        $this->assertContains('jane', $this->csv->fetchColumn());
    }

    public function testDetectDelimiterListWithInvalidRowLimit()
    {
        $this->expectException(Exception::class);
        $this->csv->fetchDelimitersOccurrence([','], -4);
    }

    public function testDetectDelimiterListWithNoCSV()
    {
        $file = new SplTempFileObject();
        $file->fwrite("How are you today ?\nI'm doing fine thanks!");
        $csv = Reader::createFromFileObject($file);
        $this->assertSame(['|' => 0], $csv->fetchDelimitersOccurrence(['toto', '|'], 5));
    }

    public function testDetectDelimiterListWithInconsistentCSV()
    {
        $data = new SplTempFileObject();
        $data->setCsvControl(';');
        $data->fputcsv(['toto', 'tata', 'tutu']);
        $data->setCsvControl('|');
        $data->fputcsv(['toto', 'tata', 'tutu']);
        $data->fputcsv(['toto', 'tata', 'tutu']);
        $data->fputcsv(['toto', 'tata', 'tutu']);

        $csv = Reader::createFromFileObject($data);
        $this->assertSame(['|' => 12, ';' => 4], $csv->fetchDelimitersOccurrence(['|', ';'], 5));
    }
}
