<?php

namespace Bakame\Csv;

use SplFileObject;

class ReaderTest extends \PHPUnit_Framework_TestCase
{
    private $reader;

    private $expected = [['foo', 'bar', '  baz '], ['foo','bar',' baz  ']];

    public function setUp()
    {
        $codec = new Codec;
        $this->reader = $codec->save($this->expected, new SplFileObject('php://temp'));
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testFetchOne()
    {
        $this->assertSame($this->expected[0], $this->reader->fetchOne(0));
        $this->reader->fetchOne(-3);
    }

    public function testFetchValue()
    {
        $this->assertSame($this->expected[0][2], $this->reader->fetchValue(0, 2));
        $this->assertNull($this->reader->fetchValue(0, 23));
        $this->assertNull($this->reader->fetchValue(8, 23));
    }

    public function testFetchAll()
    {
        $func = function ($value) {
            return array_map('strtoupper', $value);
        };

        $this->assertSame($this->expected, $this->reader->fetchAll());
        $this->assertSame(array_map($func, $this->expected), $this->reader->fetchAll($func));
    }

    public function testFetchAssoc()
    {
        $func = function ($value) {
            return array_map('strtoupper', $value);
        };
        $keys = ['firstname', 'lastname', 'pseudo'];
        $res = $this->reader->fetchAssoc($keys);
        foreach ($res as $index => $row) {
            $this->assertSame($keys, array_keys($row));
            $this->assertSame($this->expected[$index], array_values($row));
        }
        $res = $this->reader->fetchAssoc($keys, $func);
        foreach ($res as $index => $row) {
            $this->assertSame($keys, array_keys($row));
        }

        $keys = ['firstname'];
        $res = $this->reader->fetchAssoc($keys);
        $this->assertSame([['firstname' => 'foo'], ['firstname' => 'foo']], $res);

        $keys = ['firstname', 'lastname', 'email', 'age'];
        $res = $this->reader->fetchAssoc($keys);
        foreach ($res as $index => $row) {
            $this->assertCount(4, array_values($row));
            $this->assertNull($row['age']);
        }

    }

    public function testFetchCol()
    {
        $func = function ($value) {
            return strtoupper($value);
        };

        $this->assertSame(['foo', 'foo'], $this->reader->fetchCol(0));
        $this->assertSame(['FOO', 'FOO'], $this->reader->fetchCol(0, $func));
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testSetFlags()
    {
        $this->reader->setFlags(SplFileObject::SKIP_EMPTY);
        $this->assertSame(SplFileObject::SKIP_EMPTY, $this->reader->getFlags() & SplFileObject::SKIP_EMPTY);
        $this->assertSame(SplFileObject::READ_CSV, $this->reader->getFlags() & SplFileObject::READ_CSV);
        $this->reader->setFlags(-3);
    }
}
