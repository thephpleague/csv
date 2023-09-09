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

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

use function stream_get_filters;

/**
 * DEPRECATION WARNING! This class will be removed in the next major point release.
 *
 * @deprecated since version 9.10.0
 */
#[Group('filter')]
final class EncloseFieldTest extends TestCase
{
    /**
     * @see https://en.wikipedia.org/wiki/Comma-separated_values#Example
     */
    private array $records = [
            ['Year', 'Make', 'Model', 'Description', 'Price'],
            [1997, 'Ford', 'E350', 'ac,abs,moon', '3000.00'],
            [1999, 'Chevy', 'Venture "Extended Edition"', null, '4900.00'],
            [1999, 'Chevy', 'Venture "Extended Edition, Very Large"', null, '5000.00'],
            [1996, 'Jeep', 'Grand Cherokee', 'MUST SELL!
        air, moon roof, loaded', '4799.00'],
    ];

    public function testEncloseAll(): void
    {
        $csv = Writer::createFromString('');
        $csv->setDelimiter('|');
        EncloseField::addTo($csv, "\t\x1f");
        self::assertContains(EncloseField::getFiltername(), stream_get_filters());
        $csv->insertAll($this->records);
        $expected = <<<CSV
"Year"|"Make"|"Model"|"Description"|"Price"
"1997"|"Ford"|"E350"|"ac,abs,moon"|"3000.00"
"1999"|"Chevy"|"Venture ""Extended Edition"""|""|"4900.00"
"1999"|"Chevy"|"Venture ""Extended Edition, Very Large"""|""|"5000.00"
"1996"|"Jeep"|"Grand Cherokee"|"MUST SELL!
        air, moon roof, loaded"|"4799.00"

CSV;
        self::assertStringContainsString($expected, $csv->toString());
    }

    /**
     * @param array<string> $params
     */
    #[DataProvider('wrongParamProvider')]
    public function testOnCreateFailedWithWrongParams(array $params): void
    {
        $filter = new EncloseField();
        $filter->params = $params;
        self::assertFalse($filter->onCreate());
    }

    public static function wrongParamProvider(): iterable
    {
        return [
            'empty array' => [[
            ]],
            'wrong sequence (2)' => [[
                'sequence' => ';',
            ]],
            'missing parameters' => [[
                'foo' => 'bar',
            ]],
        ];
    }

    public function testEncloseFieldImmutability(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $csv = Writer::createFromString('');
        $csv->setDelimiter('|');
        EncloseField::addTo($csv, 'foo');
    }
}
