<?php

namespace LeagueTest\Csv;

use League\Csv\ByteSequence;
use PHPUnit\Framework\TestCase;
use function League\Csv\bom_match;

/**
 * @group csv
 */
class ByteSequenceTest extends TestCase
{
    /**
     * @dataProvider ByteSequenceMatchProvider
     * @param string $str
     * @param string $expected
     * @covers League\Csv\bom_match
     */
    public function testByteSequenceMatch($str, $expected)
    {
        $this->assertSame($expected, bom_match($str));
    }

    public function ByteSequenceMatchProvider()
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
        ];
    }
}
