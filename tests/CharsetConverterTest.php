<?php

namespace LeagueTest\Csv;

use ArrayIterator;
use League\Csv\CharsetConverter;
use League\Csv\Exception\InvalidArgumentException;
use League\Csv\Reader;
use PHPUnit\Framework\TestCase;

/**
 * @group converter
 */
class CharsetConverterTest extends TestCase
{
    public function testCharsetConverterTriggersException()
    {
        $this->expectException(InvalidArgumentException::class);
        (new CharsetConverter())->inputEncoding('');
    }

    public function testCharsetConverterRemainsTheSame()
    {
        $converter = new CharsetConverter();
        $this->assertSame($converter, $converter->inputEncoding('utf-8'));
        $this->assertSame($converter, $converter->outputEncoding('UtF-8'));
        $this->assertNotEquals($converter->outputEncoding('iso-8859-15'), $converter);
    }

    public function testCharsetConverterDoesNothing()
    {
        $converter = new CharsetConverter();
        $data = [['a' => 'bé']];
        $expected = new ArrayIterator($data);
        $this->assertEquals($expected, $converter->convert($expected));
        $this->assertEquals($expected[0], ($converter)($expected[0]));
        $this->assertNotEquals($expected[0], ($converter->outputEncoding('utf-16'))($expected[0]));
    }

    public function testCharsetConverterConvertsAnArray()
    {
        $expected = ['Batman', 'Superman', 'Anaïs'];
        $raw = explode(',', mb_convert_encoding(implode(',', $expected), 'iso-8859-15', 'utf-8'));
        $converter = (new CharsetConverter())
            ->inputEncoding('iso-8859-15')
            ->outputEncoding('utf-8')
        ;
        $this->assertSame($expected, iterator_to_array($converter->convert([$raw]))[0]);
    }

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

    public function testCharsetConverterAsStreamFilterFailed()
    {
        $this->expectException(InvalidArgumentException::class);
        CharsetConverter::registerStreamFilter();
        $expected = 'Batman,Superman,Anaïs';
        $raw = mb_convert_encoding($expected, 'iso-8859-15', 'utf-8');
        $csv = Reader::createFromString($raw)
            ->addStreamFilter('string.toupper')
            ->addStreamFilter('convert.league.csv.iso-8859-15:utf-8')
        ;
    }

    public function testOnCreate()
    {
        $converter = new CharsetConverter();
        $converter->filtername = 'toto';
        $this->assertFalse($converter->onCreate());
    }
}
