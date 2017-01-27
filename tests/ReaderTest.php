<?php

namespace LeagueTest\Csv;

use League\Csv\Reader;
use League\Csv\Writer;
use PHPUnit_Framework_TestCase;
use SplTempFileObject;

/**
 * @group reader
 */
class ReaderTest extends PHPUnit_Framework_TestCase
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

    public function testSetLimit()
    {
        $this->assertCount(1, $this->csv->setLimit(1)->fetchAll());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSetLimitThrowException()
    {
        $this->csv->setLimit(-4);
    }

    public function testSetOffset()
    {
        $this->assertContains(
            ['jane', 'doe', 'jane.doe@example.com'],
            $this->csv->setOffset(1)->fetchAll()
        );
    }

    /**
     * @dataProvider intervalTest
     */
    public function testInterval($offset, $limit, $expected)
    {
        $this->csv->setOffset($offset);
        $this->csv->setLimit($limit);
        $this->assertContains(
            ['jane', 'doe', 'jane.doe@example.com'],
            $this->csv->setOffset(1)->fetchAll()
        );
    }

    public function intervalTest()
    {
        return [
            'tooHigh' => [1, 10, 1],
            'normal' => [1, 1, 1],
        ];
    }

    /**
     * @expectedException \OutOfBoundsException
     */
    public function testIntervalThrowException()
    {
        $this->csv->setOffset(1);
        $this->csv->setLimit(0);
        $this->csv->fetchAll();
    }


    public function testFilter()
    {
        $func = function ($row) {
            return !in_array('jane', $row);
        };
        $this->csv->addFilter($func);
        $this->assertNotContains(['jane', 'doe', 'jane.doe@example.com'], $this->csv->fetchAll());
    }

    public function testSortBy()
    {
        $func = function ($rowA, $rowB) {
            return strcmp($rowA[0], $rowB[0]);
        };
        $this->csv->addSortBy($func);
        $this->assertSame(array_reverse($this->expected), $this->csv->fetchAll());
    }

    public function testFetchAll()
    {
        $func = function ($value) {
            return array_map('strtoupper', $value);
        };

        $res = $this->csv->fetchAll($func);
        $this->assertContains(['JANE', 'DOE', 'JANE.DOE@EXAMPLE.COM'], $res);
    }

    public function testFetchAssoc()
    {
        $keys = ['firstname', 'lastname', 'email'];
        $this->csv->setHeader($keys);
        $res = $this->csv->fetchAll();
        foreach ($res as $offset => $row) {
            $this->assertSame($keys, array_keys($row));
        }
    }

    public function testFetchAssocCallback()
    {
        $keys = ['firstname', 'lastname', 'email'];
        $func = function ($value) {
            return array_map('strtoupper', $value);
        };
        $this->csv->setHeader($keys);
        $res = $this->csv->fetchAll($func);
        foreach ($res as $row) {
            $this->assertSame($keys, array_keys($row));
        }
        $this->assertContains([
            'firstname' => 'JANE',
            'lastname' => 'DOE',
            'email' => 'JANE.DOE@EXAMPLE.COM',
        ], $res);
    }

    public function testFetchAssocLessKeys()
    {
        $keys = ['firstname'];
        $this->csv->setHeader($keys);
        $res = $this->csv->fetchAll();
        $this->assertContains(['firstname' => 'john'], $res);
    }

    public function testFetchAssocMoreKeys()
    {
        $keys = ['firstname', 'lastname', 'email', 'age'];
        $this->csv->setHeader($keys);
        $res = $this->csv->fetchAll();

        $this->assertContains([
            'firstname' => 'jane',
            'lastname' => 'doe',
            'email' => 'jane.doe@example.com',
            'age' => null,
        ], $res);
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
        $csv->setHeader(2);
        $this->assertContains(['D' => '6', 'E' => '7', 'F' => '8'], $csv->fetchAll());
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
        $this->assertSame($res, $csv->fetchAll()[0][0]);
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
        $csv->setHeader(0);
        $res = array_keys($csv->fetchAll()[0]);

        $this->assertSame('john', $res[0]);
    }

    public function testStripBOMWithEnclosureFetchAssoc()
    {
        $expected = ['parent name', 'parentA'];
        $source = Reader::BOM_UTF8.'"parent name","child name","title"
            "parentA","childA","titleA"';
        $csv = Reader::createFromString($source);
        $csv->setHeader(0);
        $expected = [
            ['parent name' => 'parentA', 'child name' => 'childA', 'title' => 'titleA'],
        ];
        $this->assertSame($expected, $csv->fetchAll());
    }

    public function testStripBOMWithEnclosureFetchColumn()
    {
        $source = Reader::BOM_UTF8.'"parent name","child name","title"
            "parentA","childA","titleA"';
        $csv = Reader::createFromString($source);
        $this->assertContains('parent name', $csv->fetchColumn());
    }

    public function testStripBOMWithEnclosureFetchAll()
    {
        $source = Reader::BOM_UTF8.'"parent name","child name","title"
            "parentA","childA","titleA"';
        $csv = Reader::createFromString($source);
        $csv->setHeader(null);
        $this->assertContains(['parent name', 'child name', 'title'], $csv->fetchAll());
    }

    public function testStripBOMWithEnclosureFetchOne()
    {
        $source = Reader::BOM_UTF8.'"parent name","child name","title"
            "parentA","childA","titleA"';
        $csv = Reader::createFromString($source);
        $csv->setHeader([]);
        $this->assertSame(Reader::BOM_UTF8, $csv->getInputBOM());
        $expected = ['parent name', 'child name', 'title'];
        $this->assertEquals($expected, $csv->fetchOne());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFetchAssocKeyFailure()
    {
        $this->csv->setHeader([['firstname', 'lastname', 'email', 'age']]);
    }

    /**
     * @param $offset
     * @dataProvider invalidOffsetWithFetchAssoc
     * @expectedException \InvalidArgumentException
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

        Reader::createFromFileObject($tmp)->setHeader($offset)->fetchAll();
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
        $this->assertContains('john', $this->csv->fetchColumn(0));
        $this->assertContains('jane', $this->csv->fetchColumn());
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
        $res = $csv->fetchColumn(2);
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
        $res = $csv->fetchColumn(2);
        $this->assertCount(0, $res);
    }


    public function testFetchColumnCallback()
    {
        $func = function ($value) {
            return strtoupper($value);
        };
        $iterator = $this->csv->fetchColumn(0, $func);
        $this->assertSame(['JOHN', 'JANE'], iterator_to_array($iterator, false));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testfetchOne()
    {
        $this->assertSame($this->expected[0], $this->csv->fetchOne(0));
        $this->assertSame($this->expected[1], $this->csv->fetchOne(1));
        $this->assertSame([], $this->csv->fetchOne(35));
        $this->csv->fetchOne(-5);
    }

    public function testGetWriter()
    {
        $this->assertInstanceOf(Writer::class, $this->csv->newWriter());
    }

    /**
     * @dataProvider fetchPairsDataProvider
     */
    public function testFetchPairsIteratorMode($key, $value, $callable, $expected)
    {
        $iterator = $this->csv->fetchPairs($key, $value, $callable);
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
                'callable' => null,
                'expected' => [
                    ['john' => 'doe'],
                    ['jane' => 'doe'],
                ],
            ],
            'changed key order' => [
                'key' => 1,
                'value' => 0,
                'callable' => null,
                'expected' => [
                    ['doe' => 'john'],
                    ['doe' => 'jane'],
                ],
            ],
            'with callback' => [
                'key' => 0,
                'value' => 1,
                'callable' => function ($row) {
                    return [
                        strtoupper($row[0]),
                        strtoupper($row[1]),
                    ];
                },
                'expected' => [
                    ['JOHN' => 'DOE'],
                    ['JANE' => 'DOE'],
                ],
            ],
        ];
    }

    public function testFetchPairsWithInvalidOffset()
    {
        $this->assertCount(0, iterator_to_array($this->csv->fetchPairs(10, 1), true));
    }

    public function testFetchPairsWithInvalidValue()
    {
        $res = $this->csv->fetchPairs(0, 15);
        foreach ($res as $value) {
            $this->assertNull($value);
        }
    }
}
