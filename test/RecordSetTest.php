<?php

namespace League\Csv\Test;

use Countable;
use DOMDocument;
use Iterator;
use IteratorAggregate;
use JsonSerializable;
use League\Csv\InvalidRowException;
use League\Csv\Reader;
use League\Csv\Statement;
use PHPUnit\Framework\TestCase;
use SplTempFileObject;

/**
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

    private $vfs;

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
        $this->vfs = null;
        $this->vfs_dir = null;
    }

    public function testCountable()
    {
        $this->assertCount(2, $this->csv->select());
        $this->assertCount(1, $this->csv->select($this->stmt->setLimit(1)));
    }

    public function testRecordsReturnType()
    {
        $res = $this->csv->setHeader(0)->select();
        $this->assertInstanceOf(JsonSerializable::class, $res);
        $this->assertInstanceof(IteratorAggregate::class, $res);
        $this->assertInstanceof(Countable::class, $res);
        $this->assertInternalType('array', $res->fetchAll());
        $this->assertInternalType('array', $res->fetchOne());
        $this->assertInternalType('array', $res->getHeader());
        $this->assertInstanceof(Iterator::class, $res->fetchColumn());
        $this->assertInstanceof(Iterator::class, $res->fetchPairs());
        $this->assertInstanceOf(DOMDocument::class, $res->toXML());
        $this->assertContains('<table', $res->toHTML());
    }

    public function testJsonSerialize()
    {
        $this->assertSame($this->expected, json_decode(json_encode($this->csv->select()), true));
    }

    /**
     * @param $rawCsv
     *
     * @dataProvider getIso8859Csv
     */
    public function testJsonSerializeAffectedByReaderOptions($rawCsv)
    {
        $csv = Reader::createFromString($rawCsv);
        $csv->setInputEncoding('iso-8859-15');
        $csv->setHeader(0);
        $stmt = (new Statement())->setOffset(799)->setLimit(50);
        json_encode($csv->select($stmt));
        $this->assertEquals(JSON_ERROR_NONE, json_last_error());
    }

    public static function getIso8859Csv()
    {
        return [[file_get_contents(__DIR__.'/data/prenoms.csv')]];
    }

    public function testSetLimit()
    {
        $this->assertCount(1, $this->csv->select($this->stmt->setLimit(1)));
    }

    public function testSetOffset()
    {
        $this->assertContains(
            ['jane', 'doe', 'jane.doe@example.com'],
            $this->csv->select($this->stmt->setOffset(1))
        );
    }

    /**
     * @dataProvider intervalTest
     */
    public function testInterval($offset, $limit, $expected)
    {
        $stmt = $this->stmt
            ->setOffset($offset)
            ->setLimit($limit)
        ;

        $this->assertContains(
            ['jane', 'doe', 'jane.doe@example.com'],
            $this->csv->select($stmt)
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
        $stmt = $this->stmt
            ->setOffset(1)
            ->setLimit(0)
        ;

        $this->expectException(\OutOfBoundsException::class);
        $this->csv->select($stmt)->fetchAll();
    }


    public function testFilter()
    {
        $func = function ($row) {
            return !in_array('jane', $row);
        };

        $this->assertNotContains(
            ['jane', 'doe', 'jane.doe@example.com'],
            $this->csv->select($this->stmt->addFilter($func))
        );
    }

    public function testSortBy()
    {
        $func = function ($rowA, $rowB) {
            return strcmp($rowA[0], $rowB[0]);
        };

        $this->assertSame(
            array_reverse($this->expected),
            $this->csv->select($this->stmt->addSortBy($func))->fetchAll()
        );
    }

    public function testFetchAssoc()
    {
        $keys = ['firstname', 'lastname', 'email'];
        $this->csv->setHeader($keys);
        $res = $this->csv->select()->fetchAll();
        foreach ($res as $offset => $row) {
            $this->assertSame($keys, array_keys($row));
        }
    }

    public function testFetchAssocLessKeys()
    {
        $this->csv->setHeader(['firstname']);
        $this->expectException(InvalidRowException::class);
        $res = $this->csv->select()->fetchAll();
    }

    public function testFetchAssocMoreKeys()
    {
        $this->csv->setHeader(['firstname', 'lastname', 'email', 'age']);
        $this->expectException(InvalidRowException::class);
        $this->csv->select()->fetchAll();
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
        $res = $csv->select($this->stmt->setOffSet(2));
        $this->assertContains(['D' => '6', 'E' => '7', 'F' => '8'], $res);
    }

    /**
     * @dataProvider validBOMSequences
     */
    public function testAutoRemoveBOM($data, $first_cell)
    {
        $tmpFile = new SplTempFileObject();
        $tmpFile->setCsvControl(';');
        foreach ($data as $row) {
            $tmpFile->fputcsv($row);
        }
        $csv = Reader::createFromFileObject($tmpFile);
        $this->assertSame($first_cell, $csv->select()->fetchAll()[0][0]);
    }

    public function validBOMSequences()
    {
        return [
            'withBOM' => [
                'data' => [
                    [Reader::BOM_UTF16_LE.'john', 'doe', 'john.doe@example.com'],
                    ['jane', 'doe', 'jane.doe@example.com'],
                ],
                'first_cell' => 'john',
            ],
            'withDoubleBOM' => [
                'data' => [
                    [Reader::BOM_UTF16_LE.Reader::BOM_UTF16_LE.'john', 'doe', 'john.doe@example.com'],
                    ['jane', 'doe', 'jane.doe@example.com'],
                ],
                'first_cell' => Reader::BOM_UTF16_LE.'john',
            ],
            'withoutBOM' => [
                'data' => [
                    ['john', 'doe', 'john.doe@example.com'],
                    ['jane', 'doe', 'jane.doe@example.com'],
                ],
                'first_cell' => 'john',
            ],
        ];
    }

    public function testFetchAssocWithBOM()
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
        $res = array_keys($csv->select()->fetchAll()[0]);

        $this->assertSame('john', $res[0]);
    }

    public function testWithEnclosureFetchAssoc()
    {
        $source = Reader::BOM_UTF8.'"parent name","child name","title"
            "parentA","childA","titleA"';
        $csv = Reader::createFromString($source)->setHeader(0);
        $expected = [[
            'parent name' => 'parentA',
            'child name' => 'childA',
            'title' => 'titleA',
        ]];

        $this->assertSame($expected, $csv->select()->fetchAll());
    }


    public function testWithEnclosureFetchAssocBis()
    {
        $source = '"parent name","child name","title"
            "parentA","childA","titleA"';
        $csv = Reader::createFromString($source)->setHeader(1);
        $expected = [[
            'parentA' => 'parent name',
            'childA' => 'child name',
            'titleA' => 'title',
        ]];

        $this->assertSame($expected, $csv->select()->fetchAll());
    }


    public function testFetchColumnWithBOM()
    {
        $source = Reader::BOM_UTF8.'"parent name","child name","title"
            "parentA","childA","titleA"';
        $csv = Reader::createFromString($source);
        $this->assertContains('parent name', $csv->select()->fetchColumn());
    }

    public function testFetchAllWithBOM()
    {
        $source = Reader::BOM_UTF8.'"parent name","child name","title"
            "parentA","childA","titleA"';
        $csv = Reader::createFromString($source);
        $this->assertContains(['parent name', 'child name', 'title'], $csv->select());
    }

    public function testFetchOneWithBOM()
    {
        $source = Reader::BOM_UTF8.'"parent name","child name","title"
            "parentA","childA","titleA"';
        $csv = Reader::createFromString($source);
        $this->assertSame(Reader::BOM_UTF8, $csv->getInputBOM());
        $expected = ['parent name', 'child name', 'title'];
        $this->assertEquals($expected, $csv->select()->fetchOne());
    }

    public function testFetchColumn()
    {
        $res = $this->csv->select();
        $this->assertContains('john', $res->fetchColumn(0));
        $this->assertContains('jane', $res->fetchColumn());
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
        $this->assertCount(1, $csv->select()->fetchColumn(2));
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
        $this->assertCount(0, $csv->select()->fetchColumn(2));
    }

    public function testFetchColumnFailure()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->csv->select()->fetchColumn('toto');
    }

    public function testFetchOne()
    {
        $res = $this->csv->select();
        $this->assertSame($this->expected[0], $res->fetchOne());
        $this->assertSame($this->expected[1], $this->csv->select($this->stmt->setOffset(1))->fetchOne());
        $this->assertSame([], $this->csv->select($this->stmt->setOffset(35))->fetchOne());
    }

    /**
     * @dataProvider fetchPairsDataProvider
     */
    public function testFetchPairs($key, $value, $expected)
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
        $this->assertCount(0, iterator_to_array($this->csv->select()->fetchPairs(10, 1)));
    }

    public function testFetchPairsWithInvalidValue()
    {
        $res = $this->csv->select()->fetchPairs(0, 15);
        foreach ($res as $value) {
            $this->assertNull($value);
        }
    }

    public function testTranscodeUsingIconvStreamFilter()
    {
        $csv = Reader::createFromPath(__DIR__.'/data/prenoms.csv');
        $csv->setInputEncoding('ISO-8859-1');
        $csv->setDelimiter(';');
        $row = $csv->select()->fetchOne(6);
        $this->assertNotSame('ISO-8859-1', mb_detect_encoding($row[0], 'auto'));
    }

    public function testFetchColumnWithHeader()
    {
        $csv = Reader::createFromPath(__DIR__.'/data/prenoms.csv');
        $csv->setInputEncoding('ISO-8859-1');
        $csv->setDelimiter(';');
        $csv->setHeader(0);
        $res = $csv->select($this->stmt->setOffset(750)->setLimit(2));
        $this->assertEquals($res->fetchColumn('prenoms'), $res->fetchColumn(0));
    }

    public function testFetchColumnWithHeaderThrowsException()
    {
        $csv = Reader::createFromPath(__DIR__.'/data/prenoms.csv');
        $csv->setInputEncoding('ISO-8859-1');
        $csv->setDelimiter(';');
        $csv->setHeader(0);
        $res = $csv->select($this->stmt->setOffset(750)->setLimit(2));
        $this->expectException(\InvalidArgumentException::class);
        $res->fetchColumn(23);
    }
}
