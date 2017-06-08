<?php

namespace LeagueTest\Csv;

use League\Csv\RFC4180Field;
use League\Csv\Writer;
use PHPUnit\Framework\TestCase;

/**
 * @group writer
 * @coversDefaultClass League\Csv\RFC4180Field
 */
class RFC4180FieldTest extends TestCase
{
    /**
     * Example taken from PHP bug #43225
     *
     * @see https://bugs.php.net/bug.php?id=43225
     *
     * @covers ::addTo
     * @covers ::onCreate
     * @covers ::filter
     */
    public function testStreamFilter()
    {
        $expected = '"a\""",bbb'."\r\n";
        $csv = Writer::createFromStream(fopen('php://temp', 'r+'));

        RFC4180Field::addTo($csv);

        $res = stream_get_filters();
        $this->assertContains(RFC4180Field::STREAM_FILTERNAME, $res);
        $csv->setNewline("\r\n");
        $csv->insertOne(['a\\"', 'bbb']);
        $this->assertSame($expected, (string) $csv);
    }

    /**
     * @covers ::onCreate
     */
    public function testOnCreateFailedWithoutParams()
    {
        $filter = new RFC4180Field();
        $this->assertFalse($filter->onCreate());
    }

    /**
     * @covers ::onCreate
     */
    public function testOnCreateFailedWithWrongParams()
    {
        $filter = new RFC4180Field();
        $filter->params = [
            'enclosure' => '"',
            'escape' => 'foo',
        ];
        $this->assertFalse($filter->onCreate());
    }
}
