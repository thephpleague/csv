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
use TypeError;

use function stream_get_filters;

use const STREAM_FILTER_ALL;
use const STREAM_FILTER_READ;

/**
 * DEPRECATION WARNING! This class will be removed in the next major point release.
 *
 * @deprecated since version 9.2.0
 */
#[Group('filter')]
final class RFC4180FieldTest extends TestCase
{
    /**
     * @see https://en.wikipedia.org/wiki/Comma-separated_values#Example
     */
    protected array $records = [
        ['Year', 'Make', 'Model', 'Description', 'Price'],
        [1997, 'Ford', 'E350', 'ac,abs,moon', '3000.00'],
        [1999, 'Chevy', 'Venture "Extended Edition"', null, '4900.00'],
        [1999, 'Chevy', 'Venture "Extended Edition, Very Large"', null, '5000.00'],
        [1996, 'Jeep', 'Grand Cherokee', 'MUST SELL!
        air, moon roof, loaded', '4799.00'],
    ];

    /**
     * @see https://bugs.php.net/bug.php?id=43225
     * @see https://bugs.php.net/bug.php?id=74713
     */
    #[DataProvider('bugsProvider')]
    public function testStreamFilterOnWrite(string $expected, array $record): void
    {
        $csv = Writer::createFromPath('php://temp');
        RFC4180Field::addTo($csv);
        self::assertContains(RFC4180Field::getFiltername(), stream_get_filters());
        $csv->setEndOfLine("\r\n");
        $csv->insertOne($record);
        self::assertSame($expected, $csv->toString());
    }

    public static function bugsProvider(): array
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
     */
    #[DataProvider('readerBugsProvider')]
    public function testStreamFilterOnRead(string $expected, array $record): void
    {
        $csv = Reader::createFromString($expected);
        RFC4180Field::addTo($csv);
        self::assertSame($record, $csv->first());
    }

    public static function readerBugsProvider(): array
    {
        return [
            'bug #55413' => [
                'expected' => '"A","Some \"Stuff\"","C"',
                'record' => ['A', 'Some "Stuff"', 'C'],
            ],
        ];
    }

    public function testOnCreateFailedWithoutParams(): void
    {
        $this->expectException(TypeError::class);
        (new RFC4180Field())->onCreate();
    }

    #[DataProvider('wrongParamProvider')]
    public function testOnCreateFailedWithWrongParams(array $params): void
    {
        $filter = new RFC4180Field();
        $filter->params = $params;
        self::assertFalse($filter->onCreate());
    }

    public static function wrongParamProvider(): array
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

    public function testDoNotEncloseWhiteSpacedField(): void
    {
        $csv = Writer::createFromString('');
        $csv->setDelimiter('|');
        RFC4180Field::addTo($csv, "\0");
        $csv->insertAll($this->records);
        $contents = $csv->toString();
        self::assertStringContainsString('Grand Cherokee', $contents);
        self::assertStringNotContainsString('"Grand Cherokee"', $contents);
    }

    public function testDoNotEncloseWhiteSpacedFieldThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        RFC4180Field::addTo(Writer::createFromString(''), "\t\0");
    }
}
