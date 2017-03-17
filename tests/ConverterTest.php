<?php

namespace LeagueTest\Csv;

use DOMDocument;
use League\Csv\Exception\InvalidArgumentException;
use League\Csv\Exception\RuntimeException;
use League\Csv\HTMLConverter;
use League\Csv\JsonConverter;
use League\Csv\Reader;
use League\Csv\Statement;
use League\Csv\XMLConverter;
use PHPUnit\Framework\TestCase;

/**
 * @group encoder
 */
class ConverterTest extends TestCase
{
    private $csv;

    private $stmt;

    public function setUp()
    {
        $this->csv = Reader::createFromPath(__DIR__.'/data/prenoms.csv', 'r')
            ->setDelimiter(';')
            ->setHeaderOffset(0)
        ;

        $this->stmt = (new Statement())
            ->offset(3)
            ->limit(5)
        ;
    }

    public function tearDown()
    {
        $this->csv = null;
        $this->stmt = null;
    }

    public function testToHTML()
    {
        $encoder = (new HTMLConverter())
            ->className('table-csv-data')
            ->inputEncoding('iso-8859-15')
            ->fieldAttributeName('title')
            ->recordOffsetAttributeName('data-record-offset')
        ;

        $this->assertContains('<td title="', $encoder->convert($this->stmt->process($this->csv)));
    }

    public function testToXML()
    {
        $csv = Reader::createFromPath(__DIR__.'/data/foo.csv', 'r');
        $encoder = (new XMLConverter())->recordElement('row', 'offset');

        $this->assertInstanceOf(DOMDocument::class, $encoder->convert($csv));
    }

    public function testToJson()
    {
        $records = $this->stmt->process($this->csv);
        $encoder = (new JsonConverter())
            ->inputEncoding('iso-8859-15')
            ->options(JSON_HEX_QUOT)
        ;

        $this->assertContains('[{', $encoder->convert($records));
        $this->assertContains('[{', $encoder->convert($records->fetchAll()));
    }

    public function testEncodingTriggersException()
    {
        $this->expectException(InvalidArgumentException::class);
        (new XMLConverter())->inputEncoding('');
    }

    public function testXmlElementTriggersException()
    {
        $this->expectException(InvalidArgumentException::class);
        (new XMLConverter())->rootElement('   ');
    }

    public function testJsonEncodingThrowsException()
    {
        $this->expectException(RuntimeException::class);
        (new JsonConverter())->convert($this->stmt->process($this->csv));
    }
}
