<?php

namespace Bakame\Csv;

use SplFileInfo;

class CodecTest extends \PHPUnit_Framework_TestCase
{

    private $codec;

    public function setUp()
    {
        $this->codec = new Codec;
    }

    public function testloadString()
    {
        $expected = ['foo', 'bar', 'baz'];
        $str = "foo,bar,baz\nfoo,bar,baz";
        $res = $this->codec->loadString($str);
        $this->assertInstanceof('\Bakame\Csv\Reader', $res);
        foreach ($res->getFile() as $row) {
            $this->assertSame($expected, $row);
        }
    }

    public function testloadFile()
    {
        $file = __DIR__.'/foo.csv';
        $res = $this->codec->loadFile($file);
        $this->assertInstanceof('\Bakame\Csv\Reader', $res);
        $this->assertSame($file, $res->getFile()->getRealPath());
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
        $this->assertInstanceof('\Bakame\Csv\Reader', $res);
        $this->assertSame($res->fetchAll(), $expected);
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
        $this->assertInstanceof('\Bakame\Csv\Reader', $res);
        $this->assertSame($res->fetchAll(), $expected);
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
        $this->assertInstanceof('\Bakame\Csv\Reader', $res);
    }
}
