<?php

namespace LeagueTest\Csv;

use League\Csv\EncloseField;
use League\Csv\Writer;
use OutOfRangeException;
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
     * @covers ::sequence
     * @covers ::isValidSequence
     * @covers ::forceEnclosure
     * @covers ::onCreate
     * @covers ::filter
     * @covers ::__invoke
     */
    public function testEncloseAll()
    {
        $csv = Writer::createFromString('');
        $csv->setDelimiter('|');
        EncloseField::addTo($csv, "\t\x1f");
        $csv->insertAll($this->records);
        $this->assertContains('"Grand Cherokee"', (string) $csv);
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
     * @covers ::sequence
     */
    public function testEncloseFieldImmutability()
    {
        $this->expectException(OutOfRangeException::class);
        $filter = (new EncloseField())->sequence("\r\t");
        $this->assertSame($filter, $filter->sequence("\r\t"));
        $filter->sequence('foo');
    }
}
