<?php

namespace LeagueTest\Csv;

use League\Csv\BOM;
use PHPUnit\Framework\TestCase;
use function League\Csv\bom_match;

/**
 * @group csv
 */
class BOMTest extends TestCase
{
    /**
     * @dataProvider BOMMatchProvider
     * @param string $str
     * @param string $expected
     */
    public function testBOMMatch($str, $expected)
    {
        $this->assertSame($expected, bom_match($str));
    }

    public function BOMMatchProvider()
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
                'expected' => BOM::UTF8,
            ],
            'UTF8 BOM sequence at the start of a text' => [
                'sequence' => BOM::UTF8.'The quick brown fox jumps over the lazy dog',
                'expected' => chr(239).chr(187).chr(191),
            ],
            'UTF8 BOM sequence inside a text' => [
                'sequence' => 'The quick brown fox '.BOM::UTF8.' jumps over the lazy dog',
                'expected' => '',
            ],
        ];
    }
}
