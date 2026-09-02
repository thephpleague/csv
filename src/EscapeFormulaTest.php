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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
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
        $csv = Writer::fromString();
        $csv->addFormatter((new EscapeFormula())->escapeRecord(...));
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
        $csv = Writer::fromString();
        $csv->addFormatter($escaoeFormula->escapeRecord(...));
        $csv->insertOne($record);

        $reader = Reader::fromString($csv->toString());
        self::assertNotEquals($record, $reader->first());

        $reader->addFormatter($escaoeFormula->unescapeRecord(...));
        self::assertSame($record, $reader->first());
    }

    public function testUnformatReader(): void
    {
        $formatter = new EscapeFormula();
        $input = "2,2017-07-25,\"Important Client\",\"'=2+5\",\"240\",\"'\ttab\",\"'\rcr\",\n";
        $reader = Reader::fromString($input)->setEnclosure('"');
        $result = array_map($formatter->unescapeRecord(...), iterator_to_array($reader));
        $formatted_records = [['2', '2017-07-25', 'Important Client', '=2+5', '240', "\ttab", "\rcr", '']];
        self::assertEquals($formatted_records, $result);
    }

    /**
     * @return iterable<string, array{EscapeFormula, array<mixed>}>
     */
    public static function provideRoundTripRecords(): iterable
    {
        $records = [
            'plain text' => ['normal', 'hello world', '2026-07-31', '240'],
            'formula triggers' => ['=2+5', '-123', '+123', '@handle', "\ttab", "\rcr"],
            'single trigger character' => ['=', '-', '+', '@'],
            'escape-led with formula trigger' => ["'=2+5", "'@handle", "'+1", "'-x"],
            'escape-led with escape character' => ["''quote", "'''"],
            'escape-led with plain text' => ["'hello", "'apostrophe"],
            'single escape character' => ["'"],
            'escape-led with multi-character escape' => ['xyfield', 'xy=2+5'],
            'custom special character' => ['|pipe', '|formula'],
            'empty string' => [''],
            'mixed value types' => [240, 0, null, 1.5, true, (object) 'yes'],
        ];
        $formatters = [
            'default escape' => new EscapeFormula(),
            'custom escape' => new EscapeFormula('`'),
            'custom escape and special characters' => new EscapeFormula('\\', ['|']),
            'multi-character escape' => new EscapeFormula('xy', ['|']),
            'empty escape' => new EscapeFormula(''),
        ];

        foreach ($formatters as $formatterName => $formatter) {
            foreach ($records as $recordName => $record) {
                yield $formatterName.' / '.$recordName => [$formatter, $record];
            }
        }
    }

    #[DataProvider('provideRoundTripRecords')]
    public function testEscapeRecordUnescapeRecordRoundTrip(EscapeFormula $formatter, array $record): void
    {
        self::assertSame($record, $formatter->unescapeRecord($formatter->escapeRecord($record)));
    }

    public function testEscapeRecordProtectsEscapeLeadingFields(): void
    {
        $formatter = new EscapeFormula();
        $record = ["'=2+5", "'hello", "'", "'@handle"];
        $expected = ["''=2+5", "''hello", "''", "''@handle"];
        self::assertSame($expected, $formatter->escapeRecord($record));
        self::assertNotEquals($formatter->escapeRecord(['=2+5']), $formatter->escapeRecord(["'=2+5"]));
    }

    public function testUnescapeRecordIgnoresLiteralEscapeLeadingFields(): void
    {
        $formatter = new EscapeFormula();
        self::assertSame(["'hello", "'plain"], $formatter->unescapeRecord(["'hello", "'plain"]));
    }

    public function testUnescapeIsInverseOfEscapeOnWriterReaderRoundTrip(): void
    {
        $formatter = new EscapeFormula();
        $record = ['id', '=2+5', "'=text you typed", "'@notmyhandle", 'normal'];
        $csv = Writer::fromString();
        $csv->addFormatter($formatter->escapeRecord(...));
        $csv->insertOne($record);

        $reader = Reader::fromString($csv->toString());
        $reader->addFormatter($formatter->unescapeRecord(...));
        self::assertSame($record, $reader->first());
    }

    public function testUnescapeRecordWithMultiCharacterEscape(): void
    {
        $formatter = new EscapeFormula('xy');
        self::assertSame(['xy=2+5', 'xy'], $formatter->unescapeRecord(['xyxy=2+5', 'xyxy']));
    }

    public function testEscapeRecordLeavesAnEmptyFieldUntouched(): void
    {
        self::assertSame([''], (new EscapeFormula())->escapeRecord(['']));
    }
}
