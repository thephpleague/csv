<?php

namespace League\Csv\test;

use League\Csv\Reader;
use PHPUnit_Framework_TestCase;
use SplFileObject;
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
        $csv = new SplTempFileObject();
        foreach ($this->expected as $row) {
            $csv->fputcsv($row);
        }

        $this->csv = Reader::createFromFileObject($csv);
        $this->csv->setFlags(SplFileObject::READ_AHEAD|SplFileObject::SKIP_EMPTY);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage the limit must an integer greater or equals to -1
     */
    public function testSetLimit()
    {
        $this->csv->setLimit(1);
        $this->assertCount(1, $this->csv->fetchAll());
        $this->csv->setLimit(-4);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage the offset must be a positive integer or 0
     */
    public function testSetOffset()
    {
        $this->csv->setOffset(1);
        $this->assertCount(1, $this->csv->fetchAll());

        $this->csv->setOffset('toto');
    }

    public function testIntervalLimitTooLong()
    {
        $this->csv->setOffset(1);
        $this->csv->setLimit(10);
        $this->assertSame([['jane', 'doe', 'jane.doe@example.com']], $this->csv->fetchAll());
    }

    public function testInterval()
    {
        $this->csv->setOffset(1);
        $this->csv->setLimit(1);
        $this->assertCount(1, $this->csv->fetchAll());
    }

    public function testFilter()
    {
        $func = function ($row) {
            return ! in_array('jane', $row);
        };
        $this->csv->addFilter($func);

        $this->assertCount(1, $this->csv->fetchAll());

        $func2 = function ($row) {
            return ! in_array('john', $row);
        };
        $this->csv->addFilter($func2);
        $this->csv->addFilter($func);

        $this->assertCount(0, $this->csv->fetchAll());

        $this->csv->addFilter($func2);
        $this->csv->addFilter($func);
        $this->assertTrue($this->csv->hasFilter($func2));
        $this->csv->removeFilter($func2);
        $this->assertFalse($this->csv->hasFilter($func2));

        $this->assertCount(1, $this->csv->fetchAll());
    }

    public function testSortBy()
    {
        $func = function ($rowA, $rowB) {
            return strcmp($rowA[0], $rowB[0]);
        };
        $this->csv->addSortBy($func);
        $this->assertSame(array_reverse($this->expected), $this->csv->fetchAll());

        $this->csv->addSortBy($func);
        $this->csv->addSortBy($func);
        $this->csv->removeSortBy($func);
        $this->assertTrue($this->csv->hasSortBy($func));
        $this->assertSame(array_reverse($this->expected), $this->csv->fetchAll());
    }

    public function testSortBy2()
    {
        $string = 'john,doe,john.doe@example.com'.PHP_EOL.'john,doe,john.doe@example.com';
        $csv = Reader::createFromString($string);
        $csv->setFlags(SplFileObject::READ_AHEAD|SplFileObject::SKIP_EMPTY);
        $func = function ($rowA, $rowB) {
            return strcmp($rowA[0], $rowB[0]);
        };
        $csv->addSortBy($func);
        $this->assertSame([
            ['john', 'doe', 'john.doe@example.com'],
            ['john', 'doe', 'john.doe@example.com'],
        ], $csv->fetchAll());
    }

    public function testFetchAll()
    {
        $func = function ($value) {
            return array_map('strtoupper', $value);
        };

        $this->assertSame($this->expected, $this->csv->fetchAll());
        $this->assertSame(array_map($func, $this->expected), $this->csv->fetchAll($func));
    }

    public function testFetchAssoc()
    {
        $keys = ['firstname', 'lastname', 'email'];
        $res = $this->csv->fetchAssoc($keys);
        foreach ($res as $index => $row) {
            $this->assertSame($keys, array_keys($row));
            $this->assertSame($this->expected[$index], array_values($row));
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
    }

    public function testFetchAssocLessKeys()
    {
        $keys = ['firstname'];
        $res = $this->csv->fetchAssoc($keys);
        $this->assertSame([['firstname' => 'john'], ['firstname' => 'jane']], $res);
    }

    public function testFetchAssocMoreKeys()
    {
        $keys = ['firstname', 'lastname', 'email', 'age'];
        $res = $this->csv->fetchAssoc($keys);

        foreach ($res as $row) {
            $this->assertCount(4, array_values($row));
            $this->assertNull($row['age']);
        }
    }

    public function testFetchAssocWithRowIndex()
    {
        $arr = [
            ['A', 'B', 'C'],
            [1, 2, 3],
            ['D', 'E', 'F'],
            [6, 7, 8],
        ];

        $tmpFile = new SplTempFileObject();
        foreach ($arr as $row) {
            $tmpFile->fputcsv($row);
        }

        $csv = Reader::createFromFileObject($tmpFile);
        $csv->setFlags(SplFileObject::READ_AHEAD|SplFileObject::SKIP_EMPTY);
        $res = $csv->setOffSet(2)->fetchAssoc(2);
        $this->assertSame([['D' => '6', 'E' => '7', 'F' => '8']], $res);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Use a flat non empty array with unique string values
     */
    public function testFetchAssocKeyFailure()
    {
        $this->csv->fetchAssoc([['firstname', 'lastname', 'email', 'age']]);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage the row index must be a positive integer or 0
     */
    public function testFetchAssocWithInvalidKey()
    {
        $arr = [
            ['A', 'B', 'C'],
            [1, 2, 3],
            ['D', 'E', 'F'],
            [6, 7, 8],
        ];

        $tmpFile = new SplTempFileObject();
        foreach ($arr as $row) {
            $tmpFile->fputcsv($row);
        }

        $csv = Reader::createFromFileObject($tmpFile);
        $csv->fetchAssoc(-23);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage the specified row does not exist
     */
    public function testFetchAssocWithInvalidOffset()
    {
        $arr = [
            ['A', 'B', 'C'],
            [1, 2, 3],
            ['D', 'E', 'F'],
            [6, 7, 8],
        ];

        $tmpFile = new SplTempFileObject();
        foreach ($arr as $row) {
            $tmpFile->fputcsv($row);
        }

        $csv = Reader::createFromFileObject($tmpFile);
        $csv->fetchAssoc(23);
    }

    public function testFetchCol()
    {
        $this->assertSame(['john', 'jane'], $this->csv->fetchColumn(0));
        $this->assertSame(['john', 'jane'], $this->csv->fetchColumn());
    }

    public function testFetchColInconsistentColumnCSV()
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
        $csv->setFlags(SplFileObject::READ_AHEAD|SplFileObject::SKIP_EMPTY);
        $res = $csv->fetchColumn(2);
        $this->assertInternalType('array', $res);
        $this->assertCount(1, $res);
    }

    public function testFetchColEmptyCol()
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
        $csv->setFlags(SplFileObject::READ_AHEAD|SplFileObject::SKIP_EMPTY);
        $res = $csv->fetchColumn(2);
        $this->assertInternalType('array', $res);
        $this->assertCount(0, $res);
    }


    public function testFetchColCallback()
    {
        $func = function ($value) {
            return array_map('strtoupper', $value);
        };

        $this->assertSame(['JOHN', 'JANE'], $this->csv->fetchColumn(0, $func));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage the column index must be a positive integer or 0
     */
    public function testFetchColFailure()
    {
        $this->csv->fetchColumn('toto');
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage the offset must be a positive integer or 0
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
        $res = $this->csv->each(function ($row) use (&$transform) {
            $transform[] = array_map('strtoupper', $row);

            return true;
        });
        $this->assertSame($res, 2);
        $this->assertSame(strtoupper($this->expected[0][0]), $transform[0][0]);
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
        $writer = $this->csv->newWriter();
        $writer->insertOne(['toto', 'le', 'herisson']);
        $expected = <<<EOF
<table class="table-csv-data">
<tr>
<td>john</td>
<td>doe</td>
<td>john.doe@example.com</td>
</tr>
<tr>
<td>jane</td>
<td>doe</td>
<td>jane.doe@example.com</td>
</tr>
<tr>
<td>toto</td>
<td>le</td>
<td>herisson</td>
</tr>
</table>
EOF;
        $writer->setFlags(SplFileObject::READ_AHEAD|SplFileObject::SKIP_EMPTY);
        $this->assertSame($expected, $writer->toHTML());
    }

    public function testGetWriter2()
    {
        $csv = Reader::createFromPath(__DIR__.'/foo.csv')->newWriter('a+');
        $this->assertInstanceOf('\League\Csv\Writer', $csv);
    }
}
