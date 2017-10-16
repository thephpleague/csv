<?php

namespace LeagueTest\Csv;

use InvalidArgumentException;
use League\Csv\EncloseField;
use League\Csv\Writer;
use PHPUnit\Framework\TestCase;

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
        $this->assertContains(EncloseField::getFiltername(), stream_get_filters());
        $csv->insertAll($this->records);
        $this->assertContains('"Grand Cherokee"', $csv->getContent());
    }

    /**
     * @covers ::onCreate
     * @covers ::isValidSequence
     * @dataProvider wrongParamProvider
     * @param array $params
     */
    public function testOnCreateFailedWithWrongParams(array $params)
    {
        $filter = new EncloseField();
        $filter->params = $params;
        $this->assertFalse($filter->onCreate());
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
        $this->expectException(InvalidArgumentException::class);
        $csv = Writer::createFromString('');
        $csv->setDelimiter('|');
        EncloseField::addTo($csv, 'foo');
    }
}
