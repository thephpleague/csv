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
    public function testEncloseAll()
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
    public function testOnCreateFailedWithWrongParams(array $params)
    {
        $filter = new EncloseField();
        $filter->params = $params;
        self::assertFalse($filter->onCreate());
    }

    public function wrongParamProvider()
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
    public function testEncloseFieldImmutability()
    {
        self::expectException(InvalidArgumentException::class);
        $csv = Writer::createFromString('');
        $csv->setDelimiter('|');
        EncloseField::addTo($csv, 'foo');
    }
}
