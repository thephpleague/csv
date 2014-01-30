<?php

namespace Bakame\Csv;

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
        ['jane','doe','jane.doe@example.com'],
    ];

    public function setUp()
    {
        $csv = new SplTempFileObject;
        foreach ($this->expected as $row) {
            $csv->fputcsv($row);
        }

        $this->csv = new Reader($csv);
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
        $func = function ($value) {
            return array_map('strtoupper', $value);
        };
        $res = $this->csv->fetchAssoc($keys, $func);
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

    /**
     * @expectedException InvalidArgumentException
     */
    public function testFetchKeyFailure()
    {
        $this->csv->fetchAssoc([['firstname', 'lastname', 'email', 'age']]);
    }

    public function testFetchCol()
    {
        $this->assertSame(['john', 'jane'], $this->csv->fetchCol(0));
    }

    public function testFetchColEmptyCol()
    {
        $raw = [
            ['john', 'doe'],
            ['lara', 'croft', 'lara.croft@example.com']
        ];

        $file = new SplTempFileObject;
        foreach ($raw as $row) {
            $file->fputcsv($row);
        }
        $csv = new Reader($file);
        $res = $csv->fetchCol(2);
        $this->assertInternalType('array', $res);
        $this->assertCount(2, $res);
        $this->assertNull($res[0][2]);
    }

    public function testFetchColCallback()
    {
        $func = function ($value) {
            return array_map('strtoupper', $value);
        };

        $this->assertSame(['JOHN', 'JANE'], $this->csv->fetchCol(0, $func));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFetchColFailure()
    {
        $this->csv->fetchCol('toto');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSetLimit()
    {
        $res = $this->csv->setLimit(1)->fetchAll();
        $this->assertCount(1, $res);
        $this->assertSame($this->expected[0], $res[0]);
        $this->csv->setLimit(-4);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSetOffset()
    {
        $res = $this->csv->setOffset(1)->fetchAll();
        $this->assertInternalType('array', $res);
        $this->assertCount(1, $res);
        $this->assertSame($this->expected[1], $res[0]);
        $this->csv->setOffset('toto');
    }

    public function testInterval()
    {
        $res = $this->csv->setOffset(0)->setLimit(1)->fetchAll();
        $this->assertCount(1, $res);
        $this->assertSame($this->expected[0], $res[0]);
        $res = $this->csv->setOffset(0)->setLimit(20)->fetchAll();
        $this->assertCount(2, $res);
        $this->assertSame($this->expected, $res);
        $res = $this->csv->setOffset(1)->setLimit(20)->fetchAll();
        $this->assertCount(1, $res);
        $this->assertSame($this->expected[1], $res[0]);
    }

    public function testOffsetGet()
    {
        $this->assertSame($this->expected[0], $this->csv[0]);
        $this->assertSame($this->expected[1], $this->csv[1]);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testOffsetGetFailure()
    {
        $this->csv[-3];
    }

    public function testOffsetExists()
    {
        $this->assertFalse(isset($this->csv[32]));
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testOffsetSet()
    {
        $this->csv[1] = ['toto', 'le', 'herisson'];
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testArrayOffsetUnset()
    {
        unset($this->csv[32]);
    }

    public function testFilter()
    {
        $func = function ($row) {
            return is_array($row) && $row[0] == 'john';
        };
        $res = $this->csv->setFilter($func)->fetchAll();
        $this->assertCount(1, $res);
        $this->assertSame($this->expected[0], $res[0]);
    }

    public function testSortBy()
    {
        $func = function ($row1, $row2) {
            if ($row1[0] == $row2[0]) {
                return 0;
            }

            return ($row1[0] < $row2[0]) ? -1 : 1;
        };
        $res = $this->csv->setSortBy($func)->fetchAll();
        $this->assertCount(2, $res);
        $this->assertSame($this->expected[1], $res[0]);
    }

    public function testGetWriter()
    {
        $writer = $this->csv->getWriter();
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
        $this->assertSame($expected, $writer->toHTML());
    }
}
