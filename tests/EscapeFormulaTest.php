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

use InvalidArgumentException;
use League\Csv\EscapeFormula;
use League\Csv\Writer;
use PHPUnit\Framework\TestCase;
use SplTempFileObject;
use TypeError;

/**
 * @group filter
 * @coversDefaultClass League\Csv\EscapeFormula
 */
class EscapeFormulaTest extends TestCase
{
    /**
     * @covers ::__construct
     * @covers ::filterSpecialCharacters
     */
    public function testConstructorThrowsTypError()
    {
        self::expectException(TypeError::class);
        new EscapeFormula("\t", [(object) 'i']);
    }

    /**
     * @covers ::__construct
     * @covers ::getSpecialCharacters
     * @covers ::filterSpecialCharacters
     */
    public function testConstructorThrowsInvalidArgumentException()
    {
        self::expectException(InvalidArgumentException::class);
        new EscapeFormula("\t", ['i', 'foo']);
    }

    /**
     * @covers ::__construct
     * @covers ::getEscape
     */
    public function testGetEscape()
    {
        $formatter = new EscapeFormula();
        self::assertSame("\t", $formatter->getEscape());
        $formatterBis = new EscapeFormula("\n");
        self::assertSame("\n", $formatterBis->getEscape());
    }

    /**
     * @covers ::__construct
     * @covers ::getSpecialCharacters
     * @covers ::filterSpecialCharacters
     */
    public function testGetSpecialChars()
    {
        $formatter = new EscapeFormula();
        self::assertNotContains('i', $formatter->getSpecialCharacters());
        $formatterBis = new EscapeFormula("\t", ['i']);
        self::assertContains('i', $formatterBis->getSpecialCharacters());
    }

    /**
     * @covers ::escapeRecord
     * @covers ::escapeField
     * @covers ::isStringable
     */
    public function testEscapeRecord()
    {
        $record = ['2', '2017-07-25', 'Important Client', '=2+5', 240, null, (object) 'yes'];
        $expected = ['2', '2017-07-25', 'Important Client', "\t=2+5", 240, null, (object) 'yes'];
        $formatter = new EscapeFormula();
        self::assertEquals($expected, $formatter->escapeRecord($record));
    }

    /**
     * @covers ::__invoke
     * @covers ::escapeRecord
     * @covers ::escapeField
     * @covers ::isStringable
     */
    public function testFormatterOnWriter()
    {
        $record = ['2', '2017-07-25', 'Important Client', '=2+5', 240, null];
        $expected = "2,2017-07-25,\"Important Client\",\"\t=2+5\",240,\n";
        $csv = Writer::createFromFileObject(new SplTempFileObject());
        $csv->addFormatter(new EscapeFormula());
        $csv->insertOne($record);
        self::assertContains($expected, $csv->getContent());
    }
}
