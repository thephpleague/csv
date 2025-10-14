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

use Dom\XMLDocument;
use DOMDocument;
use DOMException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Throwable;

use function array_map;
use function class_exists;
use function implode;
use function xdebug_get_headers;

#[Group('converter')]
final class XMLConverterTest extends TestCase
{
    public function testToXML(): void
    {
        $csv = Reader::from(__DIR__.'/../test_files/prenoms.csv', 'r')
            ->setDelimiter(';')
            ->setHeaderOffset(0)
        ;

        $stmt = (new Statement())
            ->offset(3)
            ->limit(5)
        ;

        $records = $stmt->process($csv);

        $converter = (new XMLConverter())
            ->rootElement('csv')
            ->recordElement('record', 'offset')
            ->fieldElement('field', 'name')
        ;

        /** @var DOMDocument|XMLDocument $dom */
        $dom = class_exists(XMLDocument::class) ? XMLDocument::createEmpty() : new DOMDocument(encoding: 'UTF-8');
        $dom->appendChild($converter->import($records, $dom));
        $record_list = $dom->getElementsByTagName('record');
        $record_node = $record_list->item(0);
        $field_list = $dom->getElementsByTagName('field');
        $field_node = $field_list->item(0);

        self::assertNotNull($dom->documentElement);
        self::assertSame('csv', $dom->documentElement->tagName);
        self::assertEquals(5, $record_list->length);
        self::assertNotNull($record_node);
        self::assertTrue($record_node->hasAttribute('offset'));
        self::assertEquals(20, $field_list->length);
        self::assertNotNull($field_node);
        self::assertTrue($field_node->hasAttribute('name'));
    }

    public function testXmlElementTriggersException(): void
    {
        $this->expectException(DOMException::class);
        (new XMLConverter())
            ->recordElement('record', '')
            ->rootElement('   ');
    }

    public function testImport(): void
    {
        $csv = Reader::from(__DIR__.'/../test_files/prenoms.csv', 'r')
            ->setDelimiter(';')
            ->setHeaderOffset(0)
        ;

        $stmt = (new Statement())
            ->offset(3)
            ->limit(5)
        ;

        $records = $stmt->process($csv);

        $converter = (new XMLConverter())
            ->rootElement('csv')
            ->recordElement('record', 'offset')
            ->fieldElement('field', 'name')
        ;

        $doc = class_exists(XMLDocument::class) ? XMLDocument::createEmpty() : new DOMDocument('1.0');
        $element = $converter->import($records, $doc);

        self::assertCount(0, $doc->childNodes);
        self::assertCount(5, $element->childNodes);
    }

    public function testDownload(): void
    {
        if (!function_exists('xdebug_get_headers')) {
            self::markTestSkipped(__METHOD__.' needs the xdebug extension to run');
        }

        ob_start();
        (new XMLConverter())->fieldElement('cell', 'name')->download([['foo' => 'bar']], 'foobar.xml');
        $output = (string) ob_get_clean();
        $headers = xdebug_get_headers();
        if ([] === $headers) {
            self::markTestSkipped(__METHOD__.' needs the xdebug function `xdebug_get_headers` to run and returns actual data.');
        }
        $header = implode("\n", $headers);

        self::assertStringContainsString('content-type: application/xml', strtolower($header));
        self::assertStringContainsString('content-transfer-encoding: binary', strtolower($header));
        self::assertStringContainsString('content-description: File Transfer', $header);
        self::assertStringContainsString('content-disposition: attachment;filename="foobar.xml"', $header);
        self::assertStringContainsString('<csv><row><cell name="foo">bar</cell></row></csv>', $output);
    }

    public function testToXMLWithFormatter(): void
    {
        $csv = Reader::from(__DIR__.'/../test_files/prenoms.csv', 'r')
            ->setDelimiter(';')
            ->setHeaderOffset(0)
        ;

        $stmt = (new Statement())
            ->offset(3)
            ->limit(5)
        ;

        $records = $stmt->process($csv);

        $converter = (new XMLConverter())
            ->rootElement('csv')
            ->recordElement('record', 'offset')
            ->fieldElement('field', 'name')
            ->formatter(fn (array $record, int|string $key): array => array_map(strtoupper(...), $record));
        ;

        /** @var DOMDocument|XMLDocument $dom */
        $dom = class_exists(XMLDocument::class) ? XMLDocument::createEmpty() : new DOMDocument(encoding: 'UTF-8');
        $dom->appendChild($converter->import($records, $dom));

        self::assertStringContainsString('ABEL', (string) $dom->saveXML());
    }

    #[Test]
    public function it_can_use_the_document_header_as_cell_name(): void
    {
        $csv = Reader::from(__DIR__.'/../test_files/prenoms.csv', 'r')
            ->setDelimiter(';')
            ->setHeaderOffset(0)
            ->slice(0, 2);

        $converter = (new XMLConverter())
            ->rootElement('stats')
            ->recordElement('per_name', 'offset')
            ->fieldElement(null);

        /** @var DOMDocument|XMLDocument $dom */
        $dom = class_exists(XMLDocument::class) ? XMLDocument::createEmpty() : new DOMDocument(encoding: 'UTF-8');
        $dom->appendChild($converter->import($csv, $dom));
        $dom->formatOutput = true;

        $generatedXml = (string) $dom->saveXML();

        self::assertStringContainsString('<per_name offset="1">', $generatedXml);
        self::assertStringContainsString('<nombre>55</nombre>', $generatedXml);
        self::assertStringContainsString('<sexe>M</sexe>', $generatedXml);
        self::assertStringContainsString('<annee>2004</annee>', $generatedXml);
    }

    #[Test]
    public function it_will_trigger_an_exception_if_the_header_is_invalid(): void
    {
        $csv = Reader::from(__DIR__.'/../test_files/prenoms.csv', 'r')
            ->setDelimiter(';')
            ->slice(0, 2);

        $converter = (new XMLConverter())
            ->rootElement('stats')
            ->recordElement('per_name', 'offset')
            ->fieldElement(null);

        /** @var DOMDocument|XMLDocument $dom */
        $dom = class_exists(XMLDocument::class) ? XMLDocument::createEmpty() : new DOMDocument(encoding: 'UTF-8');

        $this->expectException(Throwable::class);
        $converter->import($csv, $dom);
    }

    #[Test]
    #[DataProvider('provideHeader')]
    public function it_will_tell_if_it_can_use_a_specific_header(array $header, bool $expected): void
    {
        self::assertSame($expected, XMLConverter::supportsHeader($header));
    }

    public static function provideHeader(): iterable
    {
        yield 'no header' => ['header' => [], 'expected' => false];
        yield 'header with name value' => ['header' => ['foo', 'bar'], 'expected' => true];
        yield 'header with numeric index' => ['header' => [1, 'bar'], 'expected' => false];
        yield 'header with string as numeric index' => ['header' => ['1', 'bar'], 'expected' => false];
    }
}
