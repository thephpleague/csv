<?php

namespace LeagueTest\Csv;

use League\Csv\RFC4180FieldFormatter;
use League\Csv\Writer;
use PHPUnit\Framework\TestCase;

/**
 * @group writer
 * @coversDefaultClass League\Csv\RFC4180FieldFormatter
 */
class RFC4180FieldFormatterTest extends TestCase
{
    /**
     * @see https://bugs.php.net/bug.php?id=43225
     * @see https://bugs.php.net/bug.php?id=74713
     *
     * @covers ::addTo
     * @covers ::onCreate
     * @covers ::filter
     *
     * @dataProvider bugsProvider
     *
     * @param string $expected
     * @param array  $record
     */
    public function testStreamFilter($expected, array $record)
    {
        $csv = Writer::createFromPath('php://temp');
        RFC4180FieldFormatter::addTo($csv);
        $this->assertContains(RFC4180FieldFormatter::STREAM_FILTERNAME, stream_get_filters());
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
     * @covers ::onCreate
     */
    public function testOnCreateFailedWithoutParams()
    {
        $filter = new RFC4180FieldFormatter();
        $this->assertFalse($filter->onCreate());
    }

    /**
     * @covers ::onCreate
     */
    public function testOnCreateFailedWithWrongParams()
    {
        $filter = new RFC4180FieldFormatter();
        $filter->params = [
            'enclosure' => '"',
            'escape' => 'foo',
        ];
        $this->assertFalse($filter->onCreate());
    }
}
