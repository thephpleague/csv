<?php

namespace LeagueTest\Csv;

use League\Csv\CharsetConverter;
use League\Csv\Exception\RuntimeException;
use League\Csv\JsonConverter;
use League\Csv\Reader;
use League\Csv\Statement;
use PHPUnit\Framework\TestCase;

/**
 * @group converter
 */
class JsonConverterTest extends TestCase
{
    private $records;

    private $converter;

    public function setUp()
    {
        $csv = Reader::createFromPath(__DIR__.'/data/prenoms.csv', 'r')
            ->setDelimiter(';')
            ->setHeaderOffset(0)
        ;

        $stmt = (new Statement())
            ->offset(3)
            ->limit(5)
        ;

        $this->records = $stmt->process($csv);
        $this->converter = new JsonConverter();
    }

    public function tearDown()
    {
        $this->records = null;
        $this->converter = null;
    }

    public function testToJson()
    {
        $charset_converter = (new CharsetConverter())->inputEncoding('iso-8859-15');
        $this->assertContains('[{', $this->converter->convert($charset_converter->convert($this->records)), JSON_HEX_QUOT);
    }

    public function testJsonEncodingThrowsException()
    {
        $this->expectException(RuntimeException::class);
        $this->converter->convert($this->records);
    }
}
