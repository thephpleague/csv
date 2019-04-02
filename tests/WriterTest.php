<?php

/**
 * League.Csv (https://csv.thephpleague.com)
 *
 * (c) Ignace Nyamagana Butera <nyamsprod@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace LeagueTest\Csv;

use ArrayIterator;
use League\Csv\CannotInsertRecord;
use League\Csv\Exception;
use League\Csv\Writer;
use PHPUnit\Framework\TestCase;
use SplFileObject;
use SplTempFileObject;
use stdClass;
use TypeError;
use function array_map;
use function fclose;
use function fopen;
use function tmpfile;

/**
 * @group writer
 * @coversDefaultClass League\Csv\Writer
 */
class WriterTest extends TestCase
{
    private $csv;

    public function setUp(): void
    {
        $this->csv = Writer::createFromFileObject(new SplTempFileObject());
    }

    public function tearDown(): void
    {
        $csv = new SplFileObject(__DIR__.'/data/foo.csv', 'w');
        $csv->setCsvControl();
        $csv->fputcsv(['john', 'doe', 'john.doe@example.com'], ',', '"');
        $this->csv = null;
    }

    /**
     * @covers ::getFlushThreshold
     * @covers ::setFlushThreshold
     */
    public function testflushThreshold(): void
    {
        $this->csv->setFlushThreshold(12);
        self::assertSame(12, $this->csv->getFlushThreshold());
        self::assertSame($this->csv, $this->csv->setFlushThreshold(12));
    }

    /**
     * @covers ::setFlushThreshold
     */
    public function testflushThresholdThrowsException(): void
    {
        $this->csv->setFlushThreshold(1);
        self::expectException(Exception::class);
        $this->csv->setFlushThreshold(0);
    }

    public function testSupportsStreamFilter(): void
    {
        $csv = Writer::createFromPath(__DIR__.'/data/foo.csv');
        self::assertTrue($csv->supportsStreamFilter());
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
        self::assertContains('JANE,DOE,JANE@EXAMPLE.COM', $csv->getContent());
    }

    /**
     * @covers ::insertOne
     */
    public function testInsert(): void
    {
        $expected = [
            ['john', 'doe', 'john.doe@example.com'],
        ];
        foreach ($expected as $row) {
            $this->csv->insertOne($row);
        }
        self::assertContains('john,doe,john.doe@example.com', $this->csv->getContent());
    }

    /**
     * @covers ::insertOne
     * @covers ::addRecord
     */
    public function testInsertNormalFile(): void
    {
        $csv = Writer::createFromPath(__DIR__.'/data/foo.csv', 'a+');
        $csv->insertOne(['jane', 'doe', 'jane.doe@example.com']);
        self::assertContains('jane,doe,jane.doe@example.com', $csv->getContent());
    }

    /**
     * @covers ::insertOne
     * @dataProvider inputDataProvider
     */
    public function testInsertThrowsExceptionOnError(array $record): void
    {
        self::expectException(CannotInsertRecord::class);
        self::expectExceptionMessage('Unable to write record to the CSV document');
        Writer::createFromPath(__DIR__.'/data/foo.csv', 'r')->insertOne($record);
    }

    public function inputDataProvider(): iterable
    {
        return [
            'normal record' => [['foo', 'bar']],
            'empty record' => [[]],
        ];
    }

    /**
     * @covers ::insertAll
     */
    public function testFailedSaveWithWrongType(): void
    {
        self::expectException(TypeError::class);
        $this->csv->insertAll(new stdClass());
    }

    /**
     * @covers ::insertAll
     *
     * @dataProvider dataToSave
     */
    public function testSave(iterable $argument, string $expected): void
    {
        $this->csv->insertAll($argument);
        self::assertContains($expected, $this->csv->getContent());
    }

    public function dataToSave(): iterable
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
        self::assertSame($expected, $csv->getContent());
        $csv = null;
        fclose($fp);
        $fp = null;
    }

    /**
     * @covers ::setNewline
     * @covers ::getNewline
     * @covers ::insertOne
     * @covers ::consolidate
     * @covers League\Csv\Stream
     */
    public function testCustomNewline(): void
    {
        $csv = Writer::createFromStream(tmpfile());
        self::assertSame("\n", $csv->getNewline());
        $csv->setNewline("\r\n");
        $csv->insertOne(['jane', 'doe']);
        self::assertSame("jane,doe\r\n", $csv->getContent());
        $csv = null;
    }

    public function testAddValidationRules(): void
    {
        $func = function (array $row) {
            return false;
        };

        self::expectException(CannotInsertRecord::class);
        $this->csv->addValidator($func, 'func1');
        $this->csv->insertOne(['jane', 'doe']);
    }

    public function testFormatterRules(): void
    {
        $func = function (array $row) {
            return array_map('strtoupper', $row);
        };

        $this->csv->addFormatter($func);
        $this->csv->insertOne(['jane', 'doe']);
        self::assertSame("JANE,DOE\n", $this->csv->getContent());
    }

    /**
     * @covers League\Csv\Stream::fseek
     */
    public function testWriterTriggerExceptionWithNonSeekableStream(): void
    {
        self::expectException(Exception::class);
        $writer = Writer::createFromPath('php://output', 'w');
        $writer->setNewline("\r\n");
        $writer->insertOne(['foo', 'bar']);
    }

    /**
     * @see https://bugs.php.net/bug.php?id=43225
     * @see https://bugs.php.net/bug.php?id=74713
     * @see https://bugs.php.net/bug.php?id=55413
     *
     * @covers ::getInputBOM
     * @covers ::insertOne
     * @covers ::addRFC4180CompliantRecord
     * @covers ::addRecord
     *
     * @dataProvider compliantRFC4180Provider
     */
    public function testRFC4180WriterMode(string $expected, array $record): void
    {
        foreach (["\r\n", "\n", "\r"] as $eol) {
            $csv = Writer::createFromString();
            $csv->setNewline($eol);
            $csv->setEscape('');
            $csv->insertOne($record);
            self::assertSame($expected.$eol, $csv->getContent());
        }
    }

    public function compliantRFC4180Provider(): iterable
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
}
