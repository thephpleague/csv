<?php

namespace LeagueTest\Csv;

use League\Csv\Exception\OutOfRangeException;
use League\Csv\Exception\RuntimeException;
use League\Csv\Reader;
use League\Csv\Statement;
use PHPUnit\Framework\TestCase;
use SplFileObject;
use SplTempFileObject;

/**
 * @group reader
 * @coversDefaultClass League\Csv\Reader
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

    /**
     * @covers ::resetProperties
     * @covers ::getIterator
     * @covers ::getRecords
     * @covers ::supportsHeaderAsRecordKeys
     * @covers ::combineHeader
     * @covers League\Csv\MapIterator
     */
    public function testGetIterator()
    {
        $this->csv->setHeaderOffset(0);
        foreach ($this->csv as $record) {
            $this->assertCount(4, $record);
        }

        $this->csv->setHeaderOffset(null);
        foreach ($this->csv->getRecords() as $record) {
            $this->assertInternalType('array', $record);
        }
    }

    /**
     * @covers ::setHeaderOffset
     * @covers ::getHeaderOffset
     * @covers ::getHeader
     * @covers ::setHeader
     */
    public function testGetHeader()
    {
        $this->csv->setHeaderOffset(1);
        $this->assertSame(1, $this->csv->getHeaderOffset());
        $this->assertSame($this->expected[1], $this->csv->getHeader());
        $this->csv->setHeaderOffset(null);
        $this->assertNull($this->csv->getHeaderOffset());
        $this->assertSame([], $this->csv->getHeader());
    }

    /**
     * @covers ::select
     * @covers League\Csv\Statement
     * @covers League\Csv\ResultSet
     */
    public function testSelect()
    {
        $stmt = new Statement();
        $this->assertEquals($stmt->process($this->csv), $this->csv->select($stmt));
    }

    /**
     * @covers ::fetchDelimitersOccurrence
     * @covers League\Csv\ValidatorTrait
     */
    public function testDetectDelimiterListWithInvalidRowLimit()
    {
        $this->expectException(OutOfRangeException::class);
        $this->csv->fetchDelimitersOccurrence([','], -4);
    }

    /**
     * @covers ::fetchDelimitersOccurrence
     */
    public function testDetectDelimiterListWithNoCSV()
    {
        $file = new SplTempFileObject();
        $file->fwrite("How are you today ?\nI'm doing fine thanks!");
        $csv = Reader::createFromFileObject($file);
        $this->assertSame(['|' => 0], $csv->fetchDelimitersOccurrence(['toto', '|'], 5));
    }

    /**
     * @covers ::fetchDelimitersOccurrence
     * @covers ::getCellCount
     */
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

    /**
     * @covers ::getHeader
     * @covers ::getIterator
     * @covers ::setHeader
     */
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
     * @covers ::getIterator
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

    /**
     * @covers ::getIterator
     * @dataProvider appliedFlagsProvider
     * @param int $flag
     * @param int $fetch_count
     */
    public function testAppliedFlags($flag, $fetch_count)
    {
        $path = __DIR__.'/data/tmp.txt';
        $obj  = new SplFileObject($path, 'w+');
        $obj->fwrite("1st\n2nd\n");
        $obj->setFlags($flag);
        $reader = Reader::createFromFileObject($obj);
        $this->assertCount($fetch_count, $reader);
        $reader = null;
        $obj = null;
        unlink($path);
    }

    public function appliedFlagsProvider()
    {
        return [
            'NONE' => [0, 2],
            'DROP_NEW_LINE' => [SplFileObject::READ_AHEAD | SplFileObject::DROP_NEW_LINE, 2],
            'READ_AHEAD' => [SplFileObject::READ_AHEAD, 2],
            'SKIP_EMPTY' => [SplFileObject::SKIP_EMPTY, 2],
            'READ_AHEAD|DROP_NEW_LINE' => [SplFileObject::READ_AHEAD | SplFileObject::DROP_NEW_LINE, 2],
            'READ_AHEAD|SKIP_EMPTY' => [SplFileObject::READ_AHEAD | SplFileObject::SKIP_EMPTY, 2],
            'DROP_NEW_LINE|SKIP_EMPTY' => [SplFileObject::DROP_NEW_LINE | SplFileObject::SKIP_EMPTY, 2],
            'READ_AHEAD|DROP_NEW_LINE|SKIP_EMPTY' => [SplFileObject::READ_AHEAD | SplFileObject::DROP_NEW_LINE | SplFileObject::SKIP_EMPTY, 2],
        ];
    }

    /**
     * @covers ::stripBOM
     * @covers ::removeBOM
     */
    public function testStripBOMWithEnclosure()
    {
        $expected = ['parent name', 'parentA'];
        $source = Reader::BOM_UTF8.'"parent name","child name","title"
            "parentA","childA","titleA"';
        $csv = Reader::createFromString($source);
        $csv->setHeaderOffset(0);
        $expected = [
            0 => ['parent name' => 'parentA', 'child name' => 'childA', 'title' => 'titleA'],
        ];
        $this->assertSame($expected, (new Statement())->process($csv)->fetchAll());
    }


    /**
     * @covers ::stripBOM
     * @covers ::removeBOM
     * @covers League\Csv\StreamIterator
     */
    public function testStripNoBOM()
    {
        $expected = ['parent name', 'parentA'];
        $source = '"parent name","child name","title"
            "parentA","childA","titleA"';
        $csv = Reader::createFromString($source);
        $csv->setHeaderOffset(0);
        $expected = [
            0 => ['parent name' => 'parentA', 'child name' => 'childA', 'title' => 'titleA'],
        ];
        $this->assertSame($expected, (new Statement())->process($csv)->fetchAll());
        foreach ($csv->getRecords() as $offset => $record) {
            $this->assertInternalType('int', $offset);
            $this->assertInternalType('array', $record);
        }
    }

    /**
     * @covers ::getRecords
     * @covers ::setHeader
     * @covers ::supportsHeaderAsRecordKeys
     */
    public function testFetchAssocWithUnknownOffset()
    {
        $this->expectException(RuntimeException::class);
        $this->csv->setHeaderOffset(23)->getRecords();
    }
}
