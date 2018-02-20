<?php

namespace LeagueTest\Csv;

use BadMethodCallException;
use League\Csv\Exception;
use League\Csv\Reader;
use League\Csv\Statement;
use PHPUnit\Framework\TestCase;
use SplFileObject;
use SplTempFileObject;
use TypeError;

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
     * @covers ::count
     * @covers ::computeHeader
     * @covers ::getRecords
     */
    public function testCountable()
    {
        $source = '"parent name","child name","title"
            "parentA","childA","titleA"';
        $csv = Reader::createFromString($source);
        $this->assertCount(2, $csv);
        $this->assertCount(2, $csv);
        $csv->setHeaderOffset(0);
        $this->assertCount(1, $csv);
    }

    /**
     * @covers ::resetProperties
     * @covers ::computeHeader
     * @covers ::getIterator
     * @covers ::getRecords
     * @covers ::combineHeader
     */
    public function testGetIterator()
    {
        $this->csv->setHeaderOffset(0);
        foreach ($this->csv as $record) {
            $this->assertCount(4, $record);
        }

        $this->csv->setHeaderOffset(1);
        foreach ($this->csv as $record) {
            $this->assertCount(3, $record);
        }

        $this->csv->setHeaderOffset(null);
        foreach ($this->csv->getRecords() as $record) {
            $this->assertTrue(in_array(count($record), [3, 4], true));
        }
    }

    /**
     * @covers ::computeHeader
     * @covers ::combineHeader
     */
    public function testCombineHeader()
    {
        $this->csv->setHeaderOffset(1);
        foreach ($this->csv as $record) {
            $this->assertSame(['jane', 'doe', 'jane.doe@example.com'], array_keys($record));
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
     * @covers ::__call
     */
    public function testCall()
    {
        $raw = [
            ['firstname', 'lastname'],
            ['john', 'doe'],
            ['lara', 'croft'],
            ['bruce', 'wayne'],
            ['clarck', 'kent'],
        ];

        $file = new SplTempFileObject();
        foreach ($raw as $row) {
            $file->fputcsv($row);
        }
        $csv = Reader::createFromFileObject($file);
        $csv->setHeaderOffset(0);

        $res = (new Statement())->process($csv);
        $this->assertEquals($csv->fetchOne(3), $res->fetchOne(3));
        $this->assertEquals($csv->fetchColumn('firstname'), $res->fetchColumn('firstname'));
        $this->assertEquals($csv->fetchPairs('lastname', 0), $res->fetchPairs('lastname', 0));
    }

    /**
     * @covers ::__call
     *
     * @param string $method
     * @dataProvider invalidMethodCallMethodProvider
     */
    public function testCallThrowsException($method)
    {
        $this->expectException(BadMethodCallException::class);
        $this->csv->$method();
    }

    public function invalidMethodCallMethodProvider()
    {
        return [
            'unknown method' => ['foo'],
            'ResultSet method not whitelisted' => ['isRecordOffsetPreserved'],
        ];
    }

    /**
     * @covers ::getHeader
     * @covers ::computeHeader
     * @covers ::getRecords
     * @covers ::setHeader
     * @covers League\Csv\Exception
     */
    public function testHeaderThrowsExceptionOnError()
    {
        $this->expectException(Exception::class);
        $csv = Reader::createFromString(
            'field1,field1,field3
            1,2,3
            4,5,6'
        );
        $csv->setHeaderOffset(0);
        $this->assertSame(['field1', 'field1', 'field3'], $csv->getHeader());
        iterator_to_array($csv);
    }

    /**
     * @covers ::stripBOM
     * @covers ::removeBOM
     * @covers ::combineHeader
     * @covers League\Csv\Stream
     * @dataProvider validBOMSequences
     * @param array  $record
     * @param string $expected_bom
     * @param string $expected
     */
    public function testStripBOM(array $record, string $expected_bom, string $expected)
    {
        $fp = fopen('php://temp', 'r+');
        fputcsv($fp, $record);
        $csv = Reader::createFromStream($fp);
        $this->assertSame($expected_bom, $csv->getInputBOM());
        foreach ($csv as $offset => $record) {
            $this->assertSame($expected, $record[0]);
        }
        $csv = null;
        fclose($fp);
        $fp = null;
    }

    public function validBOMSequences()
    {
        return [
            'withBOM' => [
                [Reader::BOM_UTF16_LE.'john', 'doe', 'john.doe@example.com'],
                Reader::BOM_UTF16_LE,
                'john',
            ],
            'withDoubleBOM' =>  [
                [Reader::BOM_UTF16_LE.Reader::BOM_UTF16_LE.'john', 'doe', 'john.doe@example.com'],
                Reader::BOM_UTF16_LE,
                Reader::BOM_UTF16_LE.'john',
            ],
            'withoutBOM' => [
                ['john', 'doe', 'john.doe@example.com'],
                '',
                'john',
            ],
        ];
    }

    /**
     * @covers ::stripBOM
     * @covers ::removeBOM
     * @covers ::combineHeader
     * @covers League\Csv\Stream
     */
    public function testStripBOMWithEnclosure()
    {
        $source = Reader::BOM_UTF8.'"parent name","child name","title"
            "parentA","childA","titleA"';
        $csv = Reader::createFromString($source);
        $csv->setHeaderOffset(0);
        $expected = ['parent name' => 'parentA', 'child name' => 'childA', 'title' => 'titleA'];
        foreach ($csv->getRecords() as $offset => $record) {
            $this->assertSame($expected, $record);
        }
    }

    /**
     * @covers ::stripBOM
     * @covers ::removeBOM
     * @covers ::combineHeader
     * @covers League\Csv\Stream
     */
    public function testStripNoBOM()
    {
        $source = '"parent name","child name","title"
            "parentA","childA","titleA"';
        $csv = Reader::createFromString($source);
        $csv->setHeaderOffset(0);
        $expected = ['parent name' => 'parentA', 'child name' => 'childA', 'title' => 'titleA'];
        foreach ($csv->getRecords() as $offset => $record) {
            $this->assertSame($expected, $record);
        }
    }

    /**
     * @covers ::getIterator
     * @dataProvider appliedFlagsProvider
     * @param int $flag
     * @param int $fetch_count
     */
    public function testAppliedFlags(int $flag, int $fetch_count)
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
     * @covers ::setHeader
     * @covers ::seekRow
     */
    public function testGetHeaderThrowsExceptionWithNegativeOffset()
    {
        $this->expectException(Exception::class);
        $this->csv->setHeaderOffset(-3)->getRecords();
    }

    /**
     * @covers ::setHeader
     * @covers ::seekRow
     */
    public function testGetHeaderThrowsExceptionWithSplFileObject()
    {
        $this->expectException(Exception::class);
        $this->csv->setHeaderOffset(23)->getRecords();
    }

    /**
     * @covers ::setHeader
     * @covers ::seekRow
     */
    public function testGetHeaderThrowsExceptionWithStreamObject()
    {
        $this->expectException(Exception::class);

        $tmp = fopen('php://temp', 'r+');
        foreach ($this->expected as $row) {
            fputcsv($tmp, $row);
        }

        $csv = Reader::createFromStream($tmp);
        $csv->setHeaderOffset(23)->getRecords();
    }

    /**
     * @covers ::setHeaderOffset
     * @covers \League\Csv\is_nullable_int
     */
    public function testSetHeaderThrowsExceptionOnWrongInput()
    {
        $this->expectException(TypeError::class);
        $this->csv->setHeaderOffset((object) 1);
    }

    /**
     * @covers ::setHeaderOffset
     */
    public function testSetHeaderThrowsExceptionOnWrongInputRange()
    {
        $this->expectException(Exception::class);
        $this->csv->setHeaderOffset(-1);
    }

    /**
     * @covers ::computeHeader
     */
    public function testMapRecordsFields()
    {
        $keys = ['firstname', 'lastname', 'email'];
        $res = $this->csv->getRecords($keys);
        foreach ($res as $record) {
            $this->assertSame($keys, array_keys($record));
        }
    }

    /**
     * @covers ::jsonSerialize
     */
    public function testJsonSerialize()
    {
        $expected = [
            ['First Name', 'Last Name', 'E-mail'],
            ['jane', 'doe', 'jane.doe@example.com'],
        ];

        $tmp = new SplTempFileObject();
        foreach ($expected as $row) {
            $tmp->fputcsv($row);
        }

        $reader = Reader::createFromFileObject($tmp)->setHeaderOffset(0);
        $this->assertSame(
            '[{"First Name":"jane","Last Name":"doe","E-mail":"jane.doe@example.com"}]',
            json_encode($reader)
        );
    }

    /**
     * @covers ::createFromPath
     */
    public function testCreateFromPath()
    {
        $csv = Reader::createFromPath(__DIR__.'/data/foo_readonly.csv');
        $this->assertCount(1, $csv);
    }
}
