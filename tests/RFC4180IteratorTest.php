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

use League\Csv\RFC4180Iterator;
use League\Csv\Stream;
use PHPUnit\Framework\TestCase;
use SplTempFileObject;
use TypeError;
use function iterator_to_array;

/**
 * @group reader
 * @coversDefaultClass League\Csv\RFC4180Iterator
 */
class RFC4180IteratorTest extends TestCase
{
    /**
     * @covers ::__construct
     */
    public function testConstructorThrowsTypeErrorWithUnknownDocument()
    {
        self::expectException(TypeError::class);
        new RFC4180Iterator([]);
    }

    /**
     * @covers \League\Csv\Stream::fgets
     * @covers ::__construct
     * @covers ::getIterator
     * @covers ::extractField
     * @covers ::extractFieldEnclosed
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
        $iterator = new RFC4180Iterator(Stream::createFromString($source));
        self::assertCount(5, $iterator);
        $data = iterator_to_array($iterator->getIterator(), false);
        self::assertSame($multiline, $data[4][3]);
    }

    /**
     * @covers \League\Csv\Stream::fgets
     * @covers ::getIterator
     * @covers ::extractField
     * @covers ::extractFieldEnclosed
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
        $doc->setCsvControl('|', "'");
        $iterator = new RFC4180Iterator($doc);
        self::assertCount(5, $iterator);
        $data = iterator_to_array($iterator->getIterator(), false);
        self::assertSame($multiline, $data[4][3]);
    }

    /**
     * @covers ::getIterator
     * @covers ::extractField
     * @covers ::extractFieldEnclosed
     */
    public function testKeepEmptyLines()
    {
        $source = <<<EOF
"parent name","child name","title"


"parentA","childA","titleA"
EOF;

        $rsrc = new SplTempFileObject();
        $rsrc->fwrite($source);
        $iterator = new RFC4180Iterator($rsrc);

        self::assertCount(4, $iterator);
        $data = iterator_to_array($iterator->getIterator(), false);
        self::assertSame(['parent name', 'child name', 'title'], $data[0]);
        self::assertSame([0 => null], $data[1]);
        self::assertSame([0 => null], $data[2]);
        self::assertSame(['parentA', 'childA', 'titleA'], $data[3]);
    }

    /**
     * @covers ::getIterator
     * @covers ::extractField
     * @covers ::extractFieldEnclosed
     */
    public function testTrimSpaceWithNotEncloseField()
    {
        $source = <<<EOF
Year,Make,Model,,Description,   Price
  "1997,Ford,E350,"ac, abs, moon",   3000.00
EOF;
        $iterator = new RFC4180Iterator(Stream::createFromString($source));
        self::assertCount(2, $iterator);
        $data = iterator_to_array($iterator->getIterator(), false);
        self::assertSame(['Year', 'Make', 'Model', '', 'Description', 'Price'], $data[0]);
        self::assertSame(['"1997', 'Ford', 'E350', 'ac, abs, moon', '3000.00'], $data[1]);
    }

    /**
     * @covers ::getIterator
     * @covers ::extractField
     * @covers ::extractFieldEnclosed
     *
     * @dataProvider invalidCsvRecordProvider
     */
    public function testHandlingInvalidCSVwithEnclosure(string $string, array $record)
    {
        $iterator = new RFC4180Iterator(Stream::createFromString($string));
        $data = iterator_to_array($iterator->getIterator(), false);
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
                'record' => ['Year', 'Make', 'Model', 'Description', 'Pri"ce'],
            ],
        ];
    }

    /**
     * @covers ::getIterator
     * @covers ::extractField
     * @covers ::extractFieldEnclosed
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

        $stream  = Stream::createFromString($str);
        $stream->setCsvControl(';');
        $records = new RFC4180Iterator($stream);
        self::assertEquals($expected, iterator_to_array($records->getIterator(), false));
    }
}
