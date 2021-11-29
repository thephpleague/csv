<?php

/**
 * League.Csv (https://csv.thephpleague.com)
 *
 * (c) Ignace Nyamagana Butera <nyamsprod@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace League\Csv;

use PHPUnit\Framework\TestCase;
use SplFileObject;
use SplTempFileObject;
use function array_keys;
use function count;
use function fclose;
use function fopen;
use function fputcsv;
use function iterator_to_array;
use function json_encode;
use function unlink;

/**
 * @group reader
 * @coversDefaultClass \League\Csv\Reader
 */
final class ReaderTest extends TestCase
{
    private Reader $csv;
    private array $expected = [
        ['john', 'doe', 'john.doe@example.com', '0123456789'],
        ['jane', 'doe', 'jane.doe@example.com'],
    ];

    protected function setUp(): void
    {
        $tmp = new SplTempFileObject();
        foreach ($this->expected as $row) {
            $tmp->fputcsv($row);
        }

        $this->csv = Reader::createFromFileObject($tmp);
    }

    protected function tearDown(): void
    {
        unset($this->csv);
    }

    /**
     * @covers ::count
     * @covers ::computeHeader
     * @covers ::getRecords
     */
    public function testCountable(): void
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
    public function testReaderWithEmptyEscapeChar1(): void
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
    public function testReaderWithEmptyEscapeChar2(): void
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
    public function testGetIterator(): void
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
            self::assertContains(count($record), [3, 4]);
        }
    }

    /**
     * @covers ::computeHeader
     * @covers ::combineHeader
     */
    public function testCombineHeader(): void
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
     * @covers ::seekRow
     */
    public function testGetHeader(): void
    {
        $this->csv->setHeaderOffset(1);
        self::assertSame(1, $this->csv->getHeaderOffset());
        self::assertSame($this->expected[1], $this->csv->getHeader());

        $this->csv->setHeaderOffset(null);
        self::assertNull($this->csv->getHeaderOffset());
        self::assertSame([], $this->csv->getHeader());
    }

    /**
     * @covers ::fetchColumn
     * @covers ::fetchColumnByName
     * @covers ::fetchColumnByOffset
     * @covers ::fetchOne
     * @covers ::fetchPairs
     */
    public function testCall(): void
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

        $res = Statement::create()->process($csv);
        self::assertEquals($csv->fetchOne(3), $res->fetchOne(3));
        self::assertEquals($csv->fetchColumn('firstname'), $res->fetchColumn('firstname'));
        self::assertEquals($csv->fetchColumnByName('firstname'), $res->fetchColumnByName('firstname'));
        self::assertEquals($csv->fetchColumnByOffset(1), $res->fetchColumnByOffset(1));
        self::assertEquals($csv->fetchPairs('lastname', 0), $res->fetchPairs('lastname', 0));
    }

    /**
     * @covers ::getHeader
     * @covers ::computeHeader
     * @covers ::getRecords
     * @covers ::setHeader
     * @covers \League\Csv\SyntaxError::dueToDuplicateHeaderColumnNames
     * @covers \League\Csv\SyntaxError::duplicateColumnNames
     */
    public function testHeaderThrowsExceptionOnError(): void
    {
        $csv = Reader::createFromString(
            'field1,field1,field3
            1,2,3
            4,5,6'
        );
        $csv->setHeaderOffset(0);
        self::assertSame(['field1', 'field1', 'field3'], $csv->getHeader());
        try {
            iterator_to_array($csv);
        } catch (SyntaxError $exception) {
            self::assertSame(['field1'], $exception->duplicateColumnNames());
        }
    }

    /**
     * @covers ::getHeader
     * @covers ::seekRow
     * @covers ::setHeaderOffset
     * @covers \League\Csv\SyntaxError::dueToHeaderNotFound
     * @covers \League\Csv\SyntaxError::duplicateColumnNames
     */
    public function testHeaderThrowsExceptionOnEmptyLine(): void
    {
        $str = <<<EOF
foo,bar,baz


1,2,3
EOF;
        $csv = Reader::createFromString($str);
        $csv->setHeaderOffset(2);
        try {
            $csv->getHeader();
        } catch (SyntaxError $exception) {
            self::assertSame([], $exception->duplicateColumnNames());
        }
    }

    /**
     * @covers ::getHeader
     * @covers ::computeHeader
     * @covers ::setHeaderOffset
     * @covers \League\Csv\SyntaxError::dueToInvalidHeaderColumnNames
     */
    public function testHeaderThrowsIfItContainsNonStringNames(): void
    {
        $this->expectException(SyntaxError::class);

        iterator_to_array($this->csv->getRecords(['field1', 2, 'field3']));
    }

    /**
     * @covers ::stripBOM
     * @covers ::removeBOM
     * @covers ::combineHeader
     * @covers \League\Csv\Stream
     *
     * @dataProvider validBOMSequences
     */
    public function testStripBOM(array $record, string $expected_bom, string $expected): void
    {
        /** @var resource $fp */
        $fp = fopen('php://temp', 'r+');
        fputcsv($fp, $record);
        $csv = Reader::createFromStream($fp);
        self::assertSame($expected_bom, $csv->getInputBOM());
        foreach ($csv as $offset => $row) {
            self::assertSame($expected, $row[0]);
        }
        $csv = null;
        fclose($fp);
        $fp = null;
    }

    public function validBOMSequences(): array
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
     * @covers \League\Csv\Stream
     */
    public function testStripBOMWithEnclosure(): void
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
     * @covers \League\Csv\Stream
     */
    public function testStripNoBOM(): void
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

    public function testDisablingBOMStripping(): void
    {
        $expected_record = [Reader::BOM_UTF16_LE.'john', 'doe', 'john.doe@example.com'];
        /** @var resource $fp */
        $fp = fopen('php://temp', 'r+');
        fputcsv($fp, $expected_record);
        $csv = Reader::createFromStream($fp);
        $csv->includeInputBOM();
        self::assertSame(Reader::BOM_UTF16_LE, $csv->getInputBOM());
        foreach ($csv as $offset => $record) {
            self::assertSame($expected_record, $record);
        }
        $csv = null;
        fclose($fp);
        $fp = null;
    }

    /**
     * @covers ::getIterator
     * @dataProvider appliedFlagsProvider
     */
    public function testAppliedFlags(int $flag, int $fetch_count): void
    {
        $path = __DIR__.'/../test_files/tmp.txt';
        $obj  = new SplFileObject($path, 'w+');
        $obj->fwrite("1st\n2nd\n");
        $obj->setFlags($flag);
        $reader = Reader::createFromFileObject($obj);
        self::assertCount($fetch_count, $reader);
        $reader = null;
        $obj = null;
        unlink($path);
    }

    public function appliedFlagsProvider(): array
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
     *
     * @covers \League\Csv\InvalidArgument::dueToInvalidHeaderOffset
     */
    public function testGetHeaderThrowsExceptionWithNegativeOffset(): void
    {
        $this->expectException(InvalidArgument::class);
        $this->csv->setHeaderOffset(-3)->getRecords();
    }

    /**
     * @covers ::setHeader
     * @covers ::seekRow
     *
     * @covers \League\Csv\SyntaxError::dueToHeaderNotFound
     */
    public function testGetHeaderThrowsExceptionWithSplFileObject(): void
    {
        $this->expectException(SyntaxError::class);
        $this->csv->setHeaderOffset(23)->getRecords();
    }

    /**
     * @covers ::setHeader
     * @covers ::seekRow
     *
     * @covers \League\Csv\SyntaxError::dueToHeaderNotFound
     */
    public function testGetHeaderThrowsExceptionWithStreamObject(): void
    {
        $this->expectException(SyntaxError::class);

        /** @var resource $tmp */
        $tmp = fopen('php://temp', 'r+');
        foreach ($this->expected as $row) {
            fputcsv($tmp, $row);
        }

        $csv = Reader::createFromStream($tmp);
        $csv->setHeaderOffset(23)->getRecords();
    }

    /**
     * @covers ::setHeaderOffset
     *
     * @covers \League\Csv\InvalidArgument::dueToInvalidHeaderOffset
     */
    public function testSetHeaderThrowsExceptionOnWrongInputRange(): void
    {
        $this->expectException(InvalidArgument::class);

        $this->csv->setHeaderOffset(-1);
    }

    /**
     * @covers ::computeHeader
     */
    public function testMapRecordsFields(): void
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
    public function testJsonSerialize(): void
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
    public function testCreateFromPath(): void
    {
        $csv = Reader::createFromPath(__DIR__.'/../test_files/foo_readonly.csv');
        self::assertCount(1, $csv);
    }

    /**
     * @dataProvider sourceProvider
     * @covers ::includeEmptyRecords
     * @covers ::skipEmptyRecords
     * @covers ::isEmptyRecordsIncluded
     * @covers ::getRecords
     */
    public function testSkippingEmptyRecords(
        Reader $reader,
        array $expected_with_skipping,
        array $expected_with_preserving,
        array $expected_with_skipping_with_header,
        array $expected_with_preserving_with_header
    ): void {
        self::assertFalse($reader->isEmptyRecordsIncluded());
        self::assertCount(count($expected_with_skipping), $reader);
        foreach ($reader as $offset => $record) {
            self::assertSame($expected_with_skipping[$offset], $record);
        }

        $reader->includeEmptyRecords();
        self::assertTrue($reader->isEmptyRecordsIncluded());
        self::assertCount(count($expected_with_preserving), $reader);
        foreach ($reader as $offset => $record) {
            self::assertSame($expected_with_preserving[$offset], $record);
        }

        $reader->setHeaderOffset(0);
        self::assertTrue($reader->isEmptyRecordsIncluded());
        self::assertCount(count($expected_with_preserving_with_header), $reader);
        foreach ($reader as $offset => $record) {
            self::assertSame($expected_with_preserving_with_header[$offset], $record);
        }

        $reader->skipEmptyRecords();
        self::assertFalse($reader->isEmptyRecordsIncluded());
        self::assertCount(count($expected_with_skipping_with_header), $reader);
        foreach ($reader as $offset => $record) {
            self::assertSame($expected_with_skipping_with_header[$offset], $record);
        }
    }

    public function sourceProvider(): array
    {
        $source = <<<EOF
"parent name","child name","title"


"parentA","childA","titleA"
EOF;
        $expected_with_preserving = [
            0 => ['parent name', 'child name', 'title'],
            1 => [],
            2 => [],
            3 => ['parentA', 'childA', 'titleA'],
        ];

        $expected_with_preserving_with_header = [
            1 => ['parent name' => null, 'child name' => null, 'title' => null],
            2 => ['parent name' => null, 'child name' => null, 'title' => null],
            3 => ['parent name' => 'parentA', 'child name' => 'childA', 'title' => 'titleA'],
        ];

        $expected_with_skipping = [
            0 => ['parent name', 'child name', 'title'],
            3 => ['parentA', 'childA', 'titleA'],
        ];

        $expected_with_skipping_with_header = [
            3 => ['parent name' => 'parentA', 'child name' => 'childA', 'title' => 'titleA'],
        ];

        $rsrc = new SplTempFileObject();
        $rsrc->fwrite($source);

        return [
            'FileObject' => [
                Reader::createFromFileObject($rsrc),
                $expected_with_skipping,
                $expected_with_preserving,
                $expected_with_skipping_with_header,
                $expected_with_preserving_with_header,
            ],
            'Stream' => [
                Reader::createFromString($source),
                $expected_with_skipping,
                $expected_with_preserving,
                $expected_with_skipping_with_header,
                $expected_with_preserving_with_header,
            ],
            'FileObject with empty escape char' =>  [
                Reader::createFromFileObject($rsrc)->setEscape(''),
                $expected_with_skipping,
                $expected_with_preserving,
                $expected_with_skipping_with_header,
                $expected_with_preserving_with_header,
            ],
            'Stream with empty escape char' => [
                Reader::createFromString($source)->setEscape(''),
                $expected_with_skipping,
                $expected_with_preserving,
                $expected_with_skipping_with_header,
                $expected_with_preserving_with_header,
            ],
        ];
    }

    public function testRemovingEmptyRecordsWhenBOMStringIsPresent(): void
    {
        $bom = Reader::BOM_UTF8;
        $text = <<<CSV
$bom
column 1,column 2,column 3
cell11,cell12,cell13
CSV;
        $csv = Reader::createFromString($text);
        $csv->setHeaderOffset(1);

        self::assertCount(1, $csv);
        self::assertSame([
            'column 1' => 'cell11',
            'column 2' => 'cell12',
            'column 3' => 'cell13',
        ], $csv->fetchOne(0));

        $csv->includeEmptyRecords();

        self::assertCount(2, $csv);
        self::assertSame([
            'column 1' => null,
            'column 2' => null,
            'column 3' => null,
        ], $csv->fetchOne(0));
    }

    public function testRemovingEmptyRecordsWithoutBOMString(): void
    {
        $text = <<<CSV

column 1,column 2,column 3
cell11,cell12,cell13
CSV;
        $csv = Reader::createFromString($text);
        $csv->setHeaderOffset(1);

        self::assertCount(1, $csv);
        self::assertSame([
            'column 1' => 'cell11',
            'column 2' => 'cell12',
            'column 3' => 'cell13',
        ], $csv->fetchOne(0));

        $csv->includeEmptyRecords();

        self::assertCount(2, $csv);
        self::assertSame([
            'column 1' => null,
            'column 2' => null,
            'column 3' => null,
        ], $csv->fetchOne(0));
    }

    public function testGetHeaderThrowsIfTheFirstRecordOnlyContainsBOMString(): void
    {
        $bom = Reader::BOM_UTF8;
        $text = <<<CSV
$bom
column 1,column 2,column 3
cell11,cell12,cell13
CSV;
        $csv = Reader::createFromString($text);
        $csv->setHeaderOffset(0);

        $this->expectException(Exception::class);
        $csv->getHeader();
    }
}
