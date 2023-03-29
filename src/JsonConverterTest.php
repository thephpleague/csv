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

use OutOfBoundsException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SplTempFileObject;
use const JSON_PRETTY_PRINT;

#[Group('converter')]
final class JsonConverterTest extends TestCase
{
    private Reader $reader;
    private JsonConverter $converter;

    protected function setUp(): void
    {
        parent::setUp();
        $data = <<<CSV
field 1,field 2,field 3
one,two,3
1,two,three
one,2,"three
two one"
CSV;
        $this->reader = Reader::createFromString($data);
        $this->converter = JsonConverter::create();
    }

    public function testItCanSetTheJsonEncodeFlags(): void
    {
        self::assertSame(0, $this->converter->flags);
        self::assertSame(JSON_PRETTY_PRINT, $this->converter->flags(JSON_PRETTY_PRINT)->flags);
    }

    public function testItCanSetTheJsonEncodeDepth(): void
    {
        self::assertSame(512, $this->converter->depth);
        self::assertSame(2, $this->converter->depth(2)->depth);
    }

    public function testItFailsToSetTheJsonEncodeDepth(): void
    {
        $this->expectException(OutOfBoundsException::class);

        $this->converter->depth(0);
    }

    public function testItCanPreserveTheTabularDataOffsetSettings(): void
    {
        $newConverter = $this->converter->preserveOffset();

        self::assertFalse($this->converter->preserveOffset);
        self::assertTrue($newConverter->preserveOffset);

        self::assertSame($this->converter, $this->converter->stripOffset());
        self::assertSame($newConverter, $newConverter->preserveOffset());

        self::assertEquals($this->converter, $newConverter->stripOffset());
        self::assertNotSame($this->converter, $newConverter->stripOffset());
    }

    public function testItCanConvertTheTabularDataReaderIntoACsv(): void
    {
        $this->reader->setHeaderOffset(1);
        $content = implode('', [...$this->converter->convert($this->reader)]);

        self::assertSame($content, json_encode($this->reader));
    }

    public function testItCanSaveTheTabularDataReaderIntoAJsonFileViaAStream(): void
    {
        /** @var resource $stream */
        $stream = tmpfile();

        $this->reader->setHeaderOffset(1);
        $this->converter->convertToStream($this->reader, $stream);

        rewind($stream);
        $contents = stream_get_contents($stream);

        self::assertSame($contents, json_encode($this->reader));
    }

    public function testItCanSaveTheTabularDataReaderIntoAJsonFileViaAFileObject(): void
    {
        $stream = new SplTempFileObject();

        $this->reader->setHeaderOffset(1);
        $this->converter->convertToFile($this->reader, $stream);

        $stream->rewind();
        $contents = $stream->fread(8000);

        self::assertSame($contents, json_encode($this->reader));
    }

    public function testItCanSaveTheTabularDataReaderIntoAJsonFileViaAPath(): void
    {
        $path = __DIR__.'/../test_files/csv.json';

        $this->reader->setHeaderOffset(1);
        $this->converter->convertToPath($this->reader, $path);

        /** @var string $contents */
        $contents = file_get_contents($path);

        self::assertSame($contents, json_encode($this->reader));
        self::assertStringStartsWith('[{', $contents);
    }

    public function testItCanSaveTheTabularDataReaderIntoAJsonFileViaAPathAndPreserveTheOffset(): void
    {
        $path = __DIR__.'/../test_files/csv.json';

        $this->reader->setHeaderOffset(1);
        $this->converter->preserveOffset()->convertToPath($this->reader, $path);

        /** @var string $contents */
        $contents = file_get_contents($path);

        self::assertStringStartsWith('{"0":{', $contents);
    }

    public function testItCanSaveTheTabularDataReaderIntoAJsonFileViaAPathAndPreserveTheOffsetAndAddIndentation(): void
    {
        $path = __DIR__.'/../test_files/csv.json';

        $this->reader->setHeaderOffset(1);
        $this->converter->preserveOffset()->flags(JSON_PRETTY_PRINT)->convertToPath($this->reader, $path);

        /** @var string $contents */
        $contents = file_get_contents($path);

        $expected = <<<EOF
{
    "0": {
EOF;
        self::assertStringStartsWith($expected, $contents);
    }

    public function testItFailsToSaveToAReadOnlyPath(): void
    {
        $this->expectException(RuntimeException::class);

        $path = __DIR__.'/../test_files/csv.json';

        $this->reader->setHeaderOffset(1);
        $this->converter->convertToPath($this->reader, $path, 'r');
    }
}
