<?php

namespace LeagueTest\Csv;

use DOMDocument;
use League\Csv\Exception;
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

    public function testSetLimit()
    {
        $stmt = (new Statement())->limit(1);

        $this->assertCount(1, $stmt->process($this->csv)->fetchAll());
    }

    public function testCountable()
    {
        $stmt = (new Statement())->limit(1);
        $res = $stmt->process($this->csv);
        $this->assertCount(1, $res);
        $this->assertSame(iterator_to_array($res, false), $res->fetchAll());
    }

    public function testToHTML()
    {
        $this->assertContains('<table', $this->csv->select()->toHTML());
    }

    public function testToXML()
    {
        $this->csv->setHeaderOffset(0);
        $this->assertInstanceOf(DOMDocument::class, $this->csv->select()->toXML());
    }

    public function testStatementSameInstance()
    {
        $stmt = new Statement();
        $stmt_alt = $stmt->limit(-1)->offset(0);

        $this->assertSame($stmt_alt, $stmt);
    }

    public function testSetLimitThrowException()
    {
        $this->expectException(Exception::class);
        (new Statement())->limit(-4);
    }

    public function testSetOffset()
    {
        $stmt = (new Statement())->offset(1);

        $this->assertContains(
            ['jane', 'doe', 'jane.doe@example.com'],
            $stmt->process($this->csv)->fetchAll()
        );
    }

    /**
     * @dataProvider intervalTest
     */
    public function testInterval($offset, $limit, $expected)
    {
        $stmt = (new Statement())
            ->offset($offset)
            ->limit($limit);

        $this->assertContains(
            ['jane', 'doe', 'jane.doe@example.com'],
            $stmt->process($this->csv)->fetchAll()
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
        (new Statement())
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
        $stmt = (new Statement())->where($func);
        $this->assertNotContains(
            ['jane', 'doe', 'jane.doe@example.com'],
            $stmt->process($this->csv)->fetchAll()
        );
    }

    public function testSortBy()
    {
        $func = function ($rowA, $rowB) {
            return strcmp($rowA[0], $rowB[0]);
        };
        $stmt = (new Statement())->orderBy($func);
        $this->assertSame(
            array_reverse($this->expected),
            $stmt->process($this->csv)->fetchAll()
        );
    }

    public function testFetchAssoc()
    {
        $keys = ['firstname', 'lastname', 'email'];
        $stmt = (new Statement())->header($keys);
        $res = $stmt->process($this->csv)->fetchAll();
        foreach ($res as $offset => $row) {
            $this->assertSame($keys, array_keys($row));
        }
    }

    public function testFetchColumnWithFieldName()
    {
        $keys = ['firstname', 'lastname', 'email'];
        $stmt = (new Statement())->header($keys);
        $res = $stmt->process($this->csv)->fetchColumn('firstname');
        $this->assertSame(['john', 'jane'], iterator_to_array($res, false));
    }

    public function testFetchColumnWithColumnIndex()
    {
        $keys = ['firstname', 'lastname', 'email'];
        $stmt = (new Statement())->header($keys);
        $res = $this->csv->select($stmt)->fetchColumn(0);
        $this->assertSame(['john', 'jane'], iterator_to_array($res, false));
    }

    public function testFetchColumnTriggersException()
    {
        $this->expectException(Exception::class);
        $keys = ['firstname', 'lastname', 'email'];
        $stmt = (new Statement())->header($keys);
        $res = $stmt->process($this->csv)->fetchColumn(24);
        $this->assertSame(['john', 'jane'], iterator_to_array($res, false));
    }

    public function testFetchAssocLessKeys()
    {
        $keys = ['firstname'];
        $stmt = (new Statement())->header($keys);
        $res = $stmt->process($this->csv)->fetchAll();
        $this->assertContains(['firstname' => 'john'], $res);
    }

    public function testFetchAssocMoreKeys()
    {
        $keys = ['firstname', 'lastname', 'email', 'age'];
        $stmt = (new Statement())->header($keys);

        $this->assertContains([
            'firstname' => 'jane',
            'lastname' => 'doe',
            'email' => 'jane.doe@example.com',
            'age' => null,
        ], $stmt->process($this->csv)->fetchAll());
    }

    public function testFetchWithoutHeaders()
    {
        $stmt = (new Statement())->header([]);

        $this->assertContains([
            'jane',
            'doe',
            'jane.doe@example.com',
        ], $stmt->process($this->csv)->fetchAll());
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
            $csv->select()->fetchAll()
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
        $this->assertSame($res, $csv->select()->fetchAll()[0][0]);
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
        $res = array_keys($csv->select()->fetchAll()[0]);

        $this->assertSame('john', $res[0]);
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
        $res = array_keys($csv->select()->fetchAll()[0]);

        $this->assertSame('john', $res[0]);
    }

    public function testStripBOMWithEnclosureFetchAssoc()
    {
        $expected = ['parent name', 'parentA'];
        $source = Reader::BOM_UTF8.'"parent name","child name","title"
            "parentA","childA","titleA"';
        $csv = Reader::createFromString($source);
        $csv->setHeaderOffset(0);
        $expected = [
            ['parent name' => 'parentA', 'child name' => 'childA', 'title' => 'titleA'],
        ];
        $this->assertSame($expected, $csv->select()->fetchAll());
    }

    public function testStripBOMWithEnclosureFetchColumn()
    {
        $source = Reader::BOM_UTF8.'"parent name","child name","title"
            "parentA","childA","titleA"';
        $csv = Reader::createFromString($source);
        $this->assertContains('parent name', $csv->select()->fetchColumn());
    }

    public function testStripBOMWithEnclosureFetchAll()
    {
        $source = Reader::BOM_UTF8.'"parent name","child name","title"
            "parentA","childA","titleA"';
        $csv = Reader::createFromString($source);
        $csv->setHeaderOffset(null);
        $this->assertContains(['parent name', 'child name', 'title'], $csv->select()->fetchAll());
    }

    public function testStripBOMWithEnclosureFetchOne()
    {
        $source = Reader::BOM_UTF8.'"parent name","child name","title"
            "parentA","childA","titleA"';
        $csv = Reader::createFromString($source);
        $csv->setHeaderOffset(null);
        $expected = ['parent name', 'child name', 'title'];
        $this->assertEquals($expected, $csv->select()->fetchOne());
    }

    public function testFetchAssocKeyFailure()
    {
        $this->expectException(Exception::class);
        (new Statement())->header(['firstname', 'firstname', 'lastname', 'email', 'age']);
    }

    /**
     * @param $offset
     * @dataProvider invalidOffsetWithFetchAssoc
     */
    public function testFetchAssocWithInvalidOffset($offset)
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

        $this->expectException(Exception::class);
        Reader::createFromFileObject($tmp)->setHeaderOffset($offset)->select()->fetchAll();
    }

    public function invalidOffsetWithFetchAssoc()
    {
        return [
            'negative' => [-23],
            'tooHigh' => [23],
        ];
    }

    public function testFetchColumn()
    {
        $this->assertContains('john', $this->csv->select()->fetchColumn(0));
        $this->assertContains('jane', $this->csv->select()->fetchColumn());
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
        $res = $csv->select()->fetchColumn(2);
        $this->assertCount(1, $res);
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
        $res = $csv->select()->fetchColumn(2);
        $this->assertCount(0, $res);
    }

    public function testfetchOne()
    {
        $this->assertSame($this->expected[0], $this->csv->select()->fetchOne(0));
        $this->assertSame($this->expected[1], $this->csv->select()->fetchOne(1));
        $this->assertSame([], $this->csv->select()->fetchOne(35));
    }

    public function testFetchOneTriggersException()
    {
        $this->expectException(Exception::class);
        $this->csv->select()->fetchOne(-5);
    }

    /**
     * @dataProvider fetchPairsDataProvider
     */
    public function testFetchPairsIteratorMode($key, $value, $expected)
    {
        $iterator = $this->csv->select()->fetchPairs($key, $value);
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
        $this->assertCount(0, iterator_to_array($this->csv->select()->fetchPairs(10, 1), true));
    }

    public function testFetchPairsWithInvalidValue()
    {
        $res = $this->csv->select()->fetchPairs(0, 15);
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
        $res = (new Statement())->offset(799)->limit(50)->process($csv);
        $res->setConversionInputEncoding('iso-8859-15');

        json_encode($res);
        $this->assertEquals(JSON_ERROR_NONE, json_last_error());
    }

    public static function getIso8859Csv()
    {
        return [[file_get_contents(__DIR__.'/data/prenoms.csv')]];
    }

    public function testEncoding()
    {
        $expected = 'iso-8859-15';
        $result = $this->csv->select();
        $result->setConversionInputEncoding($expected);
        $this->assertSame(strtoupper($expected), $result->getConversionInputEncoding());
    }

    public function testEncodingTriggersException()
    {
        $this->expectException(Exception::class);
        $this->csv->select()->setConversionInputEncoding('');
    }

    public function testGetHeader()
    {
        $stmt = new Statement();
        $result = $this->csv->select($stmt);
        $this->assertSame([], $result->getHeader());
    }

    public function testGetComputedHeader()
    {
        $this->csv->setHeaderOffset(0);
        $stmt = new Statement();
        $result = $this->csv->select($stmt);
        $this->assertSame($this->expected[0], $result->getHeader());
    }


    public function testGetComputedHeaderWithSpecifiedHeader()
    {
        $expected = ['foo', 'bar', 'baz'];
        $this->csv->setHeaderOffset(0);
        $stmt = new Statement();
        $result = $this->csv->select($stmt->header($expected));
        $this->assertSame($expected, $result->getHeader());
    }
}
