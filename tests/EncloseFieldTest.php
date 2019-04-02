<?php

/**
 * League.Csv (https://csv.thephpleague.com)
 *
 * (c) Ignace Nyamagana Butera <nyamsprod@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace LeagueTest\Csv;

use InvalidArgumentException;
use League\Csv\EncloseField;
use League\Csv\Writer;
use PHPUnit\Framework\TestCase;
use function stream_get_filters;

/**
 * @group filter
 * @coversDefaultClass League\Csv\EncloseField
 */
class EncloseFieldTest extends TestCase
{
    /**
     * @see https://en.wikipedia.org/wiki/Comma-separated_values#Example
     *
     * @var array
     */
    protected $records = [
            ['Year', 'Make', 'Model', 'Description', 'Price'],
            [1997, 'Ford', 'E350', 'ac,abs,moon', '3000.00'],
            [1999, 'Chevy', 'Venture "Extended Edition"', null, '4900.00'],
            [1999, 'Chevy', 'Venture "Extended Edition, Very Large"', null, '5000.00'],
            [1996, 'Jeep', 'Grand Cherokee', 'MUST SELL!
        air, moon roof, loaded', '4799.00'],
    ];

    /**
     * @covers ::addTo
     * @covers ::register
     * @covers ::getFiltername
     * @covers ::isValidSequence
     * @covers ::onCreate
     * @covers ::filter
     */
    public function testEncloseAll(): void
    {
        $csv = Writer::createFromString('');
        $csv->setDelimiter('|');
        EncloseField::addTo($csv, "\t\x1f");
        self::assertContains(EncloseField::getFiltername(), stream_get_filters());
        $csv->insertAll($this->records);
        self::assertContains('"Grand Cherokee"', $csv->getContent());
    }

    /**
     * @covers ::onCreate
     * @covers ::isValidSequence
     * @dataProvider wrongParamProvider
     */
    public function testOnCreateFailedWithWrongParams(array $params): void
    {
        $filter = new EncloseField();
        $filter->params = $params;
        self::assertFalse($filter->onCreate());
    }

    public function wrongParamProvider(): iterable
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

    /**
     * @covers ::addTo
     */
    public function testEncloseFieldImmutability(): void
    {
        self::expectException(InvalidArgumentException::class);
        $csv = Writer::createFromString('');
        $csv->setDelimiter('|');
        EncloseField::addTo($csv, 'foo');
    }
}
