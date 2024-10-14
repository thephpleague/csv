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
use SplFileObject;
use SplTempFileObject;

use function chr;
use function function_exists;
use function ob_get_clean;
use function ob_start;
use function strtolower;
use function tmpfile;
use function unlink;
use function xdebug_get_headers;

use const PHP_EOL;

#[Group('csv')]
final class AbstractCsvTest extends TestCase
{
    private Reader $csv;
    private array $expected = [
        ['john', 'doe', 'john.doe@example.com'],
        ['jane', 'doe', 'jane.doe@example.com'],
    ];

    protected function setUp(): void
    {
        $tmp = new SplTempFileObject();
        foreach ($this->expected as $row) {
            $tmp->fputcsv($row);
        }

        $this->csv = Reader::createFromFileObject($tmp);
    }

    protected function tearDown(): void
    {
        unset($this->csv);
        @unlink(__DIR__.'/../test_files/newline.csv');
    }

    public function testCreateFromFileObjectPreserveFileObjectCsvControls(): void
    {
        $delimiter = "\t";
        $enclosure = '?';
        $escape = '^';
        $file = new SplTempFileObject();
        $file->setCsvControl($delimiter, $enclosure, $escape);
        $obj = Reader::createFromFileObject($file);
        self::assertSame($delimiter, $obj->getDelimiter());
        self::assertSame($enclosure, $obj->getEnclosure());
        if (3 === count($file->getCsvControl())) {
            self::assertSame($escape, $obj->getEscape());
        }
    }

    public function testCreateFromPathThrowsRuntimeException(): void
    {
        $this->expectException(UnavailableStream::class);

        Reader::createFromPath(__DIR__.'/foo/bar', 'r');
    }

    #[DataProvider('bomProvider')]
    public function testGetInputBOM(string $expected, string $str, string $delimiter): void
    {
        self::assertSame($expected, Reader::createFromString($str)->setDelimiter($delimiter)->getInputBOM());
    }

    public static function bomProvider(): array
    {
        $invalidBOM = <<<EOF
;\x00\x00\xFE\xFFworld
bonjour;planète
EOF;
        $validBOM = <<<EOF
\x00\x00\xFE\xFFworld
bonjour;planète
EOF;

        return [
            'invalid UTF32-BE' => ['', $invalidBOM, ';'],
            'valid UTF32-BE' => [Bom::Utf32Be->value, $validBOM, ';'],
        ];
    }

    public function testCloningIsForbidden(): void
    {
        $this->expectException(UnavailableStream::class);

        clone $this->csv;
    }

    public function testOutputSize(): void
    {
        ob_start();
        $length = $this->csv->download('test.csv');
        ob_end_clean();
        self::assertSame(60, $length);
    }

    public function testInvalidOutputFile(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->csv->download('invalid/file.csv');
    }

    public function testOutputHeaders(): void
    {
        if (!function_exists('xdebug_get_headers')) {
            self::markTestSkipped(__METHOD__.' needs the xdebug extension to run');
        }

        $raw_csv = Bom::Utf8->value."john,doe,john.doe@example.com\njane,doe,jane.doe@example.com\n";
        $csv = Reader::createFromString($raw_csv);
        ob_start();
        $csv->download('tést.csv');
        ob_end_clean();
        $headers = xdebug_get_headers();

        // Due to the variety of ways the xdebug expresses Content-Type of text files,
        // we cannot count on complete string matching.
        self::assertStringContainsString('content-type: text/csv', strtolower($headers[0]));
        self::assertSame('content-transfer-encoding: binary', strtolower($headers[1]));
        self::assertSame('content-description: File Transfer', $headers[2]);
        self::assertStringContainsString('content-disposition: attachment; filename="tst.csv"; filename*=utf-8\'\'t%C3%A9st.csv', $headers[3]);
    }

    public function testChunkDoesNotTimeoutAfterReading(): void
    {
        $raw_csv = "john,doe,john.doe@example.com\njane,doe,jane.doe@example.com\n";
        $csv = Reader::createFromString($raw_csv);

        self::assertSame($raw_csv, $csv->toString());
    }

    public function testStringRepresentation(): void
    {
        $raw_csv = "john,doe,john.doe@example.com\njane,doe,jane.doe@example.com\n";
        $csv = Reader::createFromString($raw_csv);

        self::assertSame($raw_csv, $csv->toString());
    }

    public function testToString(): void
    {
        $expected = "john,doe,john.doe@example.com\njane,doe,jane.doe@example.com\n";

        self::assertSame($expected, $this->csv->toString());
    }

    public function testChunkTriggersException(): void
    {
        $this->expectException(InvalidArgument::class);

        [...$this->csv->chunk(0)];
    }

    public function testChunk(): void
    {
        $raw_csv = Bom::Utf8->value."john,doe,john.doe@example.com\njane,doe,jane.doe@example.com\n";
        $csv = Reader::createFromString($raw_csv)->setOutputBOM(Bom::Utf32Be->value);
        $expected = Bom::Utf32Be->value."john,doe,john.doe@example.com\njane,doe,jane.doe@example.com\n";
        $res = '';
        foreach ($csv->chunk(32) as $chunk) {
            $res .= $chunk;
        }

        self::assertSame($expected, $res);
    }

    public function testSetOutputBOMTriggersException(): void
    {
        $this->expectException(InvalidArgument::class);

        Reader::createFromString()->setOutputBOM('toto');
    }

    #[DataProvider('provdesValidOutputBomSequences')]
    public function testSetOutputBOM(string $expected, Bom|string|null $bom): void
    {
        self::assertSame(
            $expected,
            Reader::createFromString()->setOutputBOM($bom)->getOutputBOM()
        );
    }

    public static function provdesValidOutputBomSequences(): array
    {
        return [
            'null' => [
                'expected' => '',
                'bom' => null,
            ],
            'empty string' => [
                'expected' => '',
                'bom' => '',
            ],
            'using a BOM string' => [
                'expected' => Bom::Utf8->value,
                'bom' => Bom::Utf8->value,
            ],
            'using a BOM instance' => [
                'expected' => Bom::Utf8->value,
                'bom' => Bom::Utf8,
            ],
        ];
    }

    #[DataProvider('provideCsvFilterTestingData')]
    public function testStreamFilterMode(
        AbstractCsv $csv,
        bool $useFilterRead,
        bool $useFilterWrite
    ): void {
        self::assertSame($useFilterRead, $csv->supportsStreamFilterOnRead());
        self::assertSame($useFilterWrite, $csv->supportsStreamFilterOnWrite());
    }

    public static function provideCsvFilterTestingData(): iterable
    {
        yield 'Reader with stream capability' => [
            'csv' => Reader::createFromString(),
            'useFilterRead' => true,
            'useFilterWrite' => false,
        ];

        yield 'Reader without stream capability' => [
            'csv' => Reader::createFromFileObject(new SplTempFileObject()),
            'useFilterRead' => false,
            'useFilterWrite' => false,
        ];

        yield 'Writer with stream capability' => [
            'csv' => Writer::createFromString(),
            'useFilterRead' => false,
            'useFilterWrite' => true,
        ];

        yield 'Writer without stream capability' => [
            'csv' => Writer::createFromFileObject(new SplTempFileObject()),
            'useFilterRead' => false,
            'useFilterWrite' => false,
        ];
    }

    public function testDelimiter(): void
    {
        $this->csv->setDelimiter('o');
        self::assertSame('o', $this->csv->getDelimiter());
        self::assertSame($this->csv, $this->csv->setDelimiter('o'));

        $this->expectException(InvalidArgument::class);

        $this->csv->setDelimiter('foo');
    }

    public function testBOMSettings(): void
    {
        self::assertSame('', $this->csv->getOutputBOM());

        $this->csv->setOutputBOM(Bom::Utf8->value);
        self::assertSame(Bom::Utf8->value, $this->csv->getOutputBOM());

        $this->csv->setOutputBOM('');
        self::assertSame('', $this->csv->getOutputBOM());
    }

    public function testAddBOMSequences(): void
    {
        $this->csv->setOutputBOM(Bom::Utf8->value);
        $expected = chr(239).chr(187).chr(191).'john,doe,john.doe@example.com'.PHP_EOL
            .'jane,doe,jane.doe@example.com'.PHP_EOL;
        self::assertSame($expected, $this->csv->toString());
    }

    public function testChangingBOMOnOutput(): void
    {
        $text = 'john,doe,john.doe@example.com'.PHP_EOL
            .'jane,doe,jane.doe@example.com'.PHP_EOL;
        $reader = Reader::createFromString(Bom::Utf32Be->value.$text);
        $reader->setOutputBOM(Bom::Utf8->value);

        self::assertSame(Bom::Utf8->value.$text, $reader->toString());
    }

    public function testEscape(): void
    {
        $this->csv->setEscape('o');
        self::assertSame('o', $this->csv->getEscape());
        self::assertSame($this->csv, $this->csv->setEscape('o'));

        $this->expectException(InvalidArgument::class);

        $this->csv->setEscape('foo');
    }

    public function testEnclosure(): void
    {
        $this->csv->setEnclosure('o');
        self::assertSame('o', $this->csv->getEnclosure());
        self::assertSame($this->csv, $this->csv->setEnclosure('o'));

        $this->expectException(InvalidArgument::class);

        $this->csv->setEnclosure('foo');
    }

    public function testAddStreamFilter(): void
    {
        $csv = Reader::createFromPath(__DIR__.'/../test_files/foo.csv');
        $csv->addStreamFilter('string.rot13');
        $csv->addStreamFilter('string.tolower');
        $csv->addStreamFilter('string.toupper');
        foreach ($csv as $row) {
            self::assertSame($row, ['WBUA', 'QBR', 'WBUA.QBR@RKNZCYR.PBZ']);
        }
    }

    public function testFailedAddStreamFilter(): void
    {
        $csv = Writer::createFromFileObject(new SplTempFileObject());
        self::assertFalse($csv->supportsStreamFilterOnWrite());

        $this->expectException(UnavailableFeature::class);

        $csv->addStreamFilter('string.toupper');
    }

    public function testFailedAddStreamFilterWithWrongFilter(): void
    {
        $this->expectException(InvalidArgument::class);

        /** @var resource $tmpfile */
        $tmpfile = tmpfile();

        Writer::createFromStream($tmpfile)
            ->addStreamFilter('foobar.toupper');
    }

    public function testStreamFilterDetection(): void
    {
        $filtername = 'string.toupper';
        $csv = Reader::createFromPath(__DIR__.'/../test_files/foo.csv');

        self::assertFalse($csv->hasStreamFilter($filtername));

        $csv->addStreamFilter($filtername);

        self::assertTrue($csv->hasStreamFilter($filtername));
    }

    public function testClearAttachedStreamFilters(): void
    {
        $path = __DIR__.'/../test_files/foo.csv';
        $csv = Reader::createFromPath($path);
        $csv->addStreamFilter('string.toupper');

        self::assertStringContainsString('JOHN', $csv->toString());

        $csv = Reader::createFromPath($path);

        self::assertStringNotContainsString('JOHN', $csv->toString());
    }

    public function testSetStreamFilterOnWriter(): void
    {
        $csv = Writer::createFromPath(__DIR__.'/../test_files/newline.csv', 'w+');
        $csv->addStreamFilter('string.toupper');
        $csv->insertOne([1, 'two', 3, "new\r\nline"]);

        self::assertStringContainsString("1,TWO,3,\"NEW\r\nLINE\"", $csv->toString());
    }

    public function testSetCsvControlWithDocument(): void
    {
        $raw_csv = "john,doe,john.doe@example.com\njane,doe,jane.doe@example.com\n";
        $csv = Reader::createFromString($raw_csv);
        $csv
            ->setDelimiter(',')
            ->setEnclosure('"')
            ->setEscape('|');

        self::assertSame('|', $csv->getEscape());
    }

    #[DataProvider('getPathnameProvider')]
    public function testGetPathname(string $path, string $expected): void
    {
        self::assertSame($expected, Reader::createFromPath($path)->getPathname());
        self::assertSame($expected, Reader::createFromFileObject(new SplFileObject($path))->getPathname());
        self::assertSame($expected, Writer::createFromFileObject(new SplFileObject($path))->getPathname());
        self::assertSame($expected, Writer::createFromFileObject(new SplFileObject($path))->getPathname());
    }

    public static function getPathnameProvider(): array
    {
        return [
            'absolute path' => [
                'path' => __DIR__.'/../test_files/foo.csv',
                'expected' => __DIR__.'/../test_files/foo.csv',
            ],
            'relative path' => [
                'path' => __DIR__.'/../test_files/foo.csv',
                'expected' => __DIR__.'/../test_files/foo.csv',
            ],
            'external uri' => [
                'path' => 'https://raw.githubusercontent.com/thephpleague/csv/8.2.3/test/data/foo.csv',
                'expected' => 'https://raw.githubusercontent.com/thephpleague/csv/8.2.3/test/data/foo.csv',
            ],
        ];
    }

    public function testGetPathnameWithTempFile(): void
    {
        self::assertSame('php://temp', Reader::createFromString('')->getPathname());
        self::assertSame('php://temp', Reader::createFromString(new SplTempFileObject())->getPathname());
        self::assertSame('php://temp', Reader::createFromFileObject(new SplTempFileObject())->getPathname());
        self::assertSame('php://temp', Writer::createFromString('')->getPathname());
        self::assertSame('php://temp', Writer::createFromString(new SplTempFileObject())->getPathname());
        self::assertSame('php://temp', Writer::createFromFileObject(new SplTempFileObject())->getPathname());
    }

    public function testBOMStripping(): void
    {
        $reader = Reader::createFromString();
        self::assertFalse($reader->isInputBOMIncluded());

        $reader->includeInputBOM();
        self::assertTrue($reader->isInputBOMIncluded());

        $reader->skipInputBOM();
        self::assertFalse($reader->isInputBOMIncluded());
    }

    public function testOutputStripBOM(): void
    {
        $raw_csv = Bom::Utf8->value."john,doe,john.doe@example.com\njane,doe,jane.doe@example.com\n";
        $csv = Reader::createFromString($raw_csv);
        $csv->setOutputBOM(Bom::Utf16Be->value);

        ob_start();
        $csv->download();
        /** @var string $result */
        $result = ob_get_clean();

        self::assertStringNotContainsString(Bom::Utf8->value, $result);
        self::assertTrue(str_starts_with($result, Bom::Utf16Be->value));
    }

    public function testOutputDoesNotStripBOM(): void
    {
        $raw_csv = Bom::Utf8->value."john,doe,john.doe@example.com\njane,doe,jane.doe@example.com\n";
        $csv = Reader::createFromString($raw_csv);
        $csv->setOutputBOM(Bom::Utf16Be->value);
        $csv->includeInputBOM();

        ob_start();
        $csv->download();
        /** @var string $result */
        $result = ob_get_clean();

        self::assertStringContainsString(Bom::Utf16Be->value, $result);
        self::assertStringContainsString(Bom::Utf8->value, $result);
        self::assertTrue(str_starts_with($result, Bom::Utf16Be->value.Bom::Utf8->value));
    }
}
