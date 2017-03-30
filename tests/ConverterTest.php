<?php

namespace LeagueTest\Csv;

use DOMDocument;
use DOMException;
use League\Csv\CharsetConverter;
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

    private $charset_converter;

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

        $this->charset_converter = new CharsetConverter();
    }

    public function tearDown()
    {
        $this->csv = null;
        $this->stmt = null;
        $this->charset_converter = null;
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
        $encoder = $this->charset_converter->inputEncoding('iso-8859-15');

        $records = $this->stmt->process($this->csv);
        $this->assertContains('[{', $converter->convert($encoder->convert($records)));
        $this->assertContains('[{', $converter->convert($encoder->convert($records->fetchAll())));
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

    public function testCharsetConverterTriggersException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->charset_converter->inputEncoding('');
    }

    public function testCharsetConverterRemainsTheSame()
    {
        $this->assertSame($this->charset_converter, $this->charset_converter->inputEncoding('utf-8'));
        $this->assertSame($this->charset_converter, $this->charset_converter->outputEncoding('UtF-8'));
        $this->assertNotEquals($this->charset_converter->outputEncoding('iso-8859-15'), $this->charset_converter);
    }

    public function testCharsetConverterDoesNothing()
    {
        $expected = [['a' => 'bé']];
        $this->assertSame($expected, $this->charset_converter->convert($expected));
        $this->assertSame($expected[0], ($this->charset_converter)($expected[0]));
        $this->assertNotSame($expected[0], ($this->charset_converter->outputEncoding('utf-16'))($expected[0]));
    }

    public function testCharsetConverterAsStreamFilter()
    {
        CharsetConverter::registerStreamFilter();
        $res = stream_get_filters();
        $this->assertContains(CharsetConverter::STREAM_FILTERNAME.'.*', $res);

        $expected = 'Batman,Superman,Anaïs';
        $raw = mb_convert_encoding($expected, 'iso-8859-15', 'utf-8');
        $csv = Reader::createFromString($raw)
            ->addStreamFilter('string.toupper')
            ->addStreamFilter(CharsetConverter::getFiltername('iso-8859-15', 'utf-8'))
        ;
        $this->assertSame(strtoupper($expected), (string) $csv);
    }

    public function testCharsetConverterAsStreamFilterFailed()
    {
        $this->expectException(InvalidArgumentException::class);
        CharsetConverter::registerStreamFilter();
        $expected = 'Batman,Superman,Anaïs';
        $raw = mb_convert_encoding($expected, 'iso-8859-15', 'utf-8');
        $csv = Reader::createFromString($raw)
            ->addStreamFilter('string.toupper')
            ->addStreamFilter('convert.league.csv.iso-8859-15:utf-8')
        ;
    }
}
