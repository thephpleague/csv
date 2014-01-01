<?php

namespace Bakame\Csv;

use SplFileObject;
use SplFileInfo;

class CodecTest extends \PHPUnit_Framework_TestCase
{

    private $codec;

    public function setUp()
    {
        $this->codec = new Codec;
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testDelimeter()
    {
        $this->codec->setDelimiter('o');
        $this->assertSame('o', $this->codec->getDelimiter());

        $this->codec->setDelimiter('foo');
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testEscape()
    {
        $this->codec->setEscape('o');
        $this->assertSame('o', $this->codec->getEscape());

        $this->codec->setEscape('foo');
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testEnclosure()
    {
        $this->codec->setEnclosure('o');
        $this->assertSame('o', $this->codec->getEnclosure());

        $this->codec->setEnclosure('foo');
    }

    public function testloadString()
    {
        $expected = ['foo', 'bar', 'baz'];
        $str = "foo,bar,baz\nfoo,bar,baz";
        $res = $this->codec->loadString($str);
        $this->assertInstanceof('SplTempFileObject', $res);
        foreach ($res as $row) {
            $this->assertSame($expected, $row);
        }
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testCreateException()
    {
        $this->codec->create(__DIR__.'/bar.csv', 'z');
    }

    /**
     * @expectedException RuntimeException
     */
    public function testCreateException2()
    {
        $this->codec->create('/etc/foo.csv', 'w');
    }

    public function testloadFile()
    {
        $expected = ['foo', 'bar', 'baz'];
        $file = __DIR__.'/foo.csv';
        $res = $this->codec->loadFile($file);
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

    /**
     * @expectedException InvalidArgumentException
     */
    public function testloadFileException()
    {
        $this->codec->loadFile(__DIR__.'/foo.csv', 'w');
    }

    public function testSaveArray()
    {
        $arr = [
            ['foo', 'bar', '  baz '],
            'foo,bar, baz  ',
        ];
        $expected = [['foo', 'bar', '  baz '],['foo','bar',' baz  ']];
        $this->codec
            ->setDelimiter(',')
            ->setEnclosure('"')
            ->setEscape("\\");

        $res = $this->codec->save($arr, 'php://temp');
        $this->assertInstanceof('SplFileObject', $res);
        foreach ($res as $key => $row) {
            $this->assertSame($expected[$key], $row);
        }
    }

    public function testSaveTransversable()
    {
        $arr = [
            ['foo', 'bar', '  baz '],
            'foo,bar, baz  ',
        ];
        $expected = [['foo', 'bar', '  baz '],['foo','bar',' baz  ']];
        $obj = new \ArrayObject($arr);
        $res = $this->codec->save($obj, 'php://temp');
        $this->assertInstanceof('\SplFileObject', $res);
        foreach ($res as $key => $row) {
            $this->assertSame($expected[$key], $row);
        }
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testSaveExceptionBadData()
    {
        $this->codec->save('foo', 'php://temp');
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testSaveExceptionBadMode()
    {
        $this->codec->save(['foo'], 'php://temp', 'r');
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testSaveExceptionBadPath()
    {
        $this->codec->save(['foo'], ['bar']);
    }

    public function testSaveSplFileInfo()
    {
        $obj = new SplFileInfo('php://temp');
        $res = $this->codec->save(['foo'], $obj);
        $this->assertInstanceof('\SplFileObject', $res);
    }
}
