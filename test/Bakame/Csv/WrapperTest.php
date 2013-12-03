<?php

namespace Bakame\Csv;

use SplFileObject;
use SplFileInfo;

class WrapperTest extends \PHPUnit_Framework_TestCase
{

    private $wrapper;

    public function setUp()
    {
        $this->wrapper = new Wrapper;
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testDelimeter()
    {
        $this->wrapper->setDelimiter('o');
        $this->assertSame('o', $this->wrapper->getDelimiter());

        $this->wrapper->setDelimiter('foo');
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testEscape()
    {
        $this->wrapper->setEscape('o');
        $this->assertSame('o', $this->wrapper->getEscape());

        $this->wrapper->setEscape('foo');
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testEnclosure()
    {
        $this->wrapper->setEnclosure('o');
        $this->assertSame('o', $this->wrapper->getEnclosure());

        $this->wrapper->setEnclosure('foo');
    }

    public function testloadString()
    {
        $expected = ['foo', 'bar', 'baz'];
        $str = "foo,bar,baz\nfoo,bar,baz";
        $res = $this->wrapper->loadString($str);
        $this->assertInstanceof('SplTempFileObject', $res);
        foreach ($res as $row) {
            $this->assertSame($expected, $row);
        }
    }

    public function testloadFile()
    {
        $expected = ['foo', 'bar', 'baz'];
        $file = __DIR__.'/foo.csv';
        $res = $this->wrapper->loadFile($file);
        $this->assertInstanceof('SplFileObject', $res);
        $this->assertSame($file, $res->getRealPath());
        $res->setFlags(SplFileObject::READ_CSV|SplFileObject::READ_AHEAD|SplFileObject::SKIP_EMPTY);
        foreach ($res as $row) {
            array_walk($row, function (&$value) {
                $value = trim($value);
            });
            $this->assertSame($expected, $row);
        }
    }

    public function testSaveArray()
    {
        $arr = [
            ['foo', 'bar', '  baz '],
            'foo,bar, baz  ',
        ];
        $expected = ['foo', 'bar', 'baz'];
        $this->wrapper
            ->setDelimiter(',')
            ->setEnclosure('"')
            ->setEscape("\\");

        $res = $this->wrapper->save($arr, 'php://temp');
        $this->assertInstanceof('SplFileObject', $res);
        foreach ($res as $row) {
            $this->assertSame($expected, $row);
        }
    }

    public function testSaveTransversable()
    {
        $arr = [
            ['foo', 'bar', '  baz '],
            'foo,bar, baz  ',
        ];
        $expected = ['foo', 'bar', 'baz'];
        $obj = new \ArrayObject($arr);
        $res = $this->wrapper->save($obj, 'php://temp');
        $this->assertInstanceof('\SplFileObject', $res);
        foreach ($res as $row) {
            $this->assertSame($expected, $row);
        }
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testSaveExceptionBadData()
    {
        $this->wrapper->save('foo', 'php://temp');
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testSaveExceptionBadMode()
    {
        $this->wrapper->save('foo', 'php://temp', 'x');
    }

    /**
     * @expectedException RuntimeException
     */
    public function testSaveExceptionBadPath()
    {
        $this->wrapper->save(['foo'], ['bar']);
    }

    public function testSaveSplFileInfo()
    {
        $obj = new SplFileInfo('php://temp');
        $res = $this->wrapper->save(['foo'], $obj);
        $this->assertInstanceof('\SplFileObject', $res);
    }
}
