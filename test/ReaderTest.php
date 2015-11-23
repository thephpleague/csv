<?php

namespace League\Csv\test;

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
     * @expectedException \InvalidArgumentException
     */
    public function testSetOffsetThrowException()
    {
        $this->csv->setOffset('toto');
    }

    /**
     * @param $offset
     * @param $limit
     * @param $expected
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
            return ! in_array('jane', $row);
        };
        $this->csv->addFilter($func);
        $this->assertNotContains(['jane', 'doe', 'jane.doe@example.com'], $this->csv->fetchAll());

        $func2 = function ($row) {
            return ! in_array('john', $row);
        };
        $this->csv->addFilter($func2);
        $this->csv->addFilter($func);
        $res = $this->csv->fetchAll();

        $this->assertNotContains(['john', 'doe', 'john.doe@example.com'], $res);
        $this->assertNotContains(['jane', 'doe', 'jane.doe@example.com'], $res);

        $this->csv->addFilter($func2);
        $this->csv->addFilter($func);
        $this->assertTrue($this->csv->hasFilter($func2));
        $this->csv->removeFilter($func2);
        $this->assertFalse($this->csv->hasFilter($func2));

        $this->assertContains(['john', 'doe', 'john.doe@example.com'], $this->csv->fetchAll());
    }

    public function testSortBy()
    {
        $func = function ($rowA, $rowB) {
            return strcmp($rowA[0], $rowB[0]);
        };
        $this->csv->addSortBy($func);
        $this->csv->addFilter(function ($row) {
            return $row != [null];

        });
        $this->assertSame(array_reverse($this->expected), $this->csv->fetchAll());

        $this->csv->addSortBy($func);
        $this->csv->addSortBy($func);
        $this->csv->removeSortBy($func);
        $this->assertTrue($this->csv->hasSortBy($func));
        $this->csv->addFilter(function ($row) {
            return $row != [null];

        });
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

    public function testFetchAssocReturnsArray()
    {
        $keys = ['firstname', 'lastname', 'email'];
        $res = $this->csv->fetchAssoc($keys);
        $this->assertInternalType('array', $res);
        foreach ($res as $offset => $row) {
            $this->assertSame($keys, array_keys($row));
        }
    }

    public function testFetchAssocReturnsIterator()
    {
        $keys = ['firstname', 'lastname', 'email'];
        $res = $this->csv->setFetchMode(Reader::FETCH_ITERATOR)->fetchAssoc($keys);
        $this->assertInstanceof('\Iterator', $res);
        foreach ($res as $offset => $row) {
            $this->assertSame($keys, array_keys($row));
        }
    }

    public function testFetchAssocCallback()
    {
        $keys = ['firstname', 'lastname', 'email'];
        $res = $this->csv->fetchAssoc($keys, function ($value) {
            return array_map('strtoupper', $value);
        });
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
        $res = $this->csv->fetchAssoc($keys);
        $this->assertContains(['firstname' => 'john'], $res);
    }

    public function testFetchAssocMoreKeys()
    {
        $keys = ['firstname', 'lastname', 'email', 'age'];
        $res = $this->csv->fetchAssoc($keys);

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
        $res = $csv->setOffSet(2)->fetchAssoc(2);
        $this->assertContains(['D' => '6', 'E' => '7', 'F' => '8'], $res);
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
        $csv->stripBom(true);

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
        $csv->stripBom(true);
        $res = array_keys($csv->fetchAssoc()[0]);

        $this->assertSame('john', $res[0]);
    }

    public function testStripBOMWithEnclosureFetchAssoc()
    {
        $expected = ['parent name', 'parentA'];
        $source = Reader::BOM_UTF8.'"parent name","child name","title"
            "parentA","childA","titleA"';
        $csv = Reader::createFromString($source);
        $csv->stripBom(true);
        $expected = [
            ['parent name' => 'parentA', 'child name' => 'childA', 'title' => 'titleA'],
        ];
        $this->assertSame($expected, $csv->fetchAssoc());
    }

    public function testStripBOMWithEnclosureFetchColumn()
    {
        $source = Reader::BOM_UTF8.'"parent name","child name","title"
            "parentA","childA","titleA"';
        $csv = Reader::createFromString($source);
        $csv->stripBom(true);
        $this->assertContains('parent name', $csv->fetchColumn());
    }

    public function testStripBOMWithEnclosureFetchAll()
    {
        $source = Reader::BOM_UTF8.'"parent name","child name","title"
            "parentA","childA","titleA"';
        $csv = Reader::createFromString($source);
        $csv->stripBom(true);
        $this->assertContains(['parent name', 'child name', 'title'], $csv->fetchAll());
    }

    public function testStripBOMWithEnclosureFetchOne()
    {
        $source = Reader::BOM_UTF8.'"parent name","child name","title"
            "parentA","childA","titleA"';
        $csv = Reader::createFromString($source);
        $csv->stripBom(true);
        $this->assertSame(Reader::BOM_UTF8, $csv->getInputBom());
        $expected = ['parent name', 'child name', 'title'];
        $this->assertEquals($expected, $csv->fetchOne());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFetchAssocKeyFailure()
    {
        $this->csv->fetchAssoc([['firstname', 'lastname', 'email', 'age']]);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFetchAssocKeyFailureWithEmptyArray()
    {
        $this->csv->fetchAssoc([]);
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

        Reader::createFromFileObject($tmp)->fetchAssoc($offset);
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

    public function testFetchColumnReturnsIterator()
    {
        $this->assertContains('john', $this->csv->setFetchMode(Reader::FETCH_ITERATOR)->fetchColumn(0));
        $this->assertContains('jane', $this->csv->setFetchMode(Reader::FETCH_ITERATOR)->fetchColumn());
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
        $this->assertSame(['JOHN', 'JANE'], $iterator);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFetchColumnFailure()
    {
        $this->csv->fetchColumn('toto');
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

    public function testEach()
    {
        $transform = [];
        $this->csv->addFilter(function ($row) {
            return $row != [null];

        });
        $res = $this->csv->each(function ($row) use (&$transform) {
            $transform[] = array_map('strtoupper', $row);

            return true;
        });
        $this->assertSame($res, 2);
        $this->assertSame(strtoupper($this->expected[0][0]), $transform[0][0]);
    }

    public function testEachWithEarlyReturns()
    {
        $res = $this->csv->each(function ($row, $index) {
            if ($index > 0) {
                return false;
            }

            return true;
        });
        $this->assertSame($res, 1);
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
        $iterator = $this->csv->setFetchMode(Reader::FETCH_ITERATOR)->fetchPairs($key, $value, $callable);
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

    /**
     * @dataProvider fetchPairsArrayDataProvider
     */
    public function testFetchPairsArrayMode($key, $value, $callable, $expected)
    {
        $array = $this->csv->fetchPairs($key, $value, $callable);
        $this->assertSame($expected, $array);
    }

    public function fetchPairsArrayDataProvider()
    {
        return [
            'default values' => [
                'key' => 0,
                'value' => 1,
                'callable' => null,
                'expected' => ['john' => 'doe', 'jane' => 'doe'],
            ],
            'changed key order' => [
                'key' => 1,
                'value' => 0,
                'callable' => null,
                'expected' => ['doe' => 'jane'],
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
                'expected' => ['JOHN' => 'DOE', 'JANE' => 'DOE'],
            ],
        ];
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFetchMode()
    {
        $this->assertSame(Reader::FETCH_ARRAY, $this->csv->getFetchMode());
        $this->csv->setFetchMode(Reader::FETCH_ITERATOR);
        $this->assertSame(Reader::FETCH_ITERATOR, $this->csv->getFetchMode());
        $this->csv->setFetchMode('toto');
    }
}
