<?php

namespace LeagueTest\Csv;

use DOMDocument;
use DOMException;
use League\Csv\Encoder;
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

    private $encoder;

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

        $this->encoder = new Encoder();
    }

    public function tearDown()
    {
        $this->csv = null;
        $this->stmt = null;
    }

    public function testToHTML()
    {
        $converter = (new HTMLConverter())
            ->encoding('iso-8859-15')
            ->table('table-csv-data')
            ->td('title')
            ->tr('data-record-offset')
        ;

        $this->assertContains(
            '<td title="',
            $converter->convert($this->stmt->process($this->csv))
        );
    }

    public function testToXML()
    {
        $csv = Reader::createFromPath(__DIR__.'/data/foo.csv', 'r');
        $converter = (new XMLConverter())->encoding('iso-8859-15')->recordElement('row', 'offset');

        $this->assertInstanceOf(DOMDocument::class, $converter->convert($csv));
    }

    public function testToJson()
    {
        $converter = (new JsonConverter())->options(JSON_HEX_QUOT);
        $encoder = $this->encoder->inputEncoding('iso-8859-15');

        $records = $this->stmt->process($this->csv);
        $this->assertContains('[{', $converter->convert($encoder->encodeAll($records)));
        $this->assertContains('[{', $converter->convert($encoder->encodeAll($records->fetchAll())));
    }

    public function testEncodingTriggersException()
    {
        $this->expectException(InvalidArgumentException::class);
        (new Encoder())->inputEncoding('');
    }

    public function testXmlElementTriggersException()
    {
        $this->expectException(DOMException::class);
        (new XMLConverter())->rootElement('   ');
    }

    public function testJsonEncodingThrowsException()
    {
        $this->expectException(RuntimeException::class);
        (new JsonConverter())->convert($this->stmt->process($this->csv));
    }

    public function testEncoderRemainsTheSame()
    {
        $this->assertSame($this->encoder, $this->encoder->inputEncoding('utf-8'));
        $this->assertSame($this->encoder, $this->encoder->outputEncoding('UtF-8'));
        $this->assertNotEquals($this->encoder->outputEncoding('iso-8859-15'), $this->encoder);
    }

    public function testEncoderDoesNothing()
    {
        $expected = [['a' => 'bé']];
        $this->assertSame($expected, $this->encoder->encodeAll($expected));
        $this->assertSame($expected[0], ($this->encoder)($expected[0]));
        $this->assertNotSame($expected[0], $this->encoder->outputEncoding('utf-16')->__invoke($expected[0]));
    }
}
