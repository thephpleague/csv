<?php

/**
 * League.Csv (https://csv.thephpleague.com)
 *
 * (c) Ignace Nyamagana Butera <nyamsprod@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace League\Csv;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SplTempFileObject;
use TypeError;

/**
 * @group filter
 * @coversDefaultClass \League\Csv\EscapeFormula
 */
final class EscapeFormulaTest extends TestCase
{
    /**
     * @covers ::__construct
     * @covers ::filterSpecialCharacters
     */
    public function testConstructorThrowsTypError(): void
    {
        $this->expectException(TypeError::class);
        new EscapeFormula("\t", [(object) 'i']);
    }

    /**
     * @covers ::__construct
     * @covers ::getSpecialCharacters
     * @covers ::filterSpecialCharacters
     */
    public function testConstructorThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new EscapeFormula("\t", ['i', 'foo']);
    }

    /**
     * @covers ::__construct
     * @covers ::getEscape
     */
    public function testGetEscape(): void
    {
        $formatter = new EscapeFormula();
        self::assertSame("'", $formatter->getEscape());
        $formatterBis = new EscapeFormula("\n");
        self::assertSame("\n", $formatterBis->getEscape());
    }

    /**
     * @covers ::__construct
     * @covers ::getSpecialCharacters
     * @covers ::filterSpecialCharacters
     */
    public function testGetSpecialChars(): void
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
    public function testEscapeRecord(): void
    {
        $record = ['2', '2017-07-25', 'Important Client', '=2+5', 240, null, (object) 'yes'];
        $expected = ['2', '2017-07-25', 'Important Client', "'=2+5", 240, null, (object) 'yes'];
        $formatter = new EscapeFormula();
        self::assertEquals($expected, $formatter->escapeRecord($record));
    }

    /**
     * @covers ::__invoke
     * @covers ::escapeRecord
     * @covers ::escapeField
     * @covers ::isStringable
     */
    public function testFormatterOnWriter(): void
    {
        $record = ['2', '2017-07-25', 'Important Client', '=2+5', 240, "\ttab", "\rcr", null];
        $expected = "2,2017-07-25,\"Important Client\",'=2+5,240,\"'\ttab\",\"'\rcr\",\n";
        $csv = Writer::createFromFileObject(new SplTempFileObject());
        $csv->addFormatter(new EscapeFormula());
        $csv->insertOne($record);
        self::assertStringContainsString($expected, $csv->getContent());
    }
}
