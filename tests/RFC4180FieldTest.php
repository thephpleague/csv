<?php

namespace LeagueTest\Csv;

use League\Csv\Reader;
use League\Csv\RFC4180Field;
use League\Csv\Writer;
use PHPUnit\Framework\TestCase;
use TypeError;

/**
 * @group filter
 * @coversDefaultClass League\Csv\RFC4180Field
 */
class RFC4180FieldTest extends TestCase
{
    /**
     * @see https://bugs.php.net/bug.php?id=43225
     * @see https://bugs.php.net/bug.php?id=74713
     *
     * @covers ::register
     * @covers ::getFiltername
     * @covers ::addTo
     * @covers ::onCreate
     * @covers ::isValidParams
     * @covers ::filter
     *
     * @dataProvider bugsProvider
     *
     * @param string $expected
     * @param array  $record
     */
    public function testStreamFilterOnWrite($expected, array $record)
    {
        $csv = Writer::createFromPath('php://temp');
        RFC4180Field::addTo($csv);
        $this->assertContains(RFC4180Field::getFiltername(), stream_get_filters());
        $csv->setNewline("\r\n");
        $csv->insertOne($record);
        $this->assertSame($expected, (string) $csv);
    }

    public function bugsProvider()
    {
        return [
            'bug #43225' => [
                'expected' => '"a\""",bbb'."\r\n",
                'record' => ['a\\"', 'bbb'],
            ],
            'bug #74713' => [
                'expected' => '"""@@"",""B"""'."\r\n",
                'record' => ['"@@","B"'],
            ],
        ];
    }

    /**
     * @see https://bugs.php.net/bug.php?id=55413
     *
     * @covers ::register
     * @covers ::getFiltername
     * @covers ::addTo
     * @covers ::onCreate
     * @covers ::isValidParams
     * @covers ::filter
     *
     * @dataProvider readerBugsProvider
     *
     * @param string $expected
     * @param array  $record
     */
    public function testStreamFilterOnRead($expected, array $record)
    {
        $csv = Reader::createFromString($expected);
        RFC4180Field::addTo($csv);
        $this->assertSame($record, $csv->fetchOne());
    }

    public function readerBugsProvider()
    {
        return [
            'bug #55413' => [
                'expected' => '"A","Some \"Stuff\"","C"',
                'record' => ['A', 'Some "Stuff"', 'C'],
            ],
        ];
    }

    /**
     * @covers ::onCreate
     * @covers ::isValidParams
     */
    public function testOnCreateFailedWithoutParams()
    {
        $this->expectException(TypeError::class);
        (new RFC4180Field())->onCreate();
    }

    /**
     * @covers ::onCreate
     * @covers ::isValidParams
     * @dataProvider wrongParamProvider
     * @param array $params
     */
    public function testOnCreateFailedWithWrongParams(array $params)
    {
        $filter = new RFC4180Field();
        $filter->params = $params;
        $this->assertFalse($filter->onCreate());
    }

    public function wrongParamProvider()
    {
        return [
            'empty array' => [[
            ]],
            'wrong escape' => [[
                'enclosure' => '"',
                'escape' => 'foo',
                'mode' => STREAM_FILTER_READ,
            ]],
            'wrong enclosure' => [[
                'enclosure' => '',
                'escape' => '\\',
                'mode' => STREAM_FILTER_READ,
            ]],
            'wrong stream filter mode' => [[
                'enclosure' => '"',
                'escape' => '\\',
                'mode' => STREAM_FILTER_ALL,
            ]],
            'missing parameters' => [[
                'enclosure' => '"',
                'escape' => '\\',
            ]],
        ];
    }
}
