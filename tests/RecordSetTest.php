<?php

namespace LeagueTest\Csv;

use League\Csv\BOM;
use League\Csv\Exception\CsvException;
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
        $records->preserveRecordOffset(true);
        $this->assertSame(iterator_to_array($records, false), $records->fetchAll());
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
     * @param int $offset
     * @param int $limit
     */
    public function testInterval($offset, $limit)
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
            'tooHigh' => [1, 10],
            'normal' => [1, 1],
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

    /**
     * @dataProvider invalidFieldNameProvider
     * @param int|string $field
     */
    public function testFetchColumnTriggersException($field)
    {
        $this->expectException(CsvException::class);
        $this->csv->setHeaderOffset(0);
        $res = $this->stmt->process($this->csv)->fetchColumn($field);
        iterator_to_array($res, false);
    }

    public function invalidFieldNameProvider()
    {
        return [
            'negative integer offset' => [-1],
            'invalid integer offset' => [24],
            'unknown column name' => ['fooBar'],
        ];
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
     * @param array $records
     * @param array $expected
     * @dataProvider validBOMSequences
     */
    public function testStripBOM($records, $expected)
    {
        $tmpFile = new SplTempFileObject();
        foreach ($records as $row) {
            $tmpFile->fputcsv($row);
        }
        $csv = Reader::createFromFileObject($tmpFile);
        $this->assertSame($expected, $this->stmt->process($csv)->fetchAll()[0][0]);
    }

    public function validBOMSequences()
    {
        return [
            'withBOM' => [[
                [BOM::UTF16_LE.'john', 'doe', 'john.doe@example.com'],
                ['jane', 'doe', 'jane.doe@example.com'],
            ], 'john'],
            'withDoubleBOM' =>  [[
                [BOM::UTF16_LE.BOM::UTF16_LE.'john', 'doe', 'john.doe@example.com'],
                ['jane', 'doe', 'jane.doe@example.com'],
            ], BOM::UTF16_LE.'john'],
            'withoutBOM' => [[
                ['john', 'doe', 'john.doe@example.com'],
                ['jane', 'doe', 'jane.doe@example.com'],
            ], 'john'],
        ];
    }

    public function testStripBOMWithFetchAssoc()
    {
        $source = [
            [BOM::UTF16_LE.'john', 'doe', 'john.doe@example.com'],
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
        $source = BOM::UTF8.'"parent name","child name","title"
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
        $source = BOM::UTF8.'"parent name","child name","title"
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
        $records->preserveRecordOffset(false);
        $this->assertFalse($records->isRecordOffsetPreserved());
        $this->assertSame($expectedNoOffset, $records->fetchAll());
        $records->preserveRecordOffset(true);
        $this->assertTrue($records->isRecordOffsetPreserved());
        $this->assertSame($expectedWithOffset, $records->fetchAll());
    }

    public function testFetchColumnWithColumnname()
    {
        $source = BOM::UTF8.'"parent name","child name","title"
            "parentA","childA","titleA"';
        $csv = Reader::createFromString($source);
        $csv->setHeaderOffset(0);
        $this->assertContains('parentA', $this->stmt->process($csv)->fetchColumn('parent name'));
        $this->assertContains('parentA', $this->stmt->process($csv)->fetchColumn(0));
    }

    public function testStripBOMWithEnclosureFetchAll()
    {
        $source = BOM::UTF8.'"parent name","child name","title"
            "parentA","childA","titleA"';
        $csv = Reader::createFromString($source);
        $csv->setHeaderOffset(null);
        $this->assertContains(['parent name', 'child name', 'title'], $this->stmt->process($csv)->fetchAll());
    }

    public function testStripBOMWithEnclosureFetchOne()
    {
        $source = BOM::UTF8.'"parent name","child name","title"
            "parentA","childA","titleA"';
        $csv = Reader::createFromString($source);
        $csv->setHeaderOffset(null);
        $expected = ['parent name', 'child name', 'title'];
        $this->assertEquals($expected, $this->stmt->process($csv)->fetchOne());
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
     * @param int|string $key
     * @param int|string $value
     * @param array      $expected
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

    public static function getIso8859Csv()
    {
        return [[file_get_contents(__DIR__.'/data/prenoms.csv')]];
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
}
