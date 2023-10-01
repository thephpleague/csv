<?php

/**
 * League.Csv (https://csv.thephpleague.com)
 *
 * (c) Ignace Nyamagana Butera <nyamsprod@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace League\Csv;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use SplTempFileObject;
use TypeError;

#[Group('filter')]
final class EscapeFormulaTest extends TestCase
{
    public function testConstructorThrowsTypError(): void
    {
        $this->expectException(TypeError::class);
        new EscapeFormula("\t", [(object) 'i']); /* @phpstan-ignore-line */
    }

    public function testConstructorThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new EscapeFormula("\t", ['i', 'foo']);
    }

    public function testGetEscape(): void
    {
        $formatter = new EscapeFormula();
        self::assertSame("'", $formatter->getEscape());
        $formatterBis = new EscapeFormula("\n");
        self::assertSame("\n", $formatterBis->getEscape());
    }

    public function testGetSpecialChars(): void
    {
        $formatter = new EscapeFormula();
        self::assertNotContains('i', $formatter->getSpecialCharacters());
        $formatterBis = new EscapeFormula("\t", ['i']);
        self::assertContains('i', $formatterBis->getSpecialCharacters());
    }

    public function testEscapeRecord(): void
    {
        $record = ['2', '2017-07-25', 'Important Client', '=2+5', 240, null, (object) 'yes'];
        $expected = ['2', '2017-07-25', 'Important Client', "'=2+5", 240, null, (object) 'yes'];
        $formatter = new EscapeFormula();
        self::assertEquals($expected, $formatter->escapeRecord($record));
    }

    public function testFormatterOnWriter(): void
    {
        $record = ['2', '2017-07-25', 'Important Client', '=2+5', 240, "\ttab", "\rcr", null];
        $expected = "2,2017-07-25,\"Important Client\",'=2+5,240,\"'\ttab\",\"'\rcr\",\n";
        $csv = Writer::createFromFileObject(new SplTempFileObject());
        $csv->addFormatter(new EscapeFormula());
        $csv->insertOne($record);
        self::assertStringContainsString($expected, $csv->toString());
    }

    public function testUnescapeRecord(): void
    {
        $expected = ['2', '2017-07-25', 'Important Client', '=2+5', 240, null, (object) 'yes'];
        $record = ['2', '2017-07-25', 'Important Client', "'=2+5", 240, null, (object) 'yes'];
        $formatter = new EscapeFormula();
        self::assertEquals($expected, $formatter->unescapeRecord($record));
    }

    public function testFormatterOnReader(): void
    {
        $escaoeFormula = new EscapeFormula();
        $record = ['2', '2017-07-25', 'Important Client', '=2+5', '240', "\ttab", "\rcr", ''];
        $csv = Writer::createFromString();
        $csv->addFormatter($escaoeFormula->escapeRecord(...));
        $csv->insertOne($record);

        $reader = Reader::createFromString($csv->toString());
        self::assertNotEquals($record, $reader->first());

        $reader->addFormatter($escaoeFormula->unescapeRecord(...));
        self::assertSame($record, $reader->first());
    }

    public function testUnformatReader(): void
    {
        $formatter = new EscapeFormula();
        $input = "2,2017-07-25,\"Important Client\",\"'=2+5\",\"240\",\"'\ttab\",\"'\rcr\",\n";
        $reader = Reader::createFromString($input)->setEnclosure('"');
        $result = array_map($formatter->unescapeRecord(...), iterator_to_array($reader));
        $formatted_records = [['2', '2017-07-25', 'Important Client', '=2+5', '240', "\ttab", "\rcr", '']];
        self::assertEquals($formatted_records, $result);
    }
}
