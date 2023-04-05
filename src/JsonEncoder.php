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

final class JsonEncoder
{
    private readonly string $start;
    private readonly string $end;
    private readonly string $separator;
    private readonly Closure $formatter;

    private function __construct(
        public readonly int $flags,
        /** @var positive-int */
        public readonly int $depth,
        public readonly bool $includeOffset,
        public readonly ?int $flushThreshold
    ) {
        $isPrettyPrint = ($this->flags & JSON_PRETTY_PRINT) === JSON_PRETTY_PRINT;
        $forceObject = ($this->flags & JSON_FORCE_OBJECT) === JSON_FORCE_OBJECT;
        [$eol, $space] = match (true) {
            $isPrettyPrint => ["\n", ' '],
            default => ['', ''],
        };
        $concat = match (true) {
            $this->includeOffset => fn (int $incr, string|int $key, string $json): string => '"'.$key.'":'.$space.$json,
            $forceObject => fn (int $incr, string|int $key, string $json): string => '"'.$incr.'":'.$space.$json,
            default => fn (int $incr, string|int $key, string $json): string => $json,
        };

        [$this->start, $this->end] = match (true) {
            $this->includeOffset || $forceObject => ['{'.$eol, $eol.'}'],
            default => ['['.$eol, $eol.']'],
        };
        $this->separator = ','.$eol;
        $this->formatter = match (true) {
            $isPrettyPrint => fn (int $incr, string|int $key, string $json): string => (string) preg_replace('/^/m', '    ', $concat($incr, $key, $json)),
            default => $concat(...),
        };
    }

    public static function create(): self
    {
        return new self(JSON_THROW_ON_ERROR & ~JSON_PARTIAL_OUTPUT_ON_ERROR, 512, false, null);
    }

    /**
     * Returns an instance that will include the iterable offset value.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified changes.
     */
    public function includeOffset(): self
    {
        return match (true) {
            true === $this->includeOffset => $this,
            default => new self($this->flags, $this->depth, true, $this->flushThreshold),
        };
    }

    /**
     * Returns an instance that will exclude the iterable offset value.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified changes.
     */
    public function excludeOffset(): self
    {
        return match (true) {
            false === $this->includeOffset => $this,
            default => new self($this->flags, $this->depth, false, $this->flushThreshold),
        };
    }

    /**
     * Returns an instance with the specified flags.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified changes.
     */
    public function flags(int $flags): self
    {
        return match (true) {
            $flags === $this->flags => $this,
            ($flags & JSON_PARTIAL_OUTPUT_ON_ERROR) === JSON_PARTIAL_OUTPUT_ON_ERROR => new self($flags | JSON_THROW_ON_ERROR, $this->depth, $this->includeOffset, $this->flushThreshold),
            default => new self($flags | JSON_THROW_ON_ERROR & ~JSON_PARTIAL_OUTPUT_ON_ERROR, $this->depth, $this->includeOffset, $this->flushThreshold),
        };
    }

    /**
     * Returns an instance with the specified recursion depth.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified changes.
     */
    public function depth(int $depth): self
    {
        return match (true) {
            $depth === $this->depth => $this,
            $depth < 1 => throw new OutOfBoundsException('The depth must be a positive integer equal or greater than 1.'),
            default => new self($this->flags, $depth, $this->includeOffset, $this->flushThreshold),
        };
    }

    /**
     * Returns an instance with the specified flush threshold.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified changes.
     */
    public function flushThreshold(?int $flushThreshold): self
    {
        return match (true) {
            $flushThreshold === $this->flushThreshold => $this,
            $flushThreshold < 1 => throw new OutOfBoundsException('The flush threshold must be null or a positive integer equal or greater than 1.'),
            default => new self($this->flags, $this->depth, $this->includeOffset, $flushThreshold),
        };
    }

    /**
     * Returns the generated JSON as a generator.
     *
     * @param iterable<mixed> $records
     *
     * @throws JsonException If the JSON conversion fails
     *
     * @return Generator<string>
     */
    public function encode(iterable $records): Generator
    {
        $increment = 0;
        yield $this->start;

        foreach ($records as $offset => $record) {
            if (0 !== $increment) {
                yield $this->separator;
            }

            yield ($this->formatter)($increment++, $offset, json_encode($record, $this->flags, $this->depth));
        }

        yield $this->end;
    }

    /**
     * Saves the generated JSON in a file object and returns the number of bytes written.
     *
     * @throws JsonException If the JSON conversion fails
     * @throws RuntimeException If writing to the persistence layer fails
     */
    public function encodeToFile(iterable $records, SplFileObject $file): int
    {
        return $this->encodeTo($records, $file);
    }

    /**
     * Saves the generated JSON in a stream resource or a file object and returns the number of bytes written.
     *
     * @throws JsonException If the JSON conversion fails
     * @throws RuntimeException If writing to the persistence layer fails
     */
    private function encodeTo(iterable $records, Stream|SplFileObject $stream): int
    {
        $bytes = 0;
        $counter = 0;
        try {
            set_error_handler(fn (int $errno, string $errstr, string $errfile, int $errline) => true);
            foreach ($this->encode($records) as $json) {
                if (false === ($res = $stream->fwrite($json))) {
                    throw new RuntimeException('Unable to write to the destination stream `'.$stream->getPathname().'`.');
                }

                ++$counter;
                if (null !== $this->flushThreshold && (0 === $counter % $this->flushThreshold)) {
                    $counter = 0;
                    $stream->fflush();
                }

                $bytes += $res;
            }
        } finally {
            restore_error_handler();
        }

        return $bytes;
    }

    /**
     * Saves the generated JSON in a stream resource and returns the number of bytes written.
     *
     * @param resource $stream
     *
     * @throws JsonException If the JSON conversion fails
     * @throws RuntimeException If writing to the persistence layer fails
     */
    public function encodeToStream(iterable $records, $stream): int
    {
        return $this->encodeTo($records, Stream::createFromResource($stream));
    }

    /**
     * Saves the generated JSON in a file specified by its path and returns the number of bytes written.
     *
     * @param resource|null $context the resource context
     *
     * @throws UnavailableStream If the stream is not available
     * @throws JsonException If the JSON conversion fails
     * @throws RuntimeException If writing to the persistence layer fails
     */
    public function encodeToPath(iterable $records, string $path, string $open_mode = 'w', $context = null): int
    {
        return $this->encodeTo($records, Stream::createFromPath($path, $open_mode, $context));
    }
}
