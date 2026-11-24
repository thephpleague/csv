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

namespace League\Csv\Security;

use League\Csv\CharsetConverter;
use League\Csv\Reader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * CharsetConverter memory usage on input containing invalid byte sequences.
 */
#[Group('security')]
final class CharsetConverterBufferingTest extends TestCase
{
    /** @var list<string> */
    private array $tmpFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tmpFiles as $file) {
            @unlink($file);
        }
        $this->tmpFiles = [];
    }

    private function tmpFile(string $content): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'csv-sec');
        file_put_contents($path, $content);
        $this->tmpFiles[] = $path;

        return $path;
    }

    private function hostileFile(int $lines): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'csv-sec');
        $this->tmpFiles[] = $path;
        $handle = fopen($path, 'wb');
        self::assertIsResource($handle);
        fwrite($handle, "\xFF,x\n"); // a single byte that is invalid in UTF-8
        $line = str_repeat('abcdefgh,', 100)."\n";
        for ($i = 0; $i < $lines; ++$i) {
            fwrite($handle, $line);
        }
        fclose($handle);

        return $path;
    }

    /**
     * A single invalid byte must not make CharsetConverter buffer the whole
     * remaining stream (and re-validate the growing buffer on every chunk).
     */
    #[Test]
    public function it_does_not_buffer_the_whole_stream_when_an_invalid_byte_is_found(): void
    {
        $path = $this->hostileFile(2_000); // ~1.8 MB
        $reader = Reader::from($path, 'r');
        CharsetConverter::addTo($reader, 'UTF-8', 'UTF-16LE');

        memory_reset_peak_usage();
        $baseline = memory_get_usage();
        foreach ($reader as $record) {
            break;
        }
        $delta = memory_get_peak_usage() - $baseline;

        self::assertLessThan(1_000_000, $delta, 'Reading the first record of a '.filesize($path).' bytes file used '.$delta.' bytes of memory.');
    }

    #[Test]
    public function it_streams_valid_input_without_buffering(): void
    {
        $path = $this->hostileFile(2_000);
        $handle = fopen($path, 'r+b');
        self::assertIsResource($handle);
        fwrite($handle, 'a');
        fclose($handle);

        $reader = Reader::from($path, 'r');
        CharsetConverter::addTo($reader, 'UTF-8', 'UTF-16LE');

        memory_reset_peak_usage();
        $baseline = memory_get_usage();
        foreach ($reader as $record) {
            break;
        }

        self::assertLessThan(1_000_000, memory_get_peak_usage() - $baseline);
    }

    #[Test]
    public function it_converts_multibyte_characters_split_across_chunks(): void
    {
        $path = $this->tmpFile(str_repeat('a', 8191)."é,b\n");
        $reader = Reader::from($path, 'r');
        CharsetConverter::addTo($reader, 'UTF-8', 'ISO-8859-1');

        self::assertSame(str_repeat('a', 8191)."\xE9", $reader->first()[0]);
    }

    #[Test]
    #[DataProvider('providesCharactersSplitAtTheEndOfTheFirstChunk')]
    public function it_keeps_a_split_character_when_the_chunk_contains_an_invalid_byte(string $character, int $bytesInFirstChunk): void
    {
        $padding = str_repeat('a', 8192 - 1 - $bytesInFirstChunk);
        $path = $this->tmpFile("\xFF".$padding.$character.",b\n");
        $reader = Reader::from($path, 'r');
        CharsetConverter::addTo($reader, 'UTF-8', 'UTF-8');

        self::assertSame(['?'.$padding.$character, 'b'], $reader->first());
    }

    /**
     * @return iterable<string, array{character: string, bytesInFirstChunk: int}>
     */
    public static function providesCharactersSplitAtTheEndOfTheFirstChunk(): iterable
    {
        yield 'two-byte character split 1+1' => ['character' => 'é', 'bytesInFirstChunk' => 1];
        yield 'three-byte character split 1+2' => ['character' => '日', 'bytesInFirstChunk' => 1];
        yield 'three-byte character split 2+1' => ['character' => '日', 'bytesInFirstChunk' => 2];
        yield 'four-byte character split 1+3' => ['character' => '💩', 'bytesInFirstChunk' => 1];
        yield 'four-byte character split 2+2' => ['character' => '💩', 'bytesInFirstChunk' => 2];
        yield 'four-byte character split 3+1' => ['character' => '💩', 'bytesInFirstChunk' => 3];
    }

    #[Test]
    public function it_does_not_prepend_a_split_character_to_the_stream_once_rewound(): void
    {
        $path = $this->tmpFile("\xFFa,b\n".str_repeat('a', 8186)."é\nc,d\n");
        $reader = Reader::from($path, 'r');
        CharsetConverter::addTo($reader, 'UTF-8', 'UTF-8');

        self::assertSame(['?a', 'b'], $reader->first());
        self::assertSame(['?a', 'b'], [...$reader][0]);
    }
}
