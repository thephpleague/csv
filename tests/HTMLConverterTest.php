<?php

/**
 * League.Csv (https://csv.thephpleague.com)
 *
 * (c) Ignace Nyamagana Butera <nyamsprod@gmail.com>
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
 * @coversDefaultClass \League\Csv\HTMLConverter
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
        self::assertNotContains('<thead>', $html);
        self::assertNotContains('<tbody>', $html);
        self::assertNotContains('<tfoot>', $html);
    }

    /**
     * @covers ::convert
     * @covers ::appendTableHeaderSection
     * @covers ::styleTableElement
     */
    public function testToHTMLWithTHeadTableSection()
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

        $headers = $records->getHeader();

        $html = $converter->convert($records, $headers);
        self::assertContains('<table class="table-csv-data" id="test">', $html);
        self::assertContains('<th scope="col">prenoms</th>', $html);
        self::assertContains('<thead>', $html);
        self::assertContains('<tbody>', $html);
        self::assertNotContains('<tfoot>', $html);
        self::assertNotContains('<thead><tr data-record-offset="0"></tr></thead>', $html);
    }

    /**
     * @covers ::convert
     * @covers ::appendTableHeaderSection
     * @covers ::styleTableElement
     */
    public function testToHTMLWithTFootTableSection()
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

        $headers = $records->getHeader();

        $html = $converter->convert($records, [], $headers);
        self::assertContains('<table class="table-csv-data" id="test">', $html);
        self::assertContains('<th scope="col">prenoms</th>', $html);
        self::assertNotContains('<thead>', $html);
        self::assertContains('<tbody>', $html);
        self::assertContains('<tfoot>', $html);
        self::assertNotContains('<tfoot><tr data-record-offset="0"></tr></tfoot>', $html);
    }

    /**
     * @covers ::convert
     * @covers ::appendTableHeaderSection
     * @covers ::styleTableElement
     */
    public function testToHTMLWithBothTableHeaderSection()
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

        $headers = $records->getHeader();

        $html = $converter->convert($records, $headers, $headers);
        self::assertContains('<table class="table-csv-data" id="test">', $html);
        self::assertContains('<thead>', $html);
        self::assertContains('<tbody>', $html);
        self::assertContains('<tfoot>', $html);
        self::assertNotContains('<thead><tr data-record-offset="0"></tr></thead>', $html);
        self::assertNotContains('<tfoot><tr data-record-offset="0"></tr></tfoot>', $html);
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
