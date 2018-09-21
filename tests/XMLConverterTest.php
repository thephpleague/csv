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

use DOMDocument;
use DOMException;
use League\Csv\Reader;
use League\Csv\Statement;
use League\Csv\XMLConverter;
use PHPUnit\Framework\TestCase;
use TypeError;

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
    public function testToXML()
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

        self::assertInstanceOf(DOMDocument::class, $dom);
        self::assertSame('csv', $dom->documentElement->tagName);
        self::assertEquals(5, $record_list->length);
        self::assertTrue($record_list->item(0)->hasAttribute('offset'));
        self::assertEquals(20, $field_list->length);
        self::assertTrue($field_list->item(0)->hasAttribute('name'));
    }

    /**
     * @covers ::rootElement
     * @covers ::filterAttributeName
     * @covers ::filterElementName
     */
    public function testXmlElementTriggersException()
    {
        self::expectException(DOMException::class);
        (new XMLConverter())
            ->recordElement('record', '')
            ->rootElement('   ');
    }

    /**
     * @covers ::convert
     */
    public function testXmlElementTriggersTypeError()
    {
        self::expectException(TypeError::class);
        (new XMLConverter())->convert('foo');
    }
}
