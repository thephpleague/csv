<?php

namespace Bakame\Csv;

use SplFileObject;

class ReaderTest extends \PHPUnit_Framework_TestCase
{
    private $csv;

    private $expected = [
        ['john', 'doe', 'john.doe@example.com'],
        ['jane','doe','jane.doe@example.com'],
    ];

    public function setUp()
    {
        $codec = new Codec;
        $this->csv = $codec->save($this->expected, new SplFileObject('php://temp'));
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testFetchOne()
    {
        $this->assertSame($this->expected[0], $this->csv->fetchOne(0));
        $this->csv->fetchOne(-3);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testFetchValue()
    {
        $this->assertSame($this->expected[0][2], $this->csv->fetchValue(0, 2));
        $this->assertNull($this->csv->fetchValue(0, 23));
        $this->assertNull($this->csv->fetchValue(8, 23));
        $this->csv->fetchValue(8, 'toto');
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

    public function testFetchColCallback()
    {
        $func = function ($value) {
            return strtoupper($value);
        };

        $this->assertSame(['JOHN', 'JANE'], $this->csv->fetchCol(0, $func));
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testFetchColFailure()
    {
        $this->csv->fetchCol('toto');
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testSetFlags()
    {
        $this->csv->setFlags(SplFileObject::SKIP_EMPTY);
        $this->assertSame(SplFileObject::SKIP_EMPTY, $this->csv->getFlags() & SplFileObject::SKIP_EMPTY);
        $this->assertSame(SplFileObject::READ_CSV, $this->csv->getFlags() & SplFileObject::READ_CSV);
        $this->csv->setFlags(-3);
    }

    public function testToString()
    {
        $expected = "john,doe,john.doe@example.com".PHP_EOL
            ."jane,doe,jane.doe@example.com".PHP_EOL;
        $this->assertSame($expected, $this->csv->__toString());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailureSettingOffset()
    {
        $this->csv->setOffset(3);
        $this->assertSame(3, $this->csv->getOffset());
        $this->csv->setOffset('toto');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailureSettingLimit()
    {
        $this->csv->setLimit(3);
        $this->assertSame(3, $this->csv->getLimit());
        $this->csv->setLimit(-4);
    }

    public function testSetLimit()
    {
        $res = $this->csv->setLimit(1)->fetchAll();
        $this->assertCount(1, $res);
        $this->assertSame($this->expected[0], $res[0]);
        $this->assertSame(0, $this->csv->getOffset());
        $this->assertSame(0, $this->csv->getLimit());
    }

    public function testSetOffset()
    {
        $res = $this->csv->setOffset(1)->fetchAll();
        $this->assertCount(1, $res);
        $this->assertSame($this->expected[1], $res[0]);
        $this->assertSame(0, $this->csv->getOffset());
        $this->assertSame(0, $this->csv->getLimit());
    }

    public function testFetchFilters()
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
}
