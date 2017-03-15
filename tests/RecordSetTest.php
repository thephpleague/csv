<?php

namespace LeagueTest\Csv;

use DOMDocument;
use League\Csv\Exception\CsvException;
use League\Csv\Exception\InvalidArgumentException;
use League\Csv\Exception\OutOfRangeException;
use League\Csv\Exception\RuntimeException;
use League\Csv\Reader;
use League\Csv\Statement;
use OutOfBoundsException;
use PHPUnit\Framework\TestCase;
use SplTempFileObject;

/**
 * @group reader
 * @group statement
 * @group recordset
 */
class RecordSetTest extends TestCase
{
    private $csv;

    private $stmt;

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
        $this->stmt = new Statement();
    }

    public function tearDown()
    {
        $this->csv = null;
        $this->stmt = null;
    }

    public function testSetLimit()
    {
        $this->assertCount(1, $this->stmt->limit(1)->process($this->csv)->fetchAll());
    }

    public function testCountable()
    {
        $records = $this->stmt->limit(1)->process($this->csv);
        $this->assertCount(1, $records);
        $records->preserveOffset(true);
        $this->assertSame(iterator_to_array($records, false), $records->fetchAll());
    }

    public function testToHTML()
    {
        $this->assertContains('<table', $this->stmt->process($this->csv)->toHTML());
    }

    public function testAddHeaderToHTMLExport()
    {
        $this->csv->setHeaderOffset(0);
        $records = $this->stmt->process($this->csv);
        $this->assertContains('<td title="john">jane</td>', $records->toHTML());
        $this->csv->setHeaderOffset(null);
        $this->assertContains('<td>jane</td>', $this->stmt->process($this->csv)->toHTML());
        $records->preserveOffset(true);
        $this->assertContains('<tr data-record-offset="', $records->toHTML());
    }

    public function testToXML()
    {
        $this->csv->setHeaderOffset(0);
        $this->assertInstanceOf(DOMDocument::class, $this->stmt->process($this->csv)->toXML());
    }

    public function testStatementSameInstance()
    {
        $stmt_alt = $this->stmt->limit(-1)->offset(0);

        $this->assertSame($stmt_alt, $this->stmt);
    }

    public function testSetLimitThrowException()
    {
        $this->expectException(OutOfRangeException::class);
        $this->stmt->limit(-4);
    }

    public function testSetOffset()
    {
        $this->assertContains(
            ['jane', 'doe', 'jane.doe@example.com'],
            $this->stmt->offset(1)->process($this->csv)->fetchAll()
        );
    }

    /**
     * @dataProvider intervalTest
     */
    public function testInterval($offset, $limit, $expected)
    {
        $this->assertContains(
            ['jane', 'doe', 'jane.doe@example.com'],
            $this->stmt
                ->offset($offset)
                ->limit($limit)
                ->process($this->csv)
                ->fetchAll()
        );
    }

    public function intervalTest()
    {
        return [
            'tooHigh' => [1, 10, 1],
            'normal' => [1, 1, 1],
        ];
    }

    public function testIntervalThrowException()
    {
        $this->expectException(OutOfBoundsException::class);
        $this->stmt
            ->offset(1)
            ->limit(0)
            ->process($this->csv)
            ->fetchAll();
    }

    public function testFilter()
    {
        $func = function ($row) {
            return !in_array('jane', $row);
        };

        $this->assertNotContains(
            ['jane', 'doe', 'jane.doe@example.com'],
            $this->stmt->where($func)->process($this->csv)->fetchAll()
        );
    }

    public function testSortBy()
    {
        $func = function ($rowA, $rowB) {
            return strcmp($rowA[0], $rowB[0]);
        };
        $this->assertSame(
            array_reverse($this->expected),
            $this->stmt->orderBy($func)->process($this->csv)->fetchAll()
        );
    }

    public function testFetchAssoc()
    {
        $keys = ['firstname', 'lastname', 'email'];
        $res = $this->stmt->columns($keys)->process($this->csv)->fetchAll();
        foreach ($res as $offset => $row) {
            $this->assertSame($keys, array_keys($row));
        }
    }

    public function testFetchColumnWithFieldName()
    {
        $keys = ['firstname', 'lastname', 'email'];
        $res = $this->stmt->columns($keys)->process($this->csv)->fetchColumn('firstname');
        $this->assertSame(['john', 'jane'], iterator_to_array($res, false));
    }

    public function testFetchColumnWithColumnIndex()
    {
        $keys = ['firstname', 'lastname', 'email'];
        $records = $this->stmt->columns($keys)->process($this->csv);
        $records->preserveOffset(true);
        $this->assertSame(['john', 'jane'], iterator_to_array($records->fetchColumn(0), false));
    }

    /**
     * @dataProvider invalidFieldNameProvider
     */
    public function testFetchColumnTriggersException($field)
    {
        $this->expectException(CsvException::class);
        $keys = ['firstname', 'lastname', 'email'];
        $res = $this->stmt->columns($keys)->process($this->csv)->fetchColumn($field);
        $this->assertSame(['john', 'jane'], iterator_to_array($res, false));
    }

    public function invalidFieldNameProvider()
    {
        return [
            'negative integer offset' => [-1],
            'invalid integer offset' => [24],
            'unknown column name' => ['fooBar'],
        ];
    }

    public function testFetchAssocLessKeys()
    {
        $keys = ['firstname'];
        $this->assertContains(
            ['firstname' => 'john'],
            $this->stmt->columns($keys)->process($this->csv)->fetchAll()
        );
    }

    public function testFetchAssocMoreKeys()
    {
        $keys = ['firstname', 'lastname', 'email', 'age'];

        $this->assertContains([
            'firstname' => 'jane',
            'lastname' => 'doe',
            'email' => 'jane.doe@example.com',
            'age' => null,
        ], $this->stmt->columns($keys)->process($this->csv)->fetchAll());
    }

    public function testFetchWithoutHeaders()
    {
        $this->assertContains([
            'jane',
            'doe',
            'jane.doe@example.com',
        ], $this->stmt->columns([])->process($this->csv)->fetchAll());
    }

    public function testFetchAssocWithRowIndex()
    {
        $arr = [
            ['A', 'B', 'C'],
            [1, 2, 3],
            ['D', 'E', 'F'],
            [6, 7, 8],
        ];

        $tmp = new SplTempFileObject();
        foreach ($arr as $row) {
            $tmp->fputcsv($row);
        }

        $csv = Reader::createFromFileObject($tmp);
        $csv->setHeaderOffset(2);
        $this->assertContains(
            ['D' => '6', 'E' => '7', 'F' => '8'],
            $this->stmt->process($csv)->fetchAll()
        );
    }

    /**
     * @param  $expected
     * @dataProvider validBOMSequences
     */
    public function testStripBOM($expected, $res)
    {
        $tmpFile = new SplTempFileObject();
        foreach ($expected as $row) {
            $tmpFile->fputcsv($row);
        }
        $csv = Reader::createFromFileObject($tmpFile);
        $this->assertSame($res, $this->stmt->process($csv)->fetchAll()[0][0]);
    }

    public function validBOMSequences()
    {
        return [
            'withBOM' => [[
                [Reader::BOM_UTF16_LE.'john', 'doe', 'john.doe@example.com'],
                ['jane', 'doe', 'jane.doe@example.com'],
            ], 'john'],
            'withDoubleBOM' =>  [[
                [Reader::BOM_UTF16_LE.Reader::BOM_UTF16_LE.'john', 'doe', 'john.doe@example.com'],
                ['jane', 'doe', 'jane.doe@example.com'],
            ], Reader::BOM_UTF16_LE.'john'],
            'withoutBOM' => [[
                ['john', 'doe', 'john.doe@example.com'],
                ['jane', 'doe', 'jane.doe@example.com'],
            ], 'john'],
        ];
    }

    public function testStripBOMWithFetchAssoc()
    {
        $source = [
            [Reader::BOM_UTF16_LE.'john', 'doe', 'john.doe@example.com'],
            ['jane', 'doe', 'jane.doe@example.com'],
        ];

        $tmp = new SplTempFileObject();
        foreach ($source as $row) {
            $tmp->fputcsv($row);
        }
        $csv = Reader::createFromFileObject($tmp);
        $csv->setHeaderOffset(0);
        $res = $this->stmt->process($csv)->fetchAll();
        $first = array_shift($res);
        $keys = array_keys($first);

        $this->assertSame('john', $keys[0]);
    }

    public function testFetchAssocWithoutBOM()
    {
        $source = [
            ['john', 'doe', 'john.doe@example.com'],
            ['jane', 'doe', 'jane.doe@example.com'],
        ];

        $tmp = new SplTempFileObject();
        foreach ($source as $row) {
            $tmp->fputcsv($row);
        }
        $csv = Reader::createFromFileObject($tmp);
        $csv->setHeaderOffset(0);
        $res = $this->stmt->process($csv)->fetchAll();
        $first = array_shift($res);
        $keys = array_keys($first);

        $this->assertSame('john', $keys[0]);
    }

    public function testStripBOMWithEnclosureFetchAssoc()
    {
        $expected = ['parent name', 'parentA'];
        $source = Reader::BOM_UTF8.'"parent name","child name","title"
            "parentA","childA","titleA"';
        $csv = Reader::createFromString($source);
        $csv->setHeaderOffset(0);
        $expected = [
            0 => ['parent name' => 'parentA', 'child name' => 'childA', 'title' => 'titleA'],
        ];
        $this->assertSame($expected, $this->stmt->process($csv)->fetchAll());
    }


    public function testPreserveOffset()
    {
        $expected = ['parent name', 'parentA'];
        $source = Reader::BOM_UTF8.'"parent name","child name","title"
            "parentA","childA","titleA"';
        $csv = Reader::createFromString($source);
        $csv->setHeaderOffset(0);
        $expectedNoOffset = [
            0 => ['parent name' => 'parentA', 'child name' => 'childA', 'title' => 'titleA'],
        ];
        $expectedWithOffset = [
            1 => ['parent name' => 'parentA', 'child name' => 'childA', 'title' => 'titleA'],
        ];
        $records = $this->stmt->process($csv);
        $records->preserveOffset(false);
        $this->assertFalse($records->isOffsetPreserved());
        $this->assertSame($expectedNoOffset, $records->fetchAll());
        $records->preserveOffset(true);
        $this->assertTrue($records->isOffsetPreserved());
        $this->assertSame($expectedWithOffset, $records->fetchAll());
    }



    public function testStripBOMWithEnclosureFetchColumn()
    {
        $source = Reader::BOM_UTF8.'"parent name","child name","title"
            "parentA","childA","titleA"';
        $csv = Reader::createFromString($source);
        $this->assertContains('parent name', $this->stmt->process($csv)->fetchColumn());
    }

    public function testStripBOMWithEnclosureFetchAll()
    {
        $source = Reader::BOM_UTF8.'"parent name","child name","title"
            "parentA","childA","titleA"';
        $csv = Reader::createFromString($source);
        $csv->setHeaderOffset(null);
        $this->assertContains(['parent name', 'child name', 'title'], $this->stmt->process($csv)->fetchAll());
    }

    public function testStripBOMWithEnclosureFetchOne()
    {
        $source = Reader::BOM_UTF8.'"parent name","child name","title"
            "parentA","childA","titleA"';
        $csv = Reader::createFromString($source);
        $csv->setHeaderOffset(null);
        $expected = ['parent name', 'child name', 'title'];
        $this->assertEquals($expected, $this->stmt->process($csv)->fetchOne());
    }

    public function testFetchAssocKeyFailure()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->stmt->columns(['firstname', 'firstname', 'lastname', 'email', 'age']);
    }

    public function testFetchAssocWithUnknownOffset()
    {
        $this->expectException(RuntimeException::class);
        $this->stmt->process($this->csv->setHeaderOffset(23))->fetchAll();
    }

    public function testFetchColumn()
    {
        $this->assertContains('john', $this->stmt->process($this->csv)->fetchColumn(0));
        $this->assertContains('jane', $this->stmt->process($this->csv)->fetchColumn());
    }

    public function testFetchColumnInconsistentColumnCSV()
    {
        $raw = [
            ['john', 'doe'],
            ['lara', 'croft', 'lara.croft@example.com'],
        ];

        $file = new SplTempFileObject();
        foreach ($raw as $row) {
            $file->fputcsv($row);
        }
        $csv = Reader::createFromFileObject($file);
        $res = $this->stmt->process($csv)->fetchColumn(2);
        $this->assertCount(1, iterator_to_array($res));
    }

    public function testFetchColumnEmptyCol()
    {
        $raw = [
            ['john', 'doe'],
            ['lara', 'croft'],
        ];

        $file = new SplTempFileObject();
        foreach ($raw as $row) {
            $file->fputcsv($row);
        }
        $csv = Reader::createFromFileObject($file);
        $res = $this->stmt->process($csv)->fetchColumn(2);
        $this->assertCount(0, iterator_to_array($res));
    }

    public function testfetchOne()
    {
        $this->assertSame($this->expected[0], $this->stmt->process($this->csv)->fetchOne(0));
        $this->assertSame($this->expected[1], $this->stmt->process($this->csv)->fetchOne(1));
        $this->assertSame([], $this->stmt->process($this->csv)->fetchOne(35));
    }

    public function testFetchOneTriggersException()
    {
        $this->expectException(OutOfRangeException::class);
        $this->stmt->process($this->csv)->fetchOne(-5);
    }

    /**
     * @dataProvider fetchPairsDataProvider
     */
    public function testFetchPairsIteratorMode($key, $value, $expected)
    {
        $iterator = $this->stmt->process($this->csv)->fetchPairs($key, $value);
        foreach ($iterator as $key => $value) {
            $res = current($expected);
            $this->assertSame($value, $res[$key]);
            next($expected);
        }
    }

    public function fetchPairsDataProvider()
    {
        return [
            'default values' => [
                'key' => 0,
                'value' => 1,
                'expected' => [
                    ['john' => 'doe'],
                    ['jane' => 'doe'],
                ],
            ],
            'changed key order' => [
                'key' => 1,
                'value' => 0,
                'expected' => [
                    ['doe' => 'john'],
                    ['doe' => 'jane'],
                ],
            ],
        ];
    }

    public function testFetchPairsWithInvalidOffset()
    {
        $this->assertCount(0, iterator_to_array($this->stmt->process($this->csv)->fetchPairs(10, 1), true));
    }

    public function testFetchPairsWithInvalidValue()
    {
        $res = $this->stmt->process($this->csv)->fetchPairs(0, 15);
        foreach ($res as $value) {
            $this->assertNull($value);
        }
    }

    /**
     * @param $rawCsv
     *
     * @dataProvider getIso8859Csv
     */
    public function testJsonSerializeAffectedByReaderOptions($rawCsv)
    {
        $csv = Reader::createFromString($rawCsv);
        $records = $this->stmt->offset(799)->limit(50)->process($csv);
        $this->assertSame('UTF-8', $records->getConversionInputEncoding());
        $records->setConversionInputEncoding('iso-8859-15');
        $this->assertSame('ISO-8859-15', $records->getConversionInputEncoding());

        json_encode($records);
        $this->assertEquals(JSON_ERROR_NONE, json_last_error());
    }

    public static function getIso8859Csv()
    {
        return [[file_get_contents(__DIR__.'/data/prenoms.csv')]];
    }

    public function testEncodingTriggersException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->stmt->process($this->csv)->setConversionInputEncoding('');
    }

    public function testGetHeader()
    {
        $this->assertSame([], $this->stmt->process($this->csv)->getColumnNames());
    }

    public function testGetComputedHeader()
    {
        $this->csv->setHeaderOffset(0);
        $this->assertSame($this->expected[0], $this->stmt->process($this->csv)->getColumnNames());
    }

    public function testGetComputedHeaderWithSpecifiedHeader()
    {
        $expected = ['john' => 'prenom', 'doe' => 'lastname', 'john.doe@example.com' => 'email'];
        $this->csv->setHeaderOffset(0);
        $records = $this->stmt->columns($expected)->process($this->csv);
        $this->assertSame(array_values($expected), $records->getColumnNames());
    }

    public function testColumnsThrowException()
    {
        $this->expectException(RuntimeException::class);
        $this->stmt
            ->columns(['john' => 'prenom', 'doe' => 'lastname', 'john.doe@example.com' => 'email'])
            ->process($this->csv);
    }
}
