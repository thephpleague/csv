<?php

/**
 * League.Csv (https://csv.thephpleague.com)
 *
 * (c) Ignace Nyamagana Butera <nyamsprod@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace League\Csv;

use DOMException;
use PHPUnit\Framework\TestCase;

/**
 * @group converter
 * @coversDefaultClass \League\Csv\HTMLConverter
 */
final class HTMLConverterTest extends TestCase
{
    /**
     * @covers ::create
     * @covers ::__construct
     * @covers ::table
     * @covers ::tr
     * @covers ::td
     * @covers ::convert
     */
    public function testToHTML(): void
    {
        $csv = Reader::createFromPath(__DIR__ . '/../test_files/prenoms.csv', 'r')
            ->setDelimiter(';')
            ->setHeaderOffset(0)
        ;

        $stmt = Statement::create()
            ->offset(3)
            ->limit(5)
        ;

        $records = $stmt->process($csv);

        $converter = HTMLConverter::create()
            ->table('table-csv-data', 'test')
            ->td('title')
            ->tr('data-record-offset')
        ;

        $html = $converter->convert($records);
        self::assertStringContainsString('<table class="table-csv-data" id="test">', $html);
        self::assertStringContainsString('<tr data-record-offset="', $html);
        self::assertStringContainsString('<td title="', $html);
        self::assertStringNotContainsString('<thead>', $html);
        self::assertStringNotContainsString('<tbody>', $html);
        self::assertStringNotContainsString('<tfoot>', $html);
    }

    /**
     * @covers ::convert
     * @covers ::appendHeaderSection
     * @covers ::addHTMLAttributes
     */
    public function testToHTMLWithTHeadTableSection(): void
    {
        $csv = Reader::createFromPath(__DIR__ . '/../test_files/prenoms.csv', 'r')
            ->setDelimiter(';')
            ->setHeaderOffset(0)
        ;

        $stmt = Statement::create()
            ->offset(3)
            ->limit(5)
        ;

        $records = $stmt->process($csv);

        $converter = HTMLConverter::create()
            ->table('table-csv-data', 'test')
            ->td('title')
            ->tr('data-record-offset')
        ;

        $headers = $records->getHeader();

        $html = $converter->convert($records, $headers);
        self::assertStringContainsString('<table class="table-csv-data" id="test">', $html);
        self::assertStringContainsString('<th scope="col">prenoms</th>', $html);
        self::assertStringContainsString('<thead>', $html);
        self::assertStringContainsString('<tbody>', $html);
        self::assertStringNotContainsString('<tfoot>', $html);
        self::assertStringNotContainsString('<thead><tr data-record-offset="0"></tr></thead>', $html);
    }

    /**
     * @covers ::convert
     * @covers ::appendHeaderSection
     * @covers ::addHTMLAttributes
     */
    public function testToHTMLWithTFootTableSection(): void
    {
        $csv = Reader::createFromPath(__DIR__ . '/../test_files/prenoms.csv', 'r')
            ->setDelimiter(';')
            ->setHeaderOffset(0)
        ;

        $stmt = Statement::create()
            ->offset(3)
            ->limit(5)
        ;

        $records = $stmt->process($csv);

        $converter = HTMLConverter::create()
            ->table('table-csv-data', 'test')
            ->td('title')
            ->tr('data-record-offset')
        ;

        $headers = $records->getHeader();

        $html = $converter->convert($records, [], $headers);
        self::assertStringContainsString('<table class="table-csv-data" id="test">', $html);
        self::assertStringContainsString('<th scope="col">prenoms</th>', $html);
        self::assertStringNotContainsString('<thead>', $html);
        self::assertStringContainsString('<tbody>', $html);
        self::assertStringContainsString('<tfoot>', $html);
        self::assertStringNotContainsString('<tfoot><tr data-record-offset="0"></tr></tfoot>', $html);
    }

    /**
     * @covers ::convert
     * @covers ::appendHeaderSection
     * @covers ::addHTMLAttributes
     */
    public function testToHTMLWithBothTableHeaderSection(): void
    {
        $csv = Reader::createFromPath(__DIR__ . '/../test_files/prenoms.csv', 'r')
            ->setDelimiter(';')
            ->setHeaderOffset(0)
        ;

        $stmt = Statement::create()
            ->offset(3)
            ->limit(5)
        ;

        $records = $stmt->process($csv);

        $converter = HTMLConverter::create()
            ->table('table-csv-data', 'test')
            ->td('title')
            ->tr('data-record-offset')
        ;

        $headers = $records->getHeader();

        $html = $converter->convert($records, $headers, $headers);
        self::assertStringContainsString('<table class="table-csv-data" id="test">', $html);
        self::assertStringContainsString('<thead>', $html);
        self::assertStringContainsString('<tbody>', $html);
        self::assertStringContainsString('<tfoot>', $html);
        self::assertStringNotContainsString('<thead><tr data-record-offset="0"></tr></thead>', $html);
        self::assertStringNotContainsString('<tfoot><tr data-record-offset="0"></tr></tfoot>', $html);
    }

    /**
     * @covers ::__construct
     * @covers ::table
     */
    public function testTableTriggersException(): void
    {
        $this->expectException(DOMException::class);
        HTMLConverter::create()->table('table-csv-data', 'te st');
    }
}
