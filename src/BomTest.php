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
use PHPUnit\Framework\TestCase;

use function chr;

final class BomTest extends TestCase
{
    #[DataProvider('BomSequencesProvider')]
    public function test_bom_detection_from_sequence(string $sequence, ?Bom $expected): void
    {
        self::assertSame($expected, Bom::tryFromSequence($sequence));
    }

    public static function BomSequencesProvider(): array
    {
        return [
            'empty string' => [
                'sequence' => '',
                'expected' => null,
            ],
            'random string' => [
                'sequence' => 'foo bar',
                'expected' => null,
            ],
            'UTF8 BOM sequence' => [
                'sequence' => chr(239).chr(187).chr(191),
                'expected' => Bom::Utf8,
            ],

            'UTF8 BOM sequence at the start of a text' => [
                'sequence' => Bom::Utf8->value.'The quick brown fox jumps over the lazy dog',
                'expected' => Bom::Utf8,
            ],
            'UTF8 BOM sequence inside a text' => [
                'sequence' => 'The quick brown fox '.Bom::Utf8->value.' jumps over the lazy dog',
                'expected' => null,
            ],
            'UTF32 LE BOM sequence' => [
                'sequence' => chr(255).chr(254).chr(0).chr(0),
                'expected' => Bom::Utf32Le,
            ],
        ];
    }

    #[DataProvider('BomNamesProvider')]
    public function test_bom_detection_from_name(string $name, ?Bom $expected): void
    {
        self::assertSame($expected, Bom::tryFromName($name));
    }

    public static function BomNamesProvider(): array
    {
        return [
            'empty string' => [
                'name' => '',
                'expected' => null,
            ],
            'all capitals' => [
                'name' => 'UTF8',
                'expected' => Bom::Utf8,
            ],
            'all lowercase' => [
                'name' => 'utf8',
                'expected' => Bom::Utf8,
            ],
            'with separators' => [
                'name' => 'u-t_f_8',
                'expected' => Bom::Utf8,
            ],
            'with unknown separator' => [
                'name' => 'utf*8',
                'expected' => null,
            ],
            'unknown BOM name' => [
                'name' => 'utf24',
                'expected' => null,
            ],
            'missing endian suffix for UTF-16' => [
                'name' => 'utf16',
                'expected' => Bom::Utf16Be,
            ],
            'missing endian suffix for UTF-32' => [
                'name' => 'UTF_32',
                'expected' => Bom::Utf32Be,
            ],
        ];
    }
}
