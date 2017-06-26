<?php

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

        $this->assertInstanceOf(DOMDocument::class, $dom);
        $this->assertSame('csv', $dom->documentElement->tagName);
        $this->assertEquals(5, $record_list->length);
        $this->assertTrue($record_list->item(0)->hasAttribute('offset'));
        $this->assertEquals(20, $field_list->length);
        $this->assertTrue($field_list->item(0)->hasAttribute('name'));
    }

    /**
     * @covers ::rootElement
     * @covers ::filterAttributeName
     * @covers ::filterElementName
     */
    public function testXmlElementTriggersException()
    {
        $this->expectException(DOMException::class);
        (new XMLConverter())
            ->recordElement('record', '')
            ->rootElement('   ');
    }

    /**
     * @covers ::convert
     */
    public function testXmlElementTriggersTypeError()
    {
        $this->expectException(TypeError::class);
        (new XMLConverter())->convert('foo');
    }
}
