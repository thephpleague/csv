<?php

namespace LeagueTest\Csv;

use League\Csv\Enclosure;
use League\Csv\Writer;
use LengthException;
use PHPUnit\Framework\TestCase;

/**
 * @group filter
 * @coversDefaultClass League\Csv\Enclosure
 */
class EnclosureTest extends TestCase
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
     * @covers ::controls
     * @covers ::sequence
     * @covers ::filterParams
     * @covers ::forceEnclosure
     * @covers ::onCreate
     * @covers ::filter
     * @covers ::__invoke
     */
    public function testEncloseAll()
    {
        $csv = Writer::createFromString('');
        $csv->setDelimiter('|');
        Enclosure::addTo($csv, Enclosure::ENCLOSE_ALL, "\t\x1f");
        $csv->insertAll($this->records);
        $this->assertContains('"Grand Cherokee"', (string) $csv);
    }

    /**
     * @covers ::addTo
     * @covers ::register
     * @covers ::controls
     * @covers ::sequence
     * @covers ::filterParams
     * @covers ::escapeWhiteSpace
     * @covers ::onCreate
     * @covers ::filter
     * @covers ::__invoke
     */
    public function testEncloseNone()
    {
        $csv = Writer::createFromString('');
        Enclosure::addTo($csv, Enclosure::ENCLOSE_NONE, "\0");
        $csv->insertAll($this->records);
        $this->assertContains('Grand Cherokee', (string) $csv);
    }

    /**
     * @covers ::onCreate
     * @covers ::filterParams
     * @dataProvider wrongParamProvider
     * @param array $params
     */
    public function testOnCreateFailedWithWrongParams(array $params)
    {
        $filter = new Enclosure();
        $filter->params = $params;
        $this->assertFalse($filter->onCreate());
    }

    public function wrongParamProvider()
    {
        return [
            'empty array' => [[
            ]],
            'wrong type' => [[
                'type' => 'foo',
                'sequence' => "\0",
            ]],
            'wrong sequence (1)' => [[
                'type' => Enclosure::ENCLOSE_NONE,
                'sequence' => "\t",
            ]],
            'wrong sequence (2)' => [[
                'type' => Enclosure::ENCLOSE_ALL,
                'sequence' => ';',
            ]],
            'missing parameters' => [[
                'type' => Enclosure::ENCLOSE_NONE,
            ]],
        ];
    }

    public function testSettingControlsFailedWithWrongParams()
    {
        $this->expectException(LengthException::class);
        (new Enclosure())->controls(',', 'foo', '\\');
    }
}
