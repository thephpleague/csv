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

use DOMException;
use League\Csv\HTMLConverter;
use League\Csv\Reader;
use League\Csv\Statement;
use PHPUnit\Framework\TestCase;

/**
 * @group converter
 * @coversDefaultClass League\Csv\HTMLConverter
 */
class HTMLConverterTest extends TestCase
{
    /**
     * @covers ::__construct
     * @covers ::table
     * @covers ::tr
     * @covers ::td
     * @covers ::convert
     */
    public function testToHTML()
    {
        $csv = Reader::createFromPath(__DIR__.'/data/prenoms.csv', 'r')
            ->setDelimiter(';')
            ->setHeaderOffset(0)
        ;

        $stmt = (new Statement())
            ->offset(3)
            ->limit(5)
        ;

        $records = $stmt->process($csv);

        $converter = (new HTMLConverter())
            ->table('table-csv-data', 'test')
            ->td('title')
            ->tr('data-record-offset')
        ;

        $html = $converter->convert($records);
        self::assertContains('<table class="table-csv-data" id="test">', $html);
        self::assertContains('<tr data-record-offset="', $html);
        self::assertContains('<td title="', $html);
    }

    /**
     * @covers ::__construct
     * @covers ::table
     */
    public function testTableTriggersException()
    {
        self::expectException(DOMException::class);
        (new HTMLConverter())->table('table-csv-data', 'te st');
    }
}
