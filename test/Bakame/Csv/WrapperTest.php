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
     * @expectedException Bakame\Csv\WrapperException
     */
    public function testDelimeter()
    {
        $this->wrapper->setDelimiter('o');
        $this->assertSame('o', $this->wrapper->getDelimiter());

        $this->wrapper->setDelimiter('foo');
    }

    /**
     * @expectedException Bakame\Csv\WrapperException
     */
    public function testEscape()
    {
        $this->wrapper->setEscape('o');
        $this->assertSame('o', $this->wrapper->getEscape());

        $this->wrapper->setEscape('foo');
    }

    /**
     * @expectedException Bakame\Csv\WrapperException
     */
    public function testEnclosure()
    {
        $this->wrapper->setEnclosure('o');
        $this->assertSame('o', $this->wrapper->getEnclosure());

        $this->wrapper->setEnclosure('foo');
    }

    public function testloadString()
    {
        $str = "foo,bar,baz\nfoo,bar,baz";
        $res = $this->wrapper->loadString($str);
        $this->assertInstanceof('SplTempFileObject', $res);
        foreach ($res as $row) {
            $this->assertSame(['foo', 'bar', 'baz'], $row);
        }
    }

    public function testloadFile()
    {
        $file = __DIR__.'/foo.csv';
        $res = $this->wrapper->loadFile($file);
        $this->assertInstanceof('SplFileObject', $res);
        $this->assertSame($file, $res->getRealPath());
        foreach ($res as $row) {
            array_walk($row, function (&$value) {
                $value = trim($value);
            });
            $this->assertSame(['foo', 'bar', 'baz'], $row);
        }
    }

    public function testSaveArray()
    {
        $arr = [
            ['foo', 'bar', '  baz '],
            'foo,bar, baz  ',
        ];
        $this->wrapper
            ->setDelimiter(',')
            ->setEnclosure('"')
            ->setEscape("\\");

        $res = $this->wrapper->save($arr, 'php://temp');
        $this->assertInstanceof('SplFileObject', $res);
        foreach ($res as $row) {
            $this->assertSame(['foo', 'bar', 'baz'], $row);
        }
    }

    public function testSaveTransversable()
    {
        $arr = [
            ['foo', 'bar', '  baz '],
            'foo,bar, baz  ',
        ];
        $obj = new \ArrayObject($arr);
        $res = $this->wrapper->save($obj, 'php://temp');
        $this->assertInstanceof('\SplFileObject', $res);
        foreach ($res as $row) {
            $this->assertSame(['foo', 'bar', 'baz'], $row);
        }
    }

    /**
     * @expectedException Bakame\Csv\WrapperException
     */
    public function testSaveExceptionBadData()
    {
        $this->wrapper->save('foo', 'php://temp');
    }

    /**
     * @expectedException Bakame\Csv\WrapperException
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
