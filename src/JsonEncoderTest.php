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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SplTempFileObject;
use const JSON_PARTIAL_OUTPUT_ON_ERROR;
use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;

#[Group('converter')]
final class JsonEncoderTest extends TestCase
{
    private Reader $reader;
    private JsonEncoder $encoder;

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
        $this->encoder = JsonEncoder::create();
    }

    #[DataProvider('providesFlags')]
    public function testItCanSetTheJsonEncodeFlags(int $expectedFlags, int $flags): void
    {
        self::assertSame($expectedFlags, $this->encoder->flags($flags)->flags);
    }

    /**
     * @return iterable<string, array<string, int>>
     */
    public static function providesFlags(): iterable
    {
        yield 'no flags' => [
            'expectedFlags' => JSON_THROW_ON_ERROR & ~JSON_PARTIAL_OUTPUT_ON_ERROR,
            'flags' => 0,
        ];

        yield 'flags contains partial output error' => [
            'expectedFlags' => JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_PARTIAL_OUTPUT_ON_ERROR,
            'flags' => JSON_PRETTY_PRINT | JSON_PARTIAL_OUTPUT_ON_ERROR,
        ];

        yield 'flags contains json exception' => [
            'expectedFlags' => JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR,
            'flags' => JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR & ~JSON_PARTIAL_OUTPUT_ON_ERROR,
        ];
    }

    public function testItCanSetTheJsonEncodeDepth(): void
    {
        self::assertSame(512, $this->encoder->depth);
        self::assertSame(2, $this->encoder->depth(2)->depth);
    }

    public function testItFailsToSetTheJsonEncodeDepth(): void
    {
        $this->expectException(OutOfBoundsException::class);

        $this->encoder->depth(0);
    }

    public function testItCanSetTheJsonEncodeFlushThreshold(): void
    {
        self::assertNull($this->encoder->flushThreshold);
        self::assertSame(2, $this->encoder->flushThreshold(2)->flushThreshold);
    }

    public function testItFailsToSetTheJsonEncodeFlushThreshold(): void
    {
        $this->expectException(OutOfBoundsException::class);

        $this->encoder->flushThreshold(0);
    }

    public function testItCanIncludeTheTabularDataOffsetSettings(): void
    {
        $newConverter = $this->encoder->includeOffset();

        self::assertFalse($this->encoder->includeOffset);
        self::assertTrue($newConverter->includeOffset);

        self::assertSame($this->encoder, $this->encoder->excludeOffset());
        self::assertSame($newConverter, $newConverter->includeOffset());

        self::assertEquals($this->encoder, $newConverter->excludeOffset());
        self::assertNotSame($this->encoder, $newConverter->excludeOffset());
    }

    public function testItCanConvertTheTabularDataReaderIntoACsv(): void
    {
        $this->reader->setHeaderOffset(1);
        $content = implode('', [...$this->encoder->encode($this->reader)]);

        self::assertSame($content, json_encode($this->reader, JSON_THROW_ON_ERROR & ~JSON_PARTIAL_OUTPUT_ON_ERROR));
    }

    public function testItCanSaveTheTabularDataReaderIntoAJsonFileViaAStream(): void
    {
        /** @var resource $stream */
        $stream = tmpfile();

        $this->reader->setHeaderOffset(1);
        $this->encoder->encodeToStream($this->reader, $stream);

        rewind($stream);
        $contents = stream_get_contents($stream);

        self::assertSame($contents, json_encode($this->reader, JSON_THROW_ON_ERROR & ~JSON_PARTIAL_OUTPUT_ON_ERROR));
    }

    public function testItCanSaveTheTabularDataReaderIntoAJsonFileViaAFileObject(): void
    {
        $stream = new SplTempFileObject();

        $this->reader->setHeaderOffset(1);
        $this->encoder->encodeToFile($this->reader, $stream);

        $stream->rewind();
        $contents = $stream->fread(8000);

        self::assertSame($contents, json_encode($this->reader, JSON_THROW_ON_ERROR & ~JSON_PARTIAL_OUTPUT_ON_ERROR));
    }

    public function testItCanSaveTheTabularDataReaderIntoAJsonFileViaAPath(): void
    {
        $path = __DIR__.'/../test_files/csv.json';

        $this->reader->setHeaderOffset(1);
        $this->encoder->encodeToPath($this->reader, $path);

        /** @var string $contents */
        $contents = file_get_contents($path);

        self::assertSame($contents, json_encode($this->reader, JSON_THROW_ON_ERROR & ~JSON_PARTIAL_OUTPUT_ON_ERROR));
        self::assertStringStartsWith('[{', $contents);
    }

    public function testItCanSaveTheTabularDataReaderIntoAJsonFileViaAPathAndPreserveTheOffset(): void
    {
        $path = __DIR__.'/../test_files/csv.json';

        $this->reader->setHeaderOffset(1);
        $this->encoder->includeOffset()->encodeToPath($this->reader, $path);

        /** @var string $contents */
        $contents = file_get_contents($path);

        self::assertStringStartsWith('{"0":{', $contents);
    }

    public function testItCanSaveTheTabularDataReaderIntoAJsonFileViaAPathAndPreserveTheOffsetAndAddIndentation(): void
    {
        $path = __DIR__.'/../test_files/csv.json';

        $this->reader->setHeaderOffset(1);
        $this->encoder->includeOffset()->flags(JSON_PRETTY_PRINT)->encodeToPath($this->reader, $path);

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
        $this->encoder->encodeToPath($this->reader, $path, 'r');
    }
}
