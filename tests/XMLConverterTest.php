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

use DOMDocument;
use DOMElement;
use DOMException;
use DOMNodeList;
use League\Csv\Reader;
use League\Csv\Statement;
use League\Csv\XMLConverter;
use PHPUnit\Framework\TestCase;

/**
 * @group converter
 * @coversDefaultClass League\Csv\XMLConverter
 */
class XMLConverterTest extends TestCase
{
    /**
     * @covers ::rootElement
     * @covers ::recordElement
     * @covers ::fieldElement
     * @covers ::convert
     * @covers ::recordToElement
     * @covers ::recordToElementWithAttribute
     * @covers ::fieldToElement
     * @covers ::fieldToElementWithAttribute
     * @covers ::filterAttributeName
     * @covers ::filterElementName
     */
    public function testToXML(): void
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

        $converter = (new XMLConverter())
            ->rootElement('csv')
            ->recordElement('record', 'offset')
            ->fieldElement('field', 'name')
        ;

        $dom = $converter->convert($records);
        $record_list = $dom->getElementsByTagName('record');
        $field_list = $dom->getElementsByTagName('field');
        self::assertInstanceOf(DOMNodeList::class, $field_list);
        self::assertEquals(20, $field_list->length);

        $node = $field_list->item(0);
        self::assertInstanceOf(DOMElement::class, $node);
        self::assertTrue($node->hasAttribute('name'));

        self::assertInstanceOf(DOMDocument::class, $dom);
        self::assertSame(1, $dom->getElementsByTagName('csv')->count());
        self::assertEquals(5, $record_list->length);

        $node = $record_list->item(0);
        self::assertInstanceOf(DOMElement::class, $node);
        self::assertTrue($node->hasAttribute('offset'));
    }

    /**
     * @covers ::rootElement
     * @covers ::filterAttributeName
     * @covers ::filterElementName
     */
    public function testXmlElementTriggersException(): void
    {
        self::expectException(DOMException::class);
        (new XMLConverter())
            ->recordElement('record', '')
            ->rootElement('   ');
    }
}
