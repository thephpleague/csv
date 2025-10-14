<?php

/**
 * League.Csv (https://csv.thephpleague.com)
 *
 * (c) Ignace Nyamagana Butera <nyamsprod@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace League\Csv;

use ArrayIterator;
use Iterator;
use OutOfRangeException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function explode;
use function fflush;
use function fwrite;
use function implode;
use function mb_convert_encoding;
use function rewind;
use function stream_filter_register;
use function stream_get_contents;
use function stream_get_filters;
use function strtoupper;
use function substr;
use function tmpfile;

#[Group('converter')]
#[Group('filter')]
final class CharsetConverterTest extends TestCase
{
    public function testCharsetConverterTriggersException(): void
    {
        $this->expectException(OutOfRangeException::class);
        (new CharsetConverter())->inputEncoding('');
    }

    public function testCharsetConverterRemainsTheSame(): void
    {
        $converter = new CharsetConverter();
        self::assertSame($converter, $converter->inputEncoding('utf-8'));
        self::assertSame($converter, $converter->outputEncoding('UtF-8'));
        self::assertNotEquals($converter->outputEncoding('iso-8859-15'), $converter);
    }

    public function testCharsetConverterDoesNothing(): void
    {
        $converter = new CharsetConverter();
        $expected = new ArrayIterator([['a' => 'bé']]);
        /** @var array $record */
        $record = $expected[0];
        self::assertEquals($expected, $converter->convert($expected));
        self::assertEquals($record, ($converter)($record));
        self::assertNotEquals($record, ($converter->outputEncoding('utf-16'))($record));
    }

    public function testCharsetConverterConvertsAnArray(): void
    {
        $expected = ['Batman', 'Superman', 'Anaïs'];
        $raw = explode(',', (string) mb_convert_encoding(implode(',', $expected), 'iso-8859-15', 'utf-8'));
        $converter = (new CharsetConverter())
            ->inputEncoding('iso-8859-15')
            ->inputEncoding('iso-8859-15')
            ->outputEncoding('utf-8')
        ;

        self::assertSame($expected, [...$converter->convert([$raw])][0]);
    }

    public function testCharsetConverterConvertsAnIterator(): void
    {
        $expected = new ArrayIterator(['Batman', 'Superman', 'Anaïs']);
        $converter = (new CharsetConverter())
            ->inputEncoding('iso-8859-15')
            ->outputEncoding('utf-8')
        ;
        self::assertInstanceOf(Iterator::class, $converter->convert($expected));
    }

    public function testCharsetConverterAsStreamFilter(): void
    {
        $expected = 'Batman,Superman,Anaïs';
        $raw = (string) mb_convert_encoding($expected, 'iso-8859-15', 'utf-8');
        $csv = Reader::fromString($raw)
            ->appendStreamFilterOnRead('string.toupper');
        CharsetConverter::addTo($csv, 'iso-8859-15', 'utf-8');

        self::assertContains(CharsetConverter::FILTERNAME.'.*', stream_get_filters());
        self::assertSame(strtoupper($expected), $csv->toString());
    }

    public function testCharsetConverterAsStreamFilterFailed(): void
    {
        $this->expectException(InvalidArgument::class);
        stream_filter_register(CharsetConverter::FILTERNAME.'.*', CharsetConverter::class);
        $expected = 'Batman,Superman,Anaïs';
        $raw = (string) mb_convert_encoding($expected, 'iso-8859-15', 'utf-8');
        Reader::fromString($raw)
            ->appendStreamFilterOnRead('string.toupper')
            ->appendStreamFilterOnRead('convert.league.csv.iso-8859-15:utf-8')
        ;
    }

    public function testOnCreateFailsWithWrongFiltername(): void
    {
        $converter = new CharsetConverter();
        $converter->filtername = 'toto';
        self::assertFalse($converter->onCreate());
    }

    public function testOnCreateFailedWithWrongParams(): void
    {
        $converter = new CharsetConverter();
        $converter->filtername = CharsetConverter::FILTERNAME.'.foo/bar';
        self::assertFalse($converter->onCreate());
    }

    #[DataProvider('converterProvider')]
    public function testConvertOnlyStringField(array $record, array $expected): void
    {
        $converter = (new CharsetConverter())
            ->inputEncoding('iso-8859-15')
            ->outputEncoding('utf-8');

        self::assertSame($expected, [...$converter->convert([$record])][0]);
    }

    public static function converterProvider(): array
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

    public function testItDoesNotChangeTheCSVContentIfNoBOMSequenceIsFound(): void
    {
        $data = <<<CSV
"start
end"
CSV;
        $reader = Reader::fromString($data);
        CharsetConverter::addBOMSkippingTo($reader);
        $reader->includeInputBOM();

        self::assertSame(
            [['start
end']],
            [...$reader]
        );
    }

    #[DataProvider('providesBOMSequences')]
    public static function testItSkipBOMSequenceBeforeConsumingTheCSVStream(string $sequence): void
    {
        $data = <<<CSV
"start
end"
CSV;
        $reader = Reader::fromString($sequence.$data);
        $reader->includeInputBOM();
        CharsetConverter::addBOMSkippingTo($reader);

        self::assertSame(
            [['start
end']],
            [...$reader]
        );
    }

    #[DataProvider('providesBOMSequences')]
    public function testItOnlySkipOnceTheBOMSequenceBeforeConsumingTheCSVStreamOnMultipleLine(string $sequence): void
    {
        $data = <<<CSV
"{$sequence}start
end"
CSV;
        $reader = Reader::fromString($sequence.$data);
        $reader->includeInputBOM();
        CharsetConverter::addBOMSkippingTo($reader);

        self::assertSame(
            [[$sequence.'start
end']],
            [...$reader]
        );
    }

    #[DataProvider('providesBOMSequences')]
    public function testItOnlySkipOnceTheBOMSequenceBeforeConsumingTheCSVStreamOnSingleLine(string $sequence): void
    {
        $reader = Reader::fromString($sequence.$sequence.'start,'.$sequence.'end');
        CharsetConverter::addBOMSkippingTo($reader);
        $reader->includeInputBOM();

        self::assertSame([[$sequence.'start', $sequence.'end']], [...$reader]);
    }

    public static function providesBOMSequences(): iterable
    {
        yield 'BOM UTF-8' => [
            'sequence' => Bom::Utf8->value,
        ];
    }

    #[Test]
    public function it_will_return_an_empty_string_if_the_multibyte_string_is_invalid(): void
    {
        /** @var resource $file */
        $file = tmpfile();
        CharsetConverter::appendOnWriteTo($file);

        $dataStart = str_pad('', 128, 'joe');
        $dataEnd = substr('💩', 0, 2);

        fwrite($file, $dataStart.$dataEnd);
        fflush($file);
        rewind($file);
        $fileContents = stream_get_contents($file);

        self::assertNotSame($dataStart.$dataEnd, $fileContents);
        self::assertSame('', $fileContents);
    }

    #[Test]
    public function it_will_return_the_correct_cotent_if_the_strem_is_split_inside_a_multibyte_string(): void
    {
        /** @var resource $file */
        $file = tmpfile();
        CharsetConverter::appendOnReadTo($file);

        fwrite($file, '💩');
        fflush($file);

        rewind($file);
        self::assertSame(substr('💩', 0, 2), stream_get_contents($file, length: 2, offset: 0));
        self::assertSame(substr('💩', 2), stream_get_contents($file, offset: 2));

        rewind($file);
        self::assertSame('💩', stream_get_contents($file));
    }
}
