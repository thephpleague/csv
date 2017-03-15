<?php

namespace LeagueTest\Csv;

use League\Csv\BOM;
use PHPUnit\Framework\TestCase;

/**
 * @group csv
 */
class BOMTest extends TestCase
{
    /**
     * @dataProvider BOMValidProvider
     */
    public function testBOMValid($sequence, $expected)
    {
        $this->assertSame($expected, BOM::isValid($sequence));
    }

    public function BOMValidProvider()
    {
        return [
            'empty string' => [
                'sequence' => '',
                'expected' => false,
            ],
            'random string' => [
                'sequence' => 'foo bar',
                'expected' => false,
            ],
            'UTF8 BOM sequence' => [
                'sequence' => chr(239).chr(187).chr(191),
                'expected' => true,
            ],
        ];
    }

    /**
     * @dataProvider BOMMatchProvider
     */
    public function testBOMMatch($str, $expected)
    {
        $this->assertSame($expected, BOM::match($str));
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
