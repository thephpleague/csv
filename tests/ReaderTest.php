<?php

/**
 * League.Csv (https://csv.thephpleague.com).
 *
 * @author  Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @license https://github.com/thephpleague/csv/blob/master/LICENSE (MIT License)
 * @version 9.2.0
 * @link    https://github.com/thephpleague/csv
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace LeagueTest\Csv;

use BadMethodCallException;
use League\Csv\Exception;
use League\Csv\Reader;
use League\Csv\Statement;
use PHPUnit\Framework\TestCase;
use SplFileObject;
use SplTempFileObject;
use TypeError;
use function array_keys;
use function count;
use function fclose;
use function fopen;
use function fputcsv;
use function in_array;
use function iterator_to_array;
use function json_encode;
use function unlink;

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
        self::assertCount(2, $csv);
        $csv->setHeaderOffset(0);
        self::assertCount(1, $csv);
    }

    /**
     * @covers ::getDocument
     */
    public function testReaderWithEmptyEscapeChar1()
    {
        $source = <<<EOF
Year,Make,Model,Description,Price
1997,Ford,E350,"ac, abs, moon",3000.00
1999,Chevy,"Venture ""Extenéded Edition""","",4900.00
1999,Chevy,"Venture ""Extended Edition, Very Large""",,5000.00
1996,Jeep,Grand Cherokee,"MUST SELL!
air, moon roof, loaded",4799.00
EOF;
        $csv = Reader::createFromString($source);
        $csv->setEscape('');
        self::assertCount(5, $csv);
        $csv->setHeaderOffset(0);
        self::assertCount(4, $csv);
    }

    /**
     * @covers ::getDocument
     */
    public function testReaderWithEmptyEscapeChar2()
    {
        $source = '"parent name","child name","title"
            "parentA","childA","titleA"';
        $csv = Reader::createFromString($source);
        $csv->setEscape('');
        self::assertCount(2, $csv);
        $csv->setHeaderOffset(0);
        self::assertCount(1, $csv);
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
            self::assertCount(4, $record);
        }

        $this->csv->setHeaderOffset(1);
        foreach ($this->csv as $record) {
            self::assertCount(3, $record);
        }

        $this->csv->setHeaderOffset(null);
        foreach ($this->csv->getRecords() as $record) {
            self::assertTrue(in_array(count($record), [3, 4], true));
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
            self::assertSame(['jane', 'doe', 'jane.doe@example.com'], array_keys($record));
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
        self::assertSame(1, $this->csv->getHeaderOffset());
        self::assertSame($this->expected[1], $this->csv->getHeader());
        $this->csv->setHeaderOffset(null);
        self::assertNull($this->csv->getHeaderOffset());
        self::assertSame([], $this->csv->getHeader());
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
        self::assertEquals($csv->fetchOne(3), $res->fetchOne(3));
        self::assertEquals($csv->fetchColumn('firstname'), $res->fetchColumn('firstname'));
        self::assertEquals($csv->fetchPairs('lastname', 0), $res->fetchPairs('lastname', 0));
    }

    /**
     * @covers ::__call
     *
     * @param string $method
     * @dataProvider invalidMethodCallMethodProvider
     */
    public function testCallThrowsException($method)
    {
        self::expectException(BadMethodCallException::class);
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
        self::expectException(Exception::class);
        $csv = Reader::createFromString(
            'field1,field1,field3
            1,2,3
            4,5,6'
        );
        $csv->setHeaderOffset(0);
        self::assertSame(['field1', 'field1', 'field3'], $csv->getHeader());
        iterator_to_array($csv);
    }


    /**
     * @covers ::getHeader
     * @covers ::seekRow
     * @covers ::setHeaderOffset
     * @covers League\Csv\Exception
     */
    public function testHeaderThrowsExceptionOnEmptyLine()
    {
        self::expectException(Exception::class);
        $str = <<<EOF
foo,bar,baz


1,2,3
EOF;
        $csv = Reader::createFromString($str);
        $csv->setHeaderOffset(2);
        $csv->getHeader();
    }



    /**
     * @covers ::stripBOM
     * @covers ::removeBOM
     * @covers ::combineHeader
     * @covers League\Csv\Stream
     * @dataProvider validBOMSequences
     */
    public function testStripBOM(array $record, string $expected_bom, string $expected)
    {
        $fp = fopen('php://temp', 'r+');
        fputcsv($fp, $record);
        $csv = Reader::createFromStream($fp);
        self::assertSame($expected_bom, $csv->getInputBOM());
        foreach ($csv as $offset => $record) {
            self::assertSame($expected, $record[0]);
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
            self::assertSame($expected, $record);
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
            self::assertSame($expected, $record);
        }
    }

    /**
     * @covers ::getIterator
     * @dataProvider appliedFlagsProvider
     */
    public function testAppliedFlags(int $flag, int $fetch_count)
    {
        $path = __DIR__.'/data/tmp.txt';
        $obj  = new SplFileObject($path, 'w+');
        $obj->fwrite("1st\n2nd\n");
        $obj->setFlags($flag);
        $reader = Reader::createFromFileObject($obj);
        self::assertCount($fetch_count, $reader);
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
        self::expectException(Exception::class);
        $this->csv->setHeaderOffset(-3)->getRecords();
    }

    /**
     * @covers ::setHeader
     * @covers ::seekRow
     */
    public function testGetHeaderThrowsExceptionWithSplFileObject()
    {
        self::expectException(Exception::class);
        $this->csv->setHeaderOffset(23)->getRecords();
    }

    /**
     * @covers ::setHeader
     * @covers ::seekRow
     */
    public function testGetHeaderThrowsExceptionWithStreamObject()
    {
        self::expectException(Exception::class);

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
        self::expectException(TypeError::class);
        $this->csv->setHeaderOffset((object) 1);
    }

    /**
     * @covers ::setHeaderOffset
     */
    public function testSetHeaderThrowsExceptionOnWrongInputRange()
    {
        self::expectException(Exception::class);
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
            self::assertSame($keys, array_keys($record));
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
        self::assertSame(
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
        self::assertCount(1, $csv);
    }
}
