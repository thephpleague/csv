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

use BadMethodCallException;
use Closure;
use Exception;
use InvalidArgumentException;
use Iterator;
use JsonException;
use RuntimeException;
use SplFileInfo;
use SplFileObject;

use function array_filter;
use function array_reduce;
use function get_defined_constants;
use function is_resource;
use function is_string;
use function json_encode;
use function json_last_error;
use function lcfirst;
use function preg_match;
use function restore_error_handler;
use function set_error_handler;
use function str_repeat;
use function str_replace;
use function str_starts_with;
use function strlen;
use function strtolower;
use function substr;
use function ucwords;

use const ARRAY_FILTER_USE_KEY;
use const JSON_ERROR_NONE;
use const JSON_FORCE_OBJECT;
use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;

/**
 * Converts and store tabular data into a JSON string.
 * @template T
 *
 * @method JsonConverter withHexTag() adds the JSON_HEX_TAG flag
 * @method JsonConverter withoutHexTag() adds the JSON_HEX_TAG flag
 * @method bool useHexTag() tells whether the JSON_HEX_TAG flag is used
 * @method JsonConverter withHexAmp() adds the JSON_HEX_TAG flag
 * @method JsonConverter withoutHexAmp() adds the JSON_HEX_TAG flag
 * @method bool useHexAmp() tells whether the JSON_HEX_TAG flag is used
 * @method JsonConverter withHexApos() adds the JSON_HEX_TAG flag
 * @method JsonConverter withoutHexApos() adds the JSON_HEX_TAG flag
 * @method bool useHexApos() tells whether the JSON_HEX_TAG flag is used
 * @method JsonConverter withHexQuot() adds the JSON_HEX_TAG flag
 * @method JsonConverter withoutHexQuot() adds the JSON_HEX_TAG flag
 * @method bool useHexQuot() tells whether the JSON_HEX_TAG flag is used
 * @method JsonConverter withForceObject() adds the JSON_HEX_TAG flag
 * @method JsonConverter withoutForceObject() adds the JSON_HEX_TAG flag
 * @method bool useForceObject() tells whether the JSON_HEX_TAG flag is used
 * @method JsonConverter withNumericCheck() adds the JSON_HEX_TAG flag
 * @method JsonConverter withoutNumericCheck() adds the JSON_HEX_TAG flag
 * @method bool useNumericCheck() tells whether the JSON_HEX_TAG flag is used
 * @method JsonConverter withUnescapedSlashes() adds the JSON_HEX_TAG flag
 * @method JsonConverter withoutUnescapedSlashes() adds the JSON_HEX_TAG flag
 * @method bool useUnescapedSlashes() tells whether the JSON_HEX_TAG flag is used
 * @method JsonConverter withPrettyPrint() adds the JSON_HEX_TAG flag
 * @method JsonConverter withoutPrettyPrint() adds the JSON_HEX_TAG flag
 * @method bool usePrettyPrint() tells whether the JSON_HEX_TAG flag is used
 * @method JsonConverter withUnescapedUnicode() adds the JSON_HEX_TAG flag
 * @method JsonConverter withoutUnescapedUnicode() adds the JSON_HEX_TAG flag
 * @method bool useUnescapedUnicode() tells whether the JSON_HEX_TAG flag is used
 * @method JsonConverter withPartialOutputOnError() adds the JSON_HEX_TAG flag
 * @method JsonConverter withoutPartialOutputOnError() adds the JSON_HEX_TAG flag
 * @method bool usePartialOutputOnError() tells whether the JSON_HEX_TAG flag is used
 * @method JsonConverter withPreserveZeroFraction() adds the JSON_HEX_TAG flag
 * @method JsonConverter withoutPreserveZeroFraction() adds the JSON_HEX_TAG flag
 * @method bool usePreserveZeroFraction() tells whether the JSON_HEX_TAG flag is used
 * @method JsonConverter withUnescapedLineTerminators() adds the JSON_HEX_TAG flag
 * @method JsonConverter withoutUnescapedLineTerminators() adds the JSON_HEX_TAG flag
 * @method bool useUnescapedLineTerminators() tells whether the JSON_HEX_TAG flag is used
 * @method JsonConverter withInvalidUtf8Ignore() adds the JSON_HEX_TAG flag
 * @method JsonConverter withoutInvalidUtf8Ignore() adds the JSON_HEX_TAG flag
 * @method bool useInvalidUtf8Ignore() tells whether the JSON_HEX_TAG flag is used
 * @method JsonConverter withInvalidUtf8Substitute() adds the JSON_HEX_TAG flag
 * @method JsonConverter withoutInvalidUtf8Substitute() adds the JSON_HEX_TAG flag
 * @method bool useInvalidUtf8Substitute() tells whether the JSON_HEX_TAG flag is used
 * @method JsonConverter withThrowOnError() adds the JSON_HEX_TAG flag
 * @method JsonConverter withoutThrowOnError() adds the JSON_HEX_TAG flag
 * @method bool useThrowOnError() tells whether the JSON_HEX_TAG flag is used
 */
final class JsonConverter
{
    public readonly int $flags;
    /** @var int<1, max> */
    public readonly int $depth;
    /** @var int<1, max> */
    public readonly int $indentSize;
    /** @var Closure(T, array-key): mixed */
    public readonly Closure $formatter;
    private readonly bool $isPrettyPrint;
    private readonly bool $isForceObject;
    /** @var non-empty-string */
    private readonly string $indentation;
    /** @var Closure(string, array-key): string */
    private readonly Closure $internalFormatter;
    /** @var int<1, max> */
    public readonly int $chunkSize;

    public static function create(): self
    {
        return new self(flags: 0, depth: 512, indentSize: 4, formatter: null, chunkSize: 500);
    }

    /**
     * @param int<1, max> $depth
     * @param int<1, max> $indentSize
     */
    private function __construct(int $flags, int $depth, int $indentSize, ?Closure $formatter, int $chunkSize)
    {
        json_encode([], $flags & ~JSON_THROW_ON_ERROR, $depth);

        JSON_ERROR_NONE === json_last_error() || throw new InvalidArgumentException('The flags or the depth given are not valid JSON encoding parameters in PHP; '.json_last_error_msg());
        1 <= $indentSize || throw new InvalidArgumentException('The indentation space must be greater or equal to 1.');
        1 <= $chunkSize || throw new InvalidArgumentException('The chunk size must be greater or equal to 1.');

        $this->flags = $flags;
        $this->depth = $depth;
        $this->indentSize = $indentSize;
        $this->indentation = str_repeat(' ', $indentSize);
        $this->formatter = $formatter ?? fn (mixed $value) => $value;
        $this->isPrettyPrint = ($this->flags & JSON_PRETTY_PRINT) === JSON_PRETTY_PRINT;
        $this->isForceObject = ($this->flags & JSON_FORCE_OBJECT) === JSON_FORCE_OBJECT;
        $this->internalFormatter = $this->setInternalFormatter();
        $this->chunkSize = $chunkSize;
    }

    /**
     * @return Closure(string, array-key): string
     */
    private function setInternalFormatter(): Closure
    {
        $callback = match ($this->isForceObject) {
            false => fn (string $json, int|string $offset): string => $json,
            default => fn (string $json, int|string $offset): string => '"'.json_encode($offset).'":'.$json,
        };

        return match ($this->isPrettyPrint) {
            false => $callback,
            default => fn (string $json, int|string $offset): string => $this->prettyPrint($callback($json, $offset)),
        };
    }

    /**
     * @throws BadMethodCallException
     */
    public function __call(string $name, array $arguments): mixed
    {
        return match (true) {
            str_starts_with($name, 'without') => $this->removeFlags(self::methodToFlag()[lcfirst(substr($name, 7))] ?? throw new BadMethodCallException('The method "'.self::class.'::'.$name.'" does not exist.')),
            str_starts_with($name, 'with') => $this->addFlags(self::methodToFlag()[lcfirst(substr($name, 4))] ?? throw new BadMethodCallException('The method "'.self::class.'::'.$name.'" does not exist.')),
            str_starts_with($name, 'use') => $this->useFlags(self::methodToFlag()[lcfirst(substr($name, 3))] ?? throw new BadMethodCallException('The method "'.self::class.'::'.$name.'" does not exist.')),
            default => throw new BadMethodCallException('The method "'.self::class.'::'.$name.'" does not exist.'),
        };
    }

    /**
     * Returns the PHP json flag associated to its method suffix to ease method lookup.
     *
     * @return array<string, int>
     */
    private static function methodToFlag(): array
    {
        static $methods;

        if (null === $methods) {
            $methods = [];
            /** @var array<string, int> $jsonFlags */
            $jsonFlags = get_defined_constants(true)['json'];
            $jsonEncodeFlags = array_filter(
                $jsonFlags,
                fn (string $key) => 1 !== preg_match('/^(JSON_BIGINT_AS_STRING|JSON_OBJECT_AS_ARRAY|JSON_ERROR_)(.*)?$/', $key),
                ARRAY_FILTER_USE_KEY
            );

            foreach ($jsonEncodeFlags as $name => $value) {
                $methods[lcfirst(str_replace('_', '', ucwords(strtolower(substr($name, 5)), '_')))] = $value;
            }
        }

        return $methods;
    }

    /**
     * Adds a list of JSON flags.
     */
    public function addFlags(int ...$flags): self
    {
        return $this->setFlags(
            array_reduce($flags, fn (int $carry, int $flag): int => $carry | $flag, $this->flags)
        );
    }

    /**
     * Removes a list of JSON flags.
     */
    public function removeFlags(int ...$flags): self
    {
        return $this->setFlags(
            array_reduce($flags, fn (int $carry, int $flag): int => $carry & ~$flag, $this->flags)
        );
    }

    /**
     * Tells whether the flag is being used by the current JsonConverter.
     */
    public function useFlags(int ...$flags): bool
    {
        foreach ($flags as $flag) {
            // the flag is always used even if it is not set by the user
            if (JSON_THROW_ON_ERROR === $flag) {
                continue;
            }

            if (($this->flags & $flag) !== $flag) {
                return false;
            }
        }

        return [] !== $flags;
    }

    private function setFlags(int $flags): self
    {
        return match ($flags) {
            $this->flags => $this,
            default => new self($flags, $this->depth, $this->indentSize, $this->formatter, $this->chunkSize),
        };
    }

    /**
     * Set the depth of Json encoding.
     *
     * @param int<1, max> $depth
     */
    public function depth(int $depth): self
    {
        return match ($depth) {
            $this->depth => $this,
            default => new self($this->flags, $depth, $this->indentSize, $this->formatter, $this->chunkSize),
        };
    }

    /**
     * Set the indentation size.
     *
     * @param int<1, max> $indentSize
     */
    public function indentSize(int $indentSize): self
    {
        return match ($indentSize) {
            $this->indentSize => $this,
            default => new self($this->flags, $this->depth, $indentSize, $this->formatter, $this->chunkSize),
        };
    }

    /**
     * Set the indentation size.
     *
     * @param int<1, max> $chunkSize
     */
    public function chunkSize(int $chunkSize): self
    {
        return match ($chunkSize) {
            $this->chunkSize => $this,
            default => new self($this->flags, $this->depth, $this->indentSize, $this->formatter, $chunkSize),
        };
    }

    /**
     * Set a callback to format each item before json encode.
     */
    public function formatter(?Closure $formatter): self
    {
        return new self($this->flags, $this->depth, $this->indentSize, $formatter, $this->chunkSize);
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
        $stream = match(true) {
            $destination instanceof Stream,
            $destination instanceof SplFileObject => $destination,
            $destination instanceof SplFileInfo => $destination->openFile(mode:'w', context: $context),
            is_resource($destination) => Stream::createFromResource($destination),
            is_string($destination) => Stream::createFromPath($destination, 'w', $context),
            default => throw new InvalidArgumentException('The destination path must be a filename, a stream or a SplFileInfo object.'),
        };
        $bytes = 0;
        $writtenBytes = 0;
        set_error_handler(fn (int $errno, string $errstr, string $errfile, int $errline) => true);
        foreach ($this->convert($records) as $line) {
            if (false === ($writtenBytes = $stream->fwrite($line))) {
                break;
            }
            $bytes += $writtenBytes;
        }
        restore_error_handler();

        false !== $writtenBytes || throw new RuntimeException('Unable to write '.(isset($line) ? '`'.$line.'`' : '').' to the destination path.');

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
        $stream = Stream::createFromString();
        $this->save($records, $stream);
        $stream->rewind();

        return (string) $stream->getContents();
    }

    /**
     * Sends and makes the JSON structure downloadable via HTTP.
     *.
     * Returns the number of characters read from the handle and passed through to the output.
     *
     * @param iterable<T> $records
     *
     * @throws Exception
     * @throws JsonException
     */
    public function download(iterable $records, string $filename): int
    {
        HttpHeaders::forFileDownload($filename, 'application/json');

        return $this->save($records, new SplFileObject('php://output', 'w'));
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

        $records = MapIterator::toIterator($records);
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

        $incr = 0;
        $buffer = [];
        while ($records->valid()) {
            if ($incr === $this->chunkSize) {
                yield $this->format($buffer, $offset).$separator;

                $incr = 0;
                $buffer = [];
            }
            $incr++;
            $buffer[] = $current;

            $offset++;
            $current = $records->current();
            $records->next();
        }

        $last = $this->format($buffer, $offset);
        if ('' !== $last) {
            yield $last.$separator;
        }

        yield $this->format([$current], $offset++).$end;
    }

    /**
     * @throws JsonException
     */
    private function format(array $value, int $offset): string
    {
        $data = [];
        foreach ($value as $item) {
            $data[] = ($this->formatter)($item, $offset);
            ++$offset;
        }

        $json = json_encode(
            value: $data,
            flags: ($this->flags & ~JSON_PRETTY_PRINT) | JSON_THROW_ON_ERROR,
            depth: $this->depth
        );

        return ($this->internalFormatter)(substr($json, 1, -1), $offset);
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
