<?php

namespace Bakame\Csv;

use SplFileObject;

class ReaderTest extends \PHPUnit_Framework_TestCase
{
    private $csv;

    private $expected = [
        ['john', 'doe', 'john.doe@example.com'],
        ['jane','doe','jane.doe@example.com']
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

    /**
     * @expectedException InvalidArgumentException
     */
    public function testFetchAssoc()
    {
        $func = function ($value) {
            return array_map('strtoupper', $value);
        };
        $keys = ['firstname', 'lastname', 'email'];
        $res = $this->csv->fetchAssoc($keys);
        foreach ($res as $index => $row) {
            $this->assertSame($keys, array_keys($row));
            $this->assertSame($this->expected[$index], array_values($row));
        }
        $res = $this->csv->fetchAssoc($keys, $func);
        foreach ($res as $index => $row) {
            $this->assertSame($keys, array_keys($row));
        }

        $keys = ['firstname'];
        $res = $this->csv->fetchAssoc($keys);
        $this->assertSame([['firstname' => 'john'], ['firstname' => 'jane']], $res);

        $keys = ['firstname', 'lastname', 'email', 'age'];
        $res = $this->csv->fetchAssoc($keys);
        foreach ($res as $index => $row) {
            $this->assertCount(4, array_values($row));
            $this->assertNull($row['age']);
        }
        $this->csv->fetchAssoc([['firstname', 'lastname', 'email', 'age']]);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testFetchCol()
    {
        $func = function ($value) {
            return strtoupper($value);
        };

        $this->assertSame(['john', 'jane'], $this->csv->fetchCol(0));
        $this->assertSame(['JOHN', 'JANE'], $this->csv->fetchCol(0, $func));
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
        $expected = "john,doe,john.doe@example.com".PHP_EOL."jane,doe,jane.doe@example.com".PHP_EOL;
        $this->assertSame($expected, $this->csv->__toString());
    }
}
