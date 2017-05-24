<?php

namespace LeagueTest\Csv;

use ArrayIterator;
use League\Csv\CharsetConverter;
use League\Csv\Exception\OutOfRangeException;
use League\Csv\Exception\RuntimeException;
use League\Csv\Reader;
use PHPUnit\Framework\TestCase;

/**
 * @group converter
 * @coversDefaultClass League\Csv\CharsetConverter
 */
class CharsetConverterTest extends TestCase
{
    /**
     * @covers ::inputEncoding
     * @covers ::filterEncoding
     */
    public function testCharsetConverterTriggersException()
    {
        $this->expectException(OutOfRangeException::class);
        (new CharsetConverter())->inputEncoding('');
    }

    /**
     * @covers ::inputEncoding
     * @covers ::outputEncoding
     */
    public function testCharsetConverterRemainsTheSame()
    {
        $converter = new CharsetConverter();
        $this->assertSame($converter, $converter->inputEncoding('utf-8'));
        $this->assertSame($converter, $converter->outputEncoding('UtF-8'));
        $this->assertNotEquals($converter->outputEncoding('iso-8859-15'), $converter);
    }

    /**
     * @covers ::convert
     * @covers ::filterEncoding
     * @covers ::encodeField
     * @covers ::inputEncoding
     * @covers ::__invoke
     */
    public function testCharsetConverterDoesNothing()
    {
        $converter = new CharsetConverter();
        $data = [['a' => 'bé']];
        $expected = new ArrayIterator($data);
        $this->assertEquals($expected, $converter->convert($expected));
        $this->assertEquals($expected[0], ($converter)($expected[0]));
        $this->assertNotEquals($expected[0], ($converter->outputEncoding('utf-16'))($expected[0]));
    }

    /**
     * @covers ::convert
     * @covers ::inputEncoding
     */
    public function testCharsetConverterConvertsAnArray()
    {
        $expected = ['Batman', 'Superman', 'Anaïs'];
        $raw = explode(',', mb_convert_encoding(implode(',', $expected), 'iso-8859-15', 'utf-8'));
        $converter = (new CharsetConverter())
            ->inputEncoding('iso-8859-15')
            ->inputEncoding('iso-8859-15')
            ->outputEncoding('utf-8')
        ;
        $this->assertSame($expected, iterator_to_array($converter->convert([$raw]))[0]);
    }

    /**
     * @covers ::registerStreamFilter
     * @covers ::getFiltername
     * @covers ::onCreate
     * @covers ::filter
     */
    public function testCharsetConverterAsStreamFilter()
    {
        CharsetConverter::registerStreamFilter();
        $res = stream_get_filters();
        $this->assertContains(CharsetConverter::STREAM_FILTERNAME.'.*', $res);

        $expected = 'Batman,Superman,Anaïs';
        $raw = mb_convert_encoding($expected, 'iso-8859-15', 'utf-8');
        $csv = Reader::createFromString($raw)
            ->addStreamFilter('string.toupper')
            ->addStreamFilter(CharsetConverter::getFiltername('iso-8859-15', 'utf-8'))
        ;
        $this->assertSame(strtoupper($expected), (string) $csv);
    }

    /**
     * @covers ::registerStreamFilter
     * @covers ::onCreate
     * @covers ::filter
     */
    public function testCharsetConverterAsStreamFilterFailed()
    {
        $this->expectException(RuntimeException::class);
        CharsetConverter::registerStreamFilter();
        $expected = 'Batman,Superman,Anaïs';
        $raw = mb_convert_encoding($expected, 'iso-8859-15', 'utf-8');
        $csv = Reader::createFromString($raw)
            ->addStreamFilter('string.toupper')
            ->addStreamFilter('convert.league.csv.iso-8859-15:utf-8')
        ;
    }

    /**
     * @covers ::onCreate
     */
    public function testOnCreate()
    {
        $converter = new CharsetConverter();
        $converter->filtername = 'toto';
        $this->assertFalse($converter->onCreate());
    }
}
