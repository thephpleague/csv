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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function fclose;
use function fgetcsv;
use function fread;
use function fwrite;
use function rewind;
use function str_repeat;
use function stream_get_contents;
use function stream_set_chunk_size;
use function substr;
use function tmpfile;

final class SwapDelimiterTest extends TestCase
{
    #[Test]
    public function it_can_swap_the_delimiter_on_read(): void
    {
        $document = <<<CSV
observedOn💩temperature💩place
2023-10-01💩18💩Yamoussokro
2023-10-02💩21💩Yamoussokro
2023-10-03💩15💩Yamoussokro
2023-10-01💩22💩Abidjan
2023-10-02💩19💩Abidjan
2023-10-03💩💩Abidjan
CSV;

        $reader = Reader::fromString($document);
        $reader->setDelimiter("\x02");
        SwapDelimiter::addTo($reader, '💩');
        $reader->setHeaderOffset(0);

        self::assertSame(
            ['observedOn' => '2023-10-01', 'temperature' => '18', 'place' => 'Yamoussokro'],
            $reader->first()
        );
    }

    #[Test]
    public function it_can_swap_the_delimiter_on_write(): void
    {
        $writer = Writer::fromString();
        $writer->setDelimiter("\x02");
        SwapDelimiter::addTo($writer, '💩');

        $writer->insertOne(['observeedOn' => '2023-10-01', 'temperature' => '18', 'place' => 'Yamoussokro']);
        self::assertSame('2023-10-01💩18💩Yamoussokro'."\n", $writer->toString());
    }

    #[Test]
    public function it_prepends_the_filter_before_existing_filters(): void
    {
        $stream = fopen('php://temp', 'w+');
        self::assertIsResource($stream);
        stream_filter_append($stream, 'string.rot13', STREAM_FILTER_WRITE);
        SwapDelimiter::prependTo($stream, 'a', ',');
        fwrite($stream, "x,y\n");
        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);

        self::assertSame("knl\n", $contents);
    }

    #[Test]
    public function it_appends_the_filter_after_existing_filters(): void
    {
        $stream = fopen('php://temp', 'w+');
        self::assertIsResource($stream);
        stream_filter_append($stream, 'string.rot13', STREAM_FILTER_WRITE);
        SwapDelimiter::appendTo($stream, 'a', ',');
        fwrite($stream, "x,y\n");
        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);

        self::assertSame("kal\n", $contents);
    }

    #[Test]
    public function it_can_swap_the_delimiter_on_read_with_stream_and_small_chunk_size(): void
    {
        $document = <<<CSV
observedOn💩temperature💩place
2023-10-01💩18💩Yamoussokro
2023-10-02💩21💩Yamoussokro
2023-10-03💩15💩Yamoussokro
2023-10-01💩22💩Abidjan
2023-10-02💩19💩Abidjan
2023-10-03💩💩Abidjan
CSV;
        $stream = tmpfile();
        stream_set_chunk_size($stream, 1);
        self::assertIsResource($stream);
        fwrite($stream, $document);
        rewind($stream);
        SwapDelimiter::appendTo($stream, '💩', ',');

        $records = [];
        while (($fields = fgetcsv($stream, escape: '')) !== false) {
            $records[] = $fields;
        }

        self::assertCount(7, $records);
        self::assertSame(['observedOn', 'temperature', 'place'], $records[0]);
    }

    #[Test]
    #[DataProvider('providesDelimiterSplitPositions')]
    public function it_does_not_prepend_a_split_delimiter_to_the_stream_once_rewound(int $bytesInFirstChunk): void
    {
        // the delimiter is split between the first two chunks of 8192 bytes
        $padding = str_repeat('a', 8192 - $bytesInFirstChunk);
        $reader = Reader::fromString($padding."💩b\nc💩d\n");
        SwapDelimiter::addTo($reader, '💩');

        self::assertSame([$padding, 'b'], $reader->first());
        self::assertSame([[$padding, 'b'], ['c', 'd']], [...$reader]);
    }

    #[Test]
    #[DataProvider('providesDelimiterSplitPositions')]
    public function it_swaps_a_split_delimiter_once_the_stream_is_rewound(int $bytesInFirstChunk): void
    {
        $padding = str_repeat('a', 8192 - $bytesInFirstChunk);

        /** @var resource $stream */
        $stream = tmpfile();
        fwrite($stream, $padding."💩b\n");
        rewind($stream);
        SwapDelimiter::appendTo($stream, '💩', ',');

        self::assertSame(substr($padding, 0, 10), fread($stream, 10));
        rewind($stream);
        self::assertSame($padding.",b\n", stream_get_contents($stream));
        fclose($stream);
    }

    /**
     * @return iterable<string, array{bytesInFirstChunk: int}>
     */
    public static function providesDelimiterSplitPositions(): iterable
    {
        yield 'split 1+3' => ['bytesInFirstChunk' => 1];
        yield 'split 2+2' => ['bytesInFirstChunk' => 2];
        yield 'split 3+1' => ['bytesInFirstChunk' => 3];
    }
}
