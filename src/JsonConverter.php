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

use Closure;
use Generator;
use JsonException;
use OutOfBoundsException;
use RuntimeException;
use SplFileObject;
use const JSON_FORCE_OBJECT;
use const JSON_PARTIAL_OUTPUT_ON_ERROR;
use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;

final class JsonConverter
{
    private readonly string $start;
    private readonly string $end;
    private readonly string $separator;
    private readonly Closure $formatter;

    private function __construct(
        public readonly int $flags,
        /** @var positive-int */
        public readonly int $depth,
        public readonly bool $preserveOffset
    ) {
        $isPrettyPrint = ($this->flags & JSON_PRETTY_PRINT) === JSON_PRETTY_PRINT;
        $forceObject = ($this->flags & JSON_FORCE_OBJECT) === JSON_FORCE_OBJECT;
        [$eol, $space] = match (true) {
            $isPrettyPrint => ["\n", ' '],
            default => ['', ''],
        };
        $concat = match (true) {
            $this->preserveOffset => fn (int $incr, string|int $key, string $json): string => '"'.$key.'":'.$space.$json,
            $forceObject => fn (int $incr, string|int $key, string $json): string => '"'.$incr.'":'.$space.$json,
            default => fn (int $incr, string|int $key, string $json): string => $json,
        };

        [$this->start, $this->end] = match (true) {
            $this->preserveOffset || $forceObject => ['{'.$eol, $eol.'}'],
            default => ['['.$eol, $eol.']'],
        };
        $this->separator = ','.$eol;
        $this->formatter = match (true) {
            !$isPrettyPrint => $concat(...),
            default => fn (int $incr, string|int $key, string $json): string => (string) preg_replace('/^/m', '    ', $concat($incr, $key, $json)),
        };
    }

    public static function create(): self
    {
        return new self(0, 512, false);
    }

    public function preserveOffset(): self
    {
        return match (true) {
            true === $this->preserveOffset => $this,
            default => new self($this->flags, $this->depth, true),
        };
    }

    public function stripOffset(): self
    {
        return match (true) {
            false === $this->preserveOffset => $this,
            default => new self($this->flags, $this->depth, false),
        };
    }

    public function flags(int $flags): self
    {
        return match (true) {
            $flags === $this->flags => $this,
            default => new self($flags, $this->depth, $this->preserveOffset),
        };
    }

    public function depth(int $depth): self
    {
        return match (true) {
            $depth === $this->depth => $this,
            $depth < 1 => throw new OutOfBoundsException('The depth must be a positive integer equal or greater than 1.'),
            default => new self($this->flags, $depth, $this->preserveOffset),
        };
    }

    /**
     * Returns the generated JSON as a generator.
     *
     * @throws JsonException
     *
     * @return Generator<string>
     */
    public function convert(iterable $records): Generator
    {
        $flag = $this->flags | JSON_THROW_ON_ERROR & ~JSON_PARTIAL_OUTPUT_ON_ERROR;
        $increment = 0;
        yield $this->start;

        foreach ($records as $offset => $record) {
            if (0 !== $increment) {
                yield $this->separator;
            }

            yield ($this->formatter)(
                $increment++,
                $offset,
                /* @var string */
                json_encode($record, $flag, $this->depth)
            );
        }

        yield $this->end;
    }

    /**
     * Saves the generated JSON in a file object and returns the number of bytes written.
     *
     * @throws JsonException    If the JSON conversion fails
     * @throws RuntimeException If writing to the persistence layer fails
     */
    public function convertToFile(iterable $records, SplFileObject $file): int
    {
        return $this->convertTo($records, $file);
    }

    /**
     * Saves the generated JSON in a stream resource and returns the number of bytes written.
     *
     * @param resource $stream
     *
     * @throws JsonException    If the JSON conversion fails
     * @throws RuntimeException If writing to the persistence layer fails
     */
    public function convertToStream(iterable $records, $stream): int
    {
        return $this->convertTo($records, Stream::createFromResource($stream));
    }

    /**
     * @param resource|null $context the resource context
     *
     * @throws UnavailableStream  If the stream is not available
     * @throws JsonException      If the JSON conversion fails
     * @throws RuntimeException   If writing to the persistence layer fails
     */
    public function convertToPath(iterable $records, string $path, string $open_mode = 'w', $context = null): int
    {
        return $this->convertTo($records, Stream::createFromPath($path, $open_mode, $context));
    }

    /**
     * Saves the generated JSON in a stream resource or a file object and returns the number of bytes written.
     *
     * @throws JsonException    If the JSON conversion fails
     * @throws RuntimeException If writing to the persistence layer fails
     */
    private function convertTo(iterable $records, Stream|SplFileObject $stream): int
    {
        $bytes = 0;
        try {
            set_error_handler(fn (int $errno, string $errstr, string $errfile, int $errline) => true);
            foreach ($this->convert($records) as $json) {
                $res = $stream->fwrite($json);
                if (false === $res) {
                    throw new RuntimeException('Unable to write to the destination stream `'.$stream->getPathname().'`.');
                }

                $bytes += $res;
            }
        } finally {
            restore_error_handler();
        }

        return $bytes;
    }
}
