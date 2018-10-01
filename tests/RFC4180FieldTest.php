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
use League\Csv\Reader;
use League\Csv\RFC4180Field;
use League\Csv\Writer;
use PHPUnit\Framework\TestCase;
use TypeError;
use const STREAM_FILTER_ALL;
use const STREAM_FILTER_READ;
use function stream_get_filters;

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
     */
    public function testStreamFilterOnWrite($expected, array $record)
    {
        $csv = Writer::createFromPath('php://temp');
        RFC4180Field::addTo($csv);
        self::assertContains(RFC4180Field::getFiltername(), stream_get_filters());
        $csv->setNewline("\r\n");
        $csv->insertOne($record);
        self::assertSame($expected, $csv->getContent());
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
     */
    public function testStreamFilterOnRead($expected, array $record)
    {
        $csv = Reader::createFromString($expected);
        RFC4180Field::addTo($csv);
        self::assertSame($record, $csv->fetchOne(0));
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
        self::expectException(TypeError::class);
        (new RFC4180Field())->onCreate();
    }

    /**
     * @covers ::onCreate
     * @covers ::isValidParams
     * @dataProvider wrongParamProvider
     */
    public function testOnCreateFailedWithWrongParams(array $params)
    {
        $filter = new RFC4180Field();
        $filter->params = $params;
        self::assertFalse($filter->onCreate());
    }

    public function wrongParamProvider(): array
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
     * @covers ::addFormatterTo
     * @covers ::onCreate
     * @covers ::isValidSequence
     * @covers ::filter
     */
    public function testDoNotEncloseWhiteSpacedField()
    {
        $csv = Writer::createFromString('');
        $csv->setDelimiter('|');
        RFC4180Field::addTo($csv, "\0");
        $csv->insertAll($this->records);
        $contents = $csv->getContent();
        self::assertContains('Grand Cherokee', $contents);
        self::assertNotContains('"Grand Cherokee"', $contents);
    }


    /**
     * @covers ::addTo
     * @covers ::addFormatterTo
     * @covers ::onCreate
     * @covers ::filter
     */
    public function testDoNotEncloseWhiteSpacedFieldThrowsException()
    {
        self::expectException(InvalidArgumentException::class);
        RFC4180Field::addTo(Writer::createFromString(''), "\t\0");
    }
}
