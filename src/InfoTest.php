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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use SplTempFileObject;
use TypeError;

use function chr;

#[Group('csv')]
final class InfoTest extends TestCase
{
    public function testDetectDelimiterListWithInvalidRowLimit(): void
    {
        $this->expectException(Exception::class);

        $file = new SplTempFileObject();
        $file->fwrite("How are you today ?\nI'm doing fine thanks!");
        $csv = Reader::createFromFileObject($file);

        Info::getDelimiterStats($csv, [','], -4);
    }

    public function testDetectDelimiterListWithInvalidDelimiter(): void
    {
        $this->expectException(TypeError::class);

        $file = new SplTempFileObject();
        $file->fwrite("How are you today ?\nI'm doing fine thanks!");
        $csv = Reader::createFromFileObject($file);

        Info::getDelimiterStats($csv, [',', []]); /* @phpstan-ignore-line */
    }

    public function testDetectDelimiterListWithNoCSV(): void
    {
        $file = new SplTempFileObject();
        $file->fwrite("How are you today ?\nI'm doing fine thanks!");
        $csv = Reader::createFromFileObject($file);

        self::assertSame(['toto' => 0, '|' => 0], Info::getDelimiterStats($csv, ['toto', '|'], 5));
    }

    public function testDetectDelimiterWithNoValidDelimiter(): void
    {
        $file = new SplTempFileObject();
        $file->fwrite("How are you today ?\nI'm doing fine thanks!");
        $csv = Reader::createFromFileObject($file);

        self::assertSame(['toto' => 0], Info::getDelimiterStats($csv, ['toto'], 5));
    }

    public function testDetectDelimiterListWithInconsistentCSV(): void
    {
        $data = new SplTempFileObject();
        $data->setCsvControl(';');
        $data->fputcsv(['toto', 'tata', 'tutu']);
        $data->setCsvControl('|');
        $data->fputcsv(['toto', 'tata', 'tutu']);
        $data->fputcsv(['toto', 'tata', 'tutu']);
        $data->fputcsv(['toto', 'tata', 'tutu']);

        self::assertSame(
            ['|' => 12, ';' => 4],
            Info::getDelimiterStats(Reader::createFromFileObject($data), ['|', ';'], 5)
        );
    }

    public function testDetectDelimiterKeepOriginalDelimiter(): void
    {
        $file = new SplTempFileObject();
        $file->fwrite("How are you today ?\nI'm doing fine thanks!");
        $csv = Reader::createFromFileObject($file);
        $csv->setDelimiter('@');

        Info::getDelimiterStats($csv, ['toto', '|'], 5);

        self::assertSame('@', $csv->getDelimiter());
    }

    public function testExpectedLimitIsUsedIssue366(): void
    {
        $text = <<<EOF
foo;bar;hello_world
42;1,2,3,4,5;true
EOF;
        $expected = [';' => 4, ',' => 0];
        $reader = Reader::createFromString($text);

        self::assertSame($expected, Info::getDelimiterStats($reader, [';', ','], 1));
    }

    #[DataProvider('ByteSequenceMatchProvider')]
    public function testByteSequenceMatch(string $str, string $expected, ?string $method_expected): void
    {
        self::assertSame($expected, Info::fetchBOMSequence($str) ?? '');
        self::assertSame($method_expected, Info::fetchBOMSequence($str));
    }

    public static function ByteSequenceMatchProvider(): array
    {
        return [
            'empty string' => [
                'sequence' => '',
                'expected' => '',
                'method_expected' => null,
            ],
            'random string' => [
                'sequence' => 'foo bar',
                'expected' => '',
                'method_expected' => null,
            ],
            'UTF8 BOM sequence' => [
                'sequence' => chr(239).chr(187).chr(191),
                'expected' => ByteSequence::BOM_UTF8,
                'method_expected' => ByteSequence::BOM_UTF8,
            ],
            'UTF8 BOM sequence at the start of a text' => [
                'sequence' => ByteSequence::BOM_UTF8.'The quick brown fox jumps over the lazy dog',
                'expected' => chr(239).chr(187).chr(191),
                'method_expected' => chr(239).chr(187).chr(191),
            ],
            'UTF8 BOM sequence inside a text' => [
                'sequence' => 'The quick brown fox '.ByteSequence::BOM_UTF8.' jumps over the lazy dog',
                'expected' => '',
                'method_expected' => null,
            ],
            'UTF32 LE BOM sequence' => [
                'sequence' => chr(255).chr(254).chr(0).chr(0),
                'expected' => ByteSequence::BOM_UTF32_LE,
                'method_expected' => ByteSequence::BOM_UTF32_LE,
            ],
        ];
    }
}
