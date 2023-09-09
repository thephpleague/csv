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

use ArrayIterator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use SplFileObject;
use SplTempFileObject;

use function array_map;
use function fclose;
use function fopen;
use function tmpfile;

#[Group('writer')]
final class WriterTest extends TestCase
{
    private Writer $csv;

    protected function setUp(): void
    {
        $this->csv = Writer::createFromFileObject(new SplTempFileObject());
    }

    protected function tearDown(): void
    {
        $csv = new SplFileObject(__DIR__.'/../test_files/foo.csv', 'w');
        $csv->setCsvControl();
        $csv->fputcsv(['john', 'doe', 'john.doe@example.com'], ',', '"');
        unset($this->csv);
    }

    public function testFlushThreshold(): void
    {
        $this->csv->setFlushThreshold(12);
        self::assertSame(12, $this->csv->getFlushThreshold());
        self::assertSame($this->csv, $this->csv->setFlushThreshold(12));
    }

    public function testFlushThresholdThrowsException(): void
    {
        $this->csv->setFlushThreshold(1);
        $this->expectException(InvalidArgument::class);
        $this->csv->setFlushThreshold(0);
    }

    public function testSupportsStreamFilter(): void
    {
        $csv = Writer::createFromPath(__DIR__.'/../test_files/foo.csv');

        self::assertTrue($csv->supportsStreamFilterOnWrite());

        $csv->setFlushThreshold(3);
        $csv->addStreamFilter('string.toupper');
        $csv->insertOne(['jane', 'doe', 'jane@example.com']);
        $csv->insertOne(['jane', 'doe', 'jane@example.com']);
        $csv->insertOne(['jane', 'doe', 'jane@example.com']);
        $csv->insertOne(['jane', 'doe', 'jane@example.com']);
        $csv->insertOne(['jane', 'doe', 'jane@example.com']);
        $csv->insertOne(['jane', 'doe', 'jane@example.com']);
        $csv->insertOne(['jane', 'doe', 'jane@example.com']);
        $csv->insertOne(['jane', 'doe', 'jane@example.com']);
        $csv->setFlushThreshold(null);

        self::assertStringContainsString('JANE,DOE,JANE@EXAMPLE.COM', $csv->toString());
    }

    public function testInsert(): void
    {
        $expected = [
            ['john', 'doe', 'john.doe@example.com'],
        ];
        foreach ($expected as $row) {
            $this->csv->insertOne($row);
        }
        self::assertStringContainsString('john,doe,john.doe@example.com', $this->csv->toString());
    }

    public function testInsertNormalFile(): void
    {
        $csv = Writer::createFromPath(__DIR__.'/../test_files/foo.csv', 'a+');
        $csv->insertOne(['jane', 'doe', 'jane.doe@example.com']);
        self::assertStringContainsString('jane,doe,jane.doe@example.com', $csv->toString());
    }

    #[DataProvider('inputDataProvider')]
    public function testInsertThrowsExceptionOnError(array $record): void
    {
        $this->expectException(CannotInsertRecord::class);

        Writer::createFromPath(__DIR__.'/../test_files/foo.csv', 'r')->insertOne($record);
    }

    public static function inputDataProvider(): array
    {
        return [
            'normal record' => [['foo', 'bar']],
            'empty record' => [[]],
        ];
    }

    #[DataProvider('dataToSave')]
    public function testSave(iterable $argument, string $expected): void
    {
        $this->csv->insertAll($argument);
        self::assertStringContainsString($expected, $this->csv->toString());
    }

    public static function dataToSave(): array
    {
        $multipleArray = [
            ['john', 'doe', 'john.doe@example.com'],
        ];

        return [
            'array' => [$multipleArray, 'john,doe,john.doe@example.com'],
            'iterator' => [new ArrayIterator($multipleArray), 'john,doe,john.doe@example.com'],
        ];
    }

    public function testToString(): void
    {
        /** @var resource $fp */
        $fp = fopen('php://temp', 'r+');
        $csv = Writer::createFromStream($fp);
        $csv->setDelimiter('|');

        $expected = [
            ['john', 'doe', 'john.doe@example.com'],
            ['jane', 'doe', 'jane.doe@example.com'],
        ];

        foreach ($expected as $row) {
            $csv->insertOne($row);
        }

        $expected = "john|doe|john.doe@example.com\njane|doe|jane.doe@example.com\n";
        self::assertSame($expected, $csv->toString());
        $csv = null;
        fclose($fp);
        $fp = null;
    }

    public function testCustomNewline(): void
    {
        /** @var resource $resource */
        $resource = tmpfile();
        $csv = Writer::createFromStream($resource);
        self::assertSame("\n", $csv->getEndOfLine());
        $csv->setEndOfLine("\r\n");
        $csv->insertOne(['jane', 'doe']);
        self::assertSame("jane,doe\r\n", $csv->toString());
        $csv = null;
    }

    public function testForceEnclosure(): void
    {
        $collection = [
            [1, 2],
            ['value 2-0', 'value 2-1'],
            ['to"to', 'foo\"bar'],
        ];

        $writer = Writer::createFromString();
        self::assertFalse($writer->encloseAll());

        $writer->forceEnclosure();
        self::assertTrue($writer->encloseAll());

        $writer->insertAll($collection);
        $csv = $writer->toString();

        self::assertStringContainsString('"1","2"'."\n", $csv);
        self::assertStringContainsString('"value 2-0","value 2-1"'."\n", $csv);
        self::assertStringContainsString('"to""to","foo\"bar"'."\n", $csv);

        $writer->relaxEnclosure();
        self::assertFalse($writer->encloseAll());
    }

    public function testAddValidationRules(): void
    {
        $this->expectException(CannotInsertRecord::class);
        $this->csv->addValidator(fn (array $row): bool => false, 'func1');
        $this->csv->insertOne(['jane', 'doe']);
    }

    public function testFormatterRules(): void
    {
        $this->csv->addFormatter(fn (array $row): array => array_map(strtoupper(...), $row));
        $this->csv->insertOne(['jane', 'doe']);
        self::assertSame("JANE,DOE\n", $this->csv->toString());
    }

    public function testWriterTriggerExceptionWithNonSeekableStream(): void
    {
        $this->expectException(UnavailableStream::class);
        $writer = Writer::createFromPath('php://null', 'w');
        $writer->setEndOfLine("\r\n");
        $writer->insertOne(['foo', 'bar']);
    }

    /**
     * @see https://bugs.php.net/bug.php?id=43225
     * @see https://bugs.php.net/bug.php?id=74713
     * @see https://bugs.php.net/bug.php?id=55413
     */
    #[DataProvider('compliantRFC4180Provider')]
    public function testRFC4180WriterMode(string $expected, array $record): void
    {
        foreach (["\r\n", "\n", "\r"] as $eol) {
            $csv = Writer::createFromString();
            $csv->setEndOfLine($eol);
            $csv->setEscape('');
            $csv->insertOne($record);
            self::assertSame($expected.$eol, $csv->toString());
        }
    }

    public static function compliantRFC4180Provider(): array
    {
        return [
            'bug #43225' => [
                'expected' => '"a\""",bbb',
                'record' => ['a\\"', 'bbb'],
            ],
            'bug #74713' => [
                'expected' => '"""@@"",""B"""',
                'record' => ['"@@","B"'],
            ],
            'bug #55413' => [
                'expected' => 'A,"Some ""Stuff""",C',
                'record' => ['A', 'Some "Stuff"', 'C'],
            ],
            'convert boolean' => [
                'expected' => ',"Some ""Stuff""",C',
                'record' => [false, 'Some "Stuff"', 'C'],
            ],
            'convert null value' => [
                'expected' => ',"Some ""Stuff""",C',
                'record' => [null, 'Some "Stuff"', 'C'],
            ],
            'bug #307' => [
                'expected' => '"a text string \\",...',
                'record' => ['a text string \\', '...'],
            ],
            'line starting with space' => [
                'expected' => '"  a",foo,bar',
                'record' => ['  a', 'foo', 'bar'],
            ],
            'line ending with space' => [
                'expected' => 'a,foo,"bar "',
                'record' => ['a', 'foo', 'bar '],
            ],
            'line containing space' => [
                'expected' => 'a,"foo bar",bar',
                'record' => ['a', 'foo bar', 'bar'],
            ],
            'multiline' => [
                'expected' => "a,\"foo bar\",\"multiline\r\nfield\",bar",
                'record' => ['a', 'foo bar', "multiline\r\nfield", 'bar'],
            ],
        ];
    }

    public function testEncloseAll(): void
    {
        /**
         * @see https://en.wikipedia.org/wiki/Comma-separated_values#Example
         */
        $records = [
            ['Year', 'Make', 'Model', 'Description', 'Price'],
            [1997, 'Ford', 'E350', 'ac,abs,moon', '3000.00'],
            [1999, 'Chevy', 'Venture "Extended Edition"', null, '4900.00'],
            [1999, 'Chevy', 'Venture "Extended Edition, Very Large"', null, '5000.00'],
            [1996, 'Jeep', 'Grand Cherokee', 'MUST SELL!
            air, moon roof, loaded', '4799.00'],
        ];

        $csv = Writer::createFromString();
        $csv->setDelimiter('|');
        $csv->forceEnclosure();
        $csv->insertAll($records);

        $expected = <<<CSV
"Year"|"Make"|"Model"|"Description"|"Price"
"1997"|"Ford"|"E350"|"ac,abs,moon"|"3000.00"
"1999"|"Chevy"|"Venture ""Extended Edition"""|""|"4900.00"
"1999"|"Chevy"|"Venture ""Extended Edition, Very Large"""|""|"5000.00"
"1996"|"Jeep"|"Grand Cherokee"|"MUST SELL!
            air, moon roof, loaded"|"4799.00"
CSV;
        self::assertStringContainsString($expected, $csv->toString());
    }
}
