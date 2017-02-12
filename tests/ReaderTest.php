<?php

namespace LeagueTest\Csv;

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

    public function testGetHeader()
    {
        $this->csv->setHeaderOffset(1);
        $this->assertSame(1, $this->csv->getHeaderOffset());
        $this->assertSame($this->expected[1], $this->csv->getHeader());
        $this->csv->setHeaderOffset(null);
        $this->assertNull($this->csv->getHeaderOffset());
        $this->assertSame([], $this->csv->getHeader());
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
