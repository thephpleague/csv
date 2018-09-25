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

use League\Csv\Exception;
use League\Csv\Parser;
use League\Csv\Reader;
use League\Csv\Stream;
use PHPUnit\Framework\TestCase;
use SplTempFileObject;
use TypeError;
use function iterator_to_array;

/**
 * @group reader
 * @coversDefaultClass League\Csv\Parser
 */
class ParserTest extends TestCase
{
    /**
     * @covers ::parse
     * @covers ::filterDocument
     */
    public function testConstructorThrowsTypeErrorWithUnknownDocument()
    {
        self::expectException(TypeError::class);
        foreach (Parser::parse([]) as $record) {
        }
    }

    /**
     * @covers ::parse
     * @covers ::filterControl
     */
    public function testConstructorThrowExceptionWithInvalidDelimiter()
    {
        self::expectException(Exception::class);
        foreach (Parser::parse(new SplTempFileObject(), 'toto') as $record) {
        }
    }

    /**
     * @covers ::parse
     * @covers ::filterControl
     */
    public function testConstructorThrowExceptionWithInvalidEnclosure()
    {
        self::expectException(Exception::class);
        foreach (Parser::parse(new SplTempFileObject(), ',', 'é') as $record) {
        }
    }

    /**
     * @covers \League\Csv\Stream::fgets
     * @covers ::parse
     * @covers ::filterDocument
     * @covers ::parse
     * @covers ::extractFieldContent
     * @covers ::extractEnclosedFieldContent
     */
    public function testWorksWithMultiLines()
    {
        $source = <<<EOF
Year,Make,Model,Description,Price
1997,Ford,E350,"ac, abs, moon",3000.00
1999,Chevy,"Venture ""Extended Edition""","",4900.00
1999,Chevy,"Venture ""Extended Edition, Very Large""",,5000.00
1996,Jeep,Grand Cherokee,"MUST SELL!
air, moon roof, loaded",4799.00
EOF;

        $multiline = <<<EOF
MUST SELL!
air, moon roof, loaded
EOF;
        $iterator = Parser::parse(Stream::createFromString($source));
        $data = iterator_to_array($iterator, false);
        self::assertCount(5, $data);
        self::assertSame($multiline, $data[4][3]);
    }

    /**
     * @covers \League\Csv\Stream::fgets
     * @covers ::parse
     * @covers ::extractFieldContent
     * @covers ::extractEnclosedFieldContent
     */
    public function testWorksWithMultiLinesWithDifferentDelimiter()
    {
        $source = <<<EOF
Year|Make|Model|Description|Price
1997|Ford|E350|'ac, abs, moon'|3000.00
1999|Chevy|'Venture ''Extended Edition'''|''|4900.00
1999|Chevy|'Venture ''Extended Edition| Very Large'''||5000.00
1996|Jeep|Grand Cherokee|'MUST SELL!
air| moon roof| loaded'|4799.00
EOF;

        $multiline = <<<EOF
MUST SELL!
air| moon roof| loaded
EOF;
        $doc = Stream::createFromString($source);
        $data = iterator_to_array(Parser::parse($doc, '|', "'"), false);
        self::assertCount(5, $data);
        self::assertSame($multiline, $data[4][3]);
    }

    /**
     * @covers ::parse
     * @covers ::extractFieldContent
     * @covers ::extractEnclosedFieldContent
     */
    public function testKeepEmptyLines()
    {
        $source = <<<EOF
"parent name","child name","title"


"parentA","childA","titleA"
EOF;

        $rsrc = new SplTempFileObject();
        $rsrc->fwrite($source);

        $data = iterator_to_array(Parser::parse($rsrc), false);
        self::assertCount(4, $data);
        self::assertSame(['parent name', 'child name', 'title'], $data[0]);
        self::assertSame([0 => null], $data[1]);
        self::assertSame([0 => null], $data[2]);
        self::assertSame(['parentA', 'childA', 'titleA'], $data[3]);
    }

    /**
     * @covers ::parse
     * @covers ::extractFieldContent
     * @covers ::extractEnclosedFieldContent
     */
    public function testNoTrimmedSpaceWithNotEncloseField()
    {
        $source = <<<EOF
Year,Make,Model,,Description,   Price
1997,  Ford  ,E350  ,ac, abs, moon,   3000.00
EOF;
        $data = iterator_to_array(Parser::parse(Stream::createFromString($source)), false);
        self::assertCount(2, $data);
        self::assertSame(['Year', 'Make', 'Model', '', 'Description', '   Price'], $data[0]);
        self::assertSame(['1997', '  Ford  ', 'E350  ', 'ac', ' abs', ' moon', '   3000.00'], $data[1]);
    }

    /**
     * @covers ::parse
     * @covers ::extractFieldContent
     * @covers ::extractEnclosedFieldContent
     *
     * @dataProvider invalidCsvRecordProvider
     */
    public function testHandlingInvalidCSVwithEnclosure(string $string, array $record)
    {
        $iterator = Parser::parse(Stream::createFromString($string));
        $data = iterator_to_array($iterator, false);
        self::assertSame($record, $data[0]);
    }

    public function invalidCsvRecordProvider()
    {
        return [
            'enclosure inside a non-unclosed field' => [
                'string' => 'Ye"ar,Make",Model,Description,Price',
                'record' => ['Ye"ar', 'Make"', 'Model', 'Description', 'Price'],
            ],
            'enclosure at the end of a non-unclosed field' => [
                'string' => 'Year,Make,Model,Description,Price"',
                'record' => ['Year', 'Make', 'Model', 'Description', 'Price"'],
            ],
            'enclosure at the end of a record field' => [
                'string' => 'Year,Make,Model,Description,"Price',
                'record' => ['Year', 'Make', 'Model', 'Description', 'Price'],
            ],
            'enclosure started but not ended' => [
                'string' => 'Year,Make,Model,Description,"Pri"ce',
                'record' => ['Year', 'Make', 'Model', 'Description', 'Price'],
            ],
        ];
    }

    /**
     * @covers ::parse
     * @covers ::extractFieldContent
     * @covers ::extractEnclosedFieldContent
     */
    public function testDoubleEnclosure()
    {
        $str = <<<EOF
Robert;Dupont;rue du Verger, 12;…
"Michel";"Durand";" av. de la Ferme, 89 ";…
"Michel ""Michele""";"Durand";" av. de la Ferme, 89";…
"Michel;Michele";"Durand";"av. de la Ferme, 89";…
EOF;

        $expected = [
            ['Robert', 'Dupont', 'rue du Verger, 12', '…'],
            ['Michel', 'Durand', ' av. de la Ferme, 89 ', '…'],
            ['Michel "Michele"', 'Durand', ' av. de la Ferme, 89', '…'],
            ['Michel;Michele', 'Durand',  'av. de la Ferme, 89', '…'],
        ];

        $stream = Stream::createFromString($str);
        $records = Parser::parse($stream, ';');
        self::assertEquals($expected, iterator_to_array($records, false));
    }

    /**
     * @covers ::parse
     * @covers ::extractFieldContent
     * @covers ::extractEnclosedFieldContent
     */
    public function testInvalidCsvParseAsFgetcsv()
    {
        $str = '"foo"bar",foo"bar'."\r\n".'"foo"'."\r\n".'baz,bar"';
        $csv = Reader::createFromString($str);
        $fgetcsv_records = iterator_to_array($csv);
        $csv->setEscape('');
        $parser_records = iterator_to_array($csv);
        self::assertEquals($fgetcsv_records, $parser_records);
    }

    public function testCsvParsedAsFgetcsv($value='')
    {
        $str = <<<EOF
"foo","foo bar","boo bar baz"
  "foo"  , "foo bar" ,    "boo bar baz"
EOF;
        $stream = Stream::createFromString($str);
        $records = iterator_to_array((Parser::parse($stream)), false);
        self::assertEquals(['foo', 'foo bar', 'boo bar baz'], $records[0]);
        self::assertEquals(['foo  ', 'foo bar ', 'boo bar baz'], $records[1]);
    }
}
