<?php

/**
 * League.Csv (https://csv.thephpleague.com).
 *
 * @author  Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @license https://github.com/thephpleague/csv/blob/master/LICENSE (MIT License)
 * @version 9.1.5
 * @link    https://github.com/thephpleague/csv
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace LeagueTest\Csv;

use League\Csv\Reader;
use League\Csv\RFC4180Iterator;
use PHPUnit\Framework\TestCase;
use SplTempFileObject;
use TypeError;

/**
 * @group reader
 * @coversDefaultClass League\Csv\RFC4180Iterator
 */
class RFC4180IteratorTest extends TestCase
{
    public function testConstructorThrowsTypeErrorWithUnknownDocument()
    {
        self::expectException(TypeError::class);
        new RFC4180Iterator([]);
    }

    /**
     * @covers ::getIterator
     * @covers \League\Csv\Stream::fgetc
     * @covers \League\Csv\Reader::getDocument
     */
    public function testReaderWithEmptyEscapeChar1()
    {
        $source = <<<EOF
Year,Make,Model,Description,Price
1997,Ford,E350,"ac, abs, moon",3000.00
1999,Chevy,"Venture ""Extenéded Edition""","",4900.00
1999,Chevy,"Venture ""Extended Edition, Very Large""",,5000.00
1996,Jeep,Grand Cherokee,"MUST SELL!
air, moon roof, loaded",4799.00
EOF;
        $csv = Reader::createFromString($source);
        $csv->setEscape('');
        self::assertCount(5, $csv);
        $csv->setHeaderOffset(0);
        self::assertCount(4, $csv);
    }

    /**
     * @covers ::getIterator
     * @covers \League\Csv\Reader::getDocument
     */
    public function testReaderWithEmptyEscapeChar2()
    {
        $source = '"parent name","child name","title"
            "parentA","childA","titleA"';

        $spl = new SplTempFileObject();
        $spl->fwrite($source);

        $csv = Reader::createFromFileObject($spl);
        $csv->setEscape('');
        self::assertCount(2, $csv);
        $csv->setHeaderOffset(0);
        self::assertCount(1, $csv);
    }
}
