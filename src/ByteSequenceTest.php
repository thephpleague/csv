<?php

/**
 * League.Csv (https://csv.thephpleague.com)
 *
 * (c) Ignace Nyamagana Butera <nyamsprod@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace League\Csv;

use PHPUnit\Framework\TestCase;
use function chr;

/**
 * @group csv
 */
final class ByteSequenceTest extends TestCase
{
    /**
     * @dataProvider ByteSequenceMatchProvider
     * @covers League\Csv\bom_match
     */
    public function testByteSequenceMatch(string $str, string $expected): void
    {
        self::assertSame($expected, bom_match($str));
    }

    public function ByteSequenceMatchProvider(): array
    {
        return [
            'empty string' => [
                'sequence' => '',
                'expected' => '',
            ],
            'random string' => [
                'sequence' => 'foo bar',
                'expected' => '',
            ],
            'UTF8 BOM sequence' => [
                'sequence' => chr(239).chr(187).chr(191),
                'expected' => ByteSequence::BOM_UTF8,
            ],
            'UTF8 BOM sequence at the start of a text' => [
                'sequence' => ByteSequence::BOM_UTF8.'The quick brown fox jumps over the lazy dog',
                'expected' => chr(239).chr(187).chr(191),
            ],
            'UTF8 BOM sequence inside a text' => [
                'sequence' => 'The quick brown fox '.ByteSequence::BOM_UTF8.' jumps over the lazy dog',
                'expected' => '',
            ],
            'UTF32 LE BOM sequence' => [
                'sequence' => chr(255).chr(254).chr(0).chr(0),
                'expected' => ByteSequence::BOM_UTF32_LE,
            ],
        ];
    }
}
