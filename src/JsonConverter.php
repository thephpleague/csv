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
use Closure;
use Exception;
use InvalidArgumentException;
use Iterator;
use IteratorAggregate;
use JsonException;
use RuntimeException;
use SplFileInfo;
use SplFileObject;
use Traversable;

use const JSON_ERROR_NONE;
use const JSON_FORCE_OBJECT;
use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;

/**
 * Converts and store tabular data into a JSON string.
 * @template T
 */
final class JsonConverter
{
    public readonly int $flags;
    /** @var int<1, max> */
    public readonly int $depth;
    /** @var non-empty-string */
    public readonly string $indentation;
    /** @var Closure(T, array-key): mixed */
    public readonly Closure $formatter;
    public readonly bool $isPrettyPrint;
    public readonly bool $isForceObject;
    /** @var Closure(string, array-key): string */
    private readonly Closure $internalFormatter;

    public static function create(): self
    {
        return new self(
            flags: 0,
            depth: 512,
            indentSize: 4,
            formatter: null,
        );
    }

    /**
     * @param int<1, max> $depth
     * @param int<1, max> $indentSize
     */
    private function __construct(
        int $flags,
        int $depth,
        int $indentSize,
        ?Closure $formatter
    ) {
        json_encode([], $flags & ~JSON_THROW_ON_ERROR, $depth);
        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new InvalidArgumentException('The flags or the depth given are not valid JSON encoding parameters in PHP; '.json_last_error_msg());
        }

        if (1 > $indentSize) {
            throw new InvalidArgumentException('The indentation space must be greater or equal to 1.');
        }

        $this->flags = $flags;
        $this->depth = $depth;
        $this->indentation = str_repeat(' ', $indentSize);
        $this->formatter = $formatter ?? fn (mixed $value) => $value;
        $this->isPrettyPrint = ($this->flags & JSON_PRETTY_PRINT) === JSON_PRETTY_PRINT;
        $this->isForceObject = ($this->flags & JSON_FORCE_OBJECT) === JSON_FORCE_OBJECT;
        $this->internalFormatter = $this->setInternalFormatter();
    }

    /**
     * Adds a list of JSON flags.
     */
    public function addFlags(int ...$flag): self
    {
        $flags = array_reduce($flag, fn (int $flag, int $value): int => $flag | $value, $this->flags);
        if ($flags === $this->flags) {
            return $this;
        }

        return new self($flags, $this->depth, strlen($this->indentation), $this->formatter);
    }

    /**
     * Removes a list of JSON flags.
     */
    public function removeFlags(int ...$flag): self
    {
        $flags = array_reduce($flag, fn (int $flag, int $value): int => $flag & ~$value, $this->flags);
        if ($flags === $this->flags) {
            return $this;
        }

        return new self($flags, $this->depth, strlen($this->indentation), $this->formatter);
    }

    /**
     * Set the depth of Json encoding.
     *
     * @param int<1, max> $depth
     */
    public function depth(int $depth): self
    {
        if ($depth === $this->depth) {
            return $this;
        }

        return new self($this->flags, $depth, strlen($this->indentation), $this->formatter);
    }

    /**
     * Set the indentation size.
     *
     * @param int<1, max> $indentSize
     */
    public function indentSize(int $indentSize): self
    {
        if ($indentSize === strlen($this->indentation)) {
            return $this;
        }

        return new self($this->flags, $this->depth, $indentSize, $this->formatter);
    }

    /**
     * Set a callback to be used on each item before json encode.
     */
    public function formatter(?Closure $formatter): self
    {
        return new self($this->flags, $this->depth, strlen($this->indentation), $formatter);
    }

    /**
     * Store the generated JSON in the destination filepath.
     *
     * if a Path or a SplFileInfo object is given,
     * the file will be emptying before adding the JSON
     * content to it. For all the other types you are
     * required to provide a file with the correct open
     * mode.
     *
     * @param iterable<T> $records
     * @param SplFileInfo|SplFileObject|Stream|resource|string $destination
     * @param resource|null $context
     *
     * @throws UnavailableStream
     * @throws InvalidArgumentException
     * @throws JsonException
     * @throws RuntimeException
     */
    public function save(iterable $records, mixed $destination, $context = null): int
    {
        $bytes = 0;
        $stream = match(true) {
            $destination instanceof Stream,
            $destination instanceof SplFileObject => $destination,
            $destination instanceof SplFileInfo => $destination->openFile(mode:'w', context: $context),
            is_resource($destination) => Stream::createFromResource($destination),
            is_string($destination) => Stream::createFromPath(path: $destination, open_mode:'w', context: $context),
            default => throw new InvalidArgumentException('The path must be a stream or a SplFileInfo object.'),
        };

        set_error_handler(fn (int $errno, string $errstr, string $errfile, int $errline) => true);
        foreach ($this->convert($records) as $line) {
            $addedBytes = $stream->fwrite($line);
            if (false === $addedBytes) {
                restore_error_handler();

                throw new RuntimeException('Unable to write to the stream.');
            }
            $bytes += $addedBytes;
        }

        restore_error_handler();

        return $bytes;
    }

    /**
     * Returns the JSON representation of a tabular data collection.
     *
     * @param iterable<T> $records
     *
     * @throws Exception
     * @throws JsonException
     */
    public function encode(iterable $records): string
    {
        $json = '';
        foreach ($this->convert($records) as $line) {
            $json .= $line;
        }

        return $json;
    }

    /**
     * Returns an Iterator that you can iterate to generate the actual JSON string representation.
     *
     * @param iterable<T> $records
     *
     * @throws JsonException
     * @throws Exception
     *
     * @return Iterator<string>
     */
    public function convert(iterable $records): Iterator
    {
        $start = '[';
        $end = ']';
        if ($this->isForceObject) {
            $start = '{';
            $end = '}';
        }

        if ($records instanceof IteratorAggregate) {
            $records = $records->getIterator();
        }

        $records = match (true) {
            $records instanceof Iterator => $records,
            $records instanceof Traversable => (function () use ($records): Iterator {
                foreach ($records as $offset => $record) {
                    yield $offset => $records;
                }
            })(),
            default => new ArrayIterator($records),
        };
        $records->rewind();
        if (!$records->valid()) {
            yield $start.$end;

            return;
        }

        $separator = ',';
        if ($this->isPrettyPrint) {
            $start .= "\n";
            $end = "\n".$end;
            $separator .= "\n";
        }

        $offset = 0;
        $current = $records->current();
        $records->next();

        yield $start;

        while ($records->valid()) {
            yield $this->format($current, $offset).$separator;

            $offset++;
            $current = $records->current();
            $records->next();
        }

        yield $this->format($current, $offset).$end;
    }

    /**
     * @throws JsonException
     */
    private function format(mixed $value, int|string $offset): string
    {
        return ($this->internalFormatter)(
            json_encode(
                value: ($this->formatter)($value, $offset),
                flags: ($this->flags & ~JSON_PRETTY_PRINT) | JSON_THROW_ON_ERROR,
                depth: $this->depth
            ),
            $offset
        );
    }

    /**
     * @return Closure(string, array-key): string
     */
    private function setInternalFormatter(): Closure
    {
        $callback = fn (string $json, int|string $offset): string => $json;
        if ($this->isForceObject) {
            $callback = fn (string $json, int|string $offset): string => '"'.json_encode($offset).'":'.$json;
        }

        if (!$this->isPrettyPrint) {
            return $callback;
        }

        return fn (string $json, int|string $offset): string => $this->prettyPrint($callback($json, $offset));
    }

    /**
     * Pretty Print the JSON string without using JSON_PRETTY_PRINT
     * The method also allow using an arbitrary length for the indentation.
     */
    private function prettyPrint(string $json): string
    {
        $level = 1;
        $inQuotes = false;
        $escape = false;
        $length = strlen($json);

        $str = $this->indentation;
        for ($i = 0; $i < $length; $i++) {
            $char = $json[$i];
            if ('"' === $char && !$escape) {
                $inQuotes = !$inQuotes;
            }

            $escape = '\\' === $char && !$escape;
            $str .= $inQuotes ? $char : match ($char) {
                '{', '[' => $char."\n".str_repeat($this->indentation, ++$level),
                '}', ']' =>  "\n".str_repeat($this->indentation, --$level).$char,
                ',' => $char."\n".str_repeat($this->indentation, $level),
                ':' => $char.' ',
                default => $char,
            };
        }

        return $str;
    }
}
