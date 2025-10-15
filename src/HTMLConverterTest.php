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

use DOMException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

use function array_map;

#[Group('converter')]
final class HTMLConverterTest extends TestCase
{
    public function testToHTML(): void
    {
        $csv = Reader::from(__DIR__.'/../test_files/prenoms.csv', 'r')
            ->setDelimiter(';')
            ->setHeaderOffset(0)
        ;

        $records = (new Statement())
            ->offset(3)
            ->limit(5)
            ->process($csv);

        $converter = (new HTMLConverter())
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

    public function testToHTMLWithTHeadTableSection(): void
    {
        $csv = Reader::from(__DIR__.'/../test_files/prenoms.csv', 'r')
            ->setDelimiter(';')
            ->setHeaderOffset(0)
        ;

        $records = (new Statement())
            ->offset(3)
            ->limit(5)
            ->process($csv);

        $converter = (new HTMLConverter())
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

    public function testToHTMLWithTFootTableSection(): void
    {
        $csv = Reader::from(__DIR__.'/../test_files/prenoms.csv', 'r')
            ->setDelimiter(';')
            ->setHeaderOffset(0)
        ;

        $records = (new Statement())
            ->offset(3)
            ->limit(5)
            ->process($csv);

        $converter = (new HTMLConverter())
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

    public function testToHTMLWithBothTableHeaderSection(): void
    {
        $csv = Reader::from(__DIR__.'/../test_files/prenoms.csv', 'r')
            ->setDelimiter(';')
            ->setHeaderOffset(0)
        ;

        $records = (new Statement())
            ->offset(3)
            ->limit(5)
            ->process($csv);

        $converter = (new HTMLConverter())
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

    public function testTableTriggersException(): void
    {
        $this->expectException(DOMException::class);
        (new HTMLConverter())->table('table-csv-data', 'te st');
    }

    public function testToHTMLWithFormatter(): void
    {
        $csv = Reader::from(__DIR__.'/../test_files/prenoms.csv', 'r')
            ->setDelimiter(';')
            ->setHeaderOffset(0);

        $records = (new Statement())
            ->offset(3)
            ->limit(5)
            ->process($csv);

        $converter = (new HTMLConverter())
            ->table('table-csv-data', 'test')
            ->td('title')
            ->tr('data-record-offset')
            ->formatter(fn (array $record, int|string $key): array => array_map(strtoupper(...), $record));

        self::assertStringContainsString('ABEL', $converter->convert($records));
    }
}
