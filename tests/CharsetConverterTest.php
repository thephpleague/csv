<?php

/**
 * League.Csv (https://csv.thephpleague.com).
 *
 * @author  Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @license https://github.com/thephpleague/csv/blob/master/LICENSE (MIT License)
 * @version 9.2.0
 * @link    https://github.com/thephpleague/csv
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace LeagueTest\Csv;

use ArrayIterator;
use Iterator;
use League\Csv\CharsetConverter;
use League\Csv\Exception;
use League\Csv\Reader;
use OutOfRangeException;
use PHPUnit\Framework\TestCase;
use TypeError;
use function explode;
use function implode;
use function mb_convert_encoding;
use function stream_filter_register;
use function stream_get_filters;
use function strtoupper;

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
        self::expectException(OutOfRangeException::class);
        (new CharsetConverter())->inputEncoding('');
    }

    /**
     * @covers ::convert
     */
    public function testCharsetConverterTriggersExceptionOnConversion()
    {
        self::expectException(TypeError::class);
        (new CharsetConverter())->convert('toto');
    }

    /**
     * @covers ::inputEncoding
     * @covers ::outputEncoding
     */
    public function testCharsetConverterRemainsTheSame()
    {
        $converter = new CharsetConverter();
        self::assertSame($converter, $converter->inputEncoding('utf-8'));
        self::assertSame($converter, $converter->outputEncoding('UtF-8'));
        self::assertNotEquals($converter->outputEncoding('iso-8859-15'), $converter);
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
        self::assertEquals($expected, $converter->convert($expected));
        self::assertEquals($expected[0], ($converter)($expected[0]));
        self::assertNotEquals($expected[0], ($converter->outputEncoding('utf-16'))($expected[0]));
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
        self::assertSame($expected, $converter->convert([$raw])[0]);
    }


    /**
     * @covers ::convert
     * @covers ::inputEncoding
     */
    public function testCharsetConverterConvertsAnIterator()
    {
        $expected = new ArrayIterator(['Batman', 'Superman', 'Anaïs']);
        $converter = (new CharsetConverter())
            ->inputEncoding('iso-8859-15')
            ->outputEncoding('utf-8')
        ;
        self::assertInstanceOf(Iterator::class, $converter->convert($expected));
    }

    /**
     * @covers ::register
     * @covers ::getFiltername
     * @covers ::addTo
     * @covers ::onCreate
     * @covers ::filter
     */
    public function testCharsetConverterAsStreamFilter()
    {
        $expected = 'Batman,Superman,Anaïs';
        $raw = mb_convert_encoding($expected, 'iso-8859-15', 'utf-8');
        $csv = Reader::createFromString($raw)
            ->addStreamFilter('string.toupper');
        CharsetConverter::addTo($csv, 'iso-8859-15', 'utf-8');

        self::assertContains(CharsetConverter::FILTERNAME.'.*', stream_get_filters());
        self::assertSame(strtoupper($expected), $csv->getContent());
    }

    /**
     * @covers ::onCreate
     * @covers ::filter
     */
    public function testCharsetConverterAsStreamFilterFailed()
    {
        self::expectException(Exception::class);
        stream_filter_register(CharsetConverter::FILTERNAME.'.*', CharsetConverter::class);
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
    public function testOnCreateFailsWithWrongFiltername()
    {
        $converter = new CharsetConverter();
        $converter->filtername = 'toto';
        self::assertFalse($converter->onCreate());
    }

    /**
     * @covers ::onCreate
     */
    public function testOnCreateFailedWithWrongParams()
    {
        $converter = new CharsetConverter();
        $converter->filtername = CharsetConverter::FILTERNAME.'.foo/bar';
        self::assertFalse($converter->onCreate());
    }

    /**
     * @covers ::convert
     * @covers ::encodeField
     *
     * @dataProvider converterProvider
     */
    public function testConvertOnlyStringField(array $record, array $expected)
    {
        $converter = (new CharsetConverter())
            ->inputEncoding('iso-8859-15')
            ->outputEncoding('utf-8');
        $res = $converter->convert([$record]);
        self::assertSame($expected, $res[0]);
    }

    public function converterProvider(): array
    {
        return [
            'only numeric values' => [
                'record' => [1, 2, 3],
                'expected' => [1, 2, 3],
            ],
            'only string values' => [
                'record' => ['1', '2', '3'],
                'expected' => ['1', '2', '3'],
            ],
            'mixed values' => [
                'record' => [1, '2', 3],
                'expected' => [1, '2', 3],
            ],
            'mixed offset' => [
                'record' => [1 => 1, '2' => '2', 3 => 3],
                'expected' => [1 => 1, '2' => '2', 3 => 3],
            ],
        ];
    }
}
