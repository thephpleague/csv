<?php

namespace LeagueTest\Csv;

use League\Csv\Exception\OutOfRangeException;
use League\Csv\Exception\RuntimeException;
use League\Csv\Reader;
use League\Csv\Statement;
use PHPUnit\Framework\TestCase;
use SplTempFileObject;

/**
 * @group reader
 */
class ReaderTest extends TestCase
{
    private $csv;

    private $expected = [
        ['john', 'doe', 'john.doe@example.com', '0123456789'],
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

    public function testGetIterator()
    {
        $this->csv->setHeaderOffset(0);
        foreach ($this->csv as $record) {
            $this->assertCount(4, $record);
        }
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

    public function testSelect()
    {
        $stmt = new Statement();
        $this->assertEquals($stmt->process($this->csv), $this->csv->select($stmt));
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
        $this->expectException(OutOfRangeException::class);
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

    public function testDuplicateHeaderValueTriggersException()
    {
        $csv = Reader::createFromString(
            'field1,field1,field3
            1,2,3
            4,5,6'
        );
        $csv->setHeaderOffset(0);
        $this->assertSame(['field1', 'field1', 'field3'], $csv->getHeader());
        $this->expectException(RuntimeException::class);
        iterator_to_array($csv, true);
    }

    /**
     * @param array  $records
     * @param array  $expected
     * @param string $expected_bom
     * @dataProvider validBOMSequences
     */
    public function testStripBOM($records, $expected, $expected_bom)
    {
        $fp = fopen('php://temp', 'r+');
        foreach ($records as $record) {
            fputcsv($fp, $record);
        }
        $csv = Reader::createFromStream($fp);
        $this->assertSame($expected_bom, $csv->getInputBOM());
        $this->assertSame($expected, (new Statement())->process($csv)->fetchAll()[0][0]);
    }

    public function validBOMSequences()
    {
        return [
            'withBOM' => [[
                [Reader::BOM_UTF16_LE.'john', 'doe', 'john.doe@example.com'],
                ['jane', 'doe', 'jane.doe@example.com'],
            ], 'john', Reader::BOM_UTF16_LE],
            'withDoubleBOM' =>  [[
                [Reader::BOM_UTF16_LE.Reader::BOM_UTF16_LE.'john', 'doe', 'john.doe@example.com'],
                ['jane', 'doe', 'jane.doe@example.com'],
            ], Reader::BOM_UTF16_LE.'john', Reader::BOM_UTF16_LE],
            'withoutBOM' => [[
                ['john', 'doe', 'john.doe@example.com'],
                ['jane', 'doe', 'jane.doe@example.com'],
            ], 'john', ''],
        ];
    }
}
