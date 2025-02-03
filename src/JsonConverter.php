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
use Deprecated;
use Exception;
use InvalidArgumentException;
use Iterator;
use JsonException;
use RuntimeException;
use SplFileInfo;
use SplFileObject;
use TypeError;

use function array_filter;
use function array_reduce;
use function get_defined_constants;
use function is_bool;
use function is_resource;
use function is_string;
use function json_encode;
use function json_last_error;
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
use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;

/**
 * Converts and store tabular data into a JSON string.
 * @template T
 *
 * @method JsonConverter withHexTag() adds the JSON_HEX_TAG flag
 * @method JsonConverter withoutHexTag() removes the JSON_HEX_TAG flag
 * @method bool useHexTag() tells whether the JSON_HEX_TAG flag is used
 * @method JsonConverter withHexAmp() adds the JSON_HEX_TAG flag
 * @method JsonConverter withoutHexAmp() removes the JSON_HEX_TAG flag
 * @method bool useHexAmp() tells whether the JSON_HEX_TAG flag is used
 * @method JsonConverter withHexApos() adds the JSON_HEX_TAG flag
 * @method JsonConverter withoutHexApos() removes the JSON_HEX_TAG flag
 * @method bool useHexApos() tells whether the JSON_HEX_TAG flag is used
 * @method JsonConverter withHexQuot() adds the JSON_HEX_TAG flag
 * @method JsonConverter withoutHexQuot() removes the JSON_HEX_TAG flag
 * @method bool useHexQuot() tells whether the JSON_HEX_TAG flag is used
 * @method JsonConverter withForceObject() adds the JSON_HEX_TAG flag
 * @method JsonConverter withoutForceObject() removes the JSON_HEX_TAG flag
 * @method bool useForceObject() tells whether the JSON_HEX_TAG flag is used
 * @method JsonConverter withNumericCheck() adds the JSON_HEX_TAG flag
 * @method JsonConverter withoutNumericCheck() removes the JSON_HEX_TAG flag
 * @method bool useNumericCheck() tells whether the JSON_HEX_TAG flag is used
 * @method JsonConverter withUnescapedSlashes() adds the JSON_HEX_TAG flag
 * @method JsonConverter withoutUnescapedSlashes() removes the JSON_HEX_TAG flag
 * @method bool useUnescapedSlashes() tells whether the JSON_HEX_TAG flag is used
 * @method JsonConverter withoutPrettyPrint() removes the JSON_PRETTY_PRINT flag
 * @method bool usePrettyPrint() tells whether the JSON_HEX_TAG flag is used
 * @method JsonConverter withUnescapedUnicode() adds the JSON_HEX_TAG flag
 * @method JsonConverter withoutUnescapedUnicode() removes the JSON_HEX_TAG flag
 * @method bool useUnescapedUnicode() tells whether the JSON_HEX_TAG flag is used
 * @method JsonConverter withPartialOutputOnError() adds the JSON_HEX_TAG flag
 * @method JsonConverter withoutPartialOutputOnError() removes the JSON_HEX_TAG flag
 * @method bool usePartialOutputOnError() tells whether the JSON_HEX_TAG flag is used
 * @method JsonConverter withPreserveZeroFraction() adds the JSON_HEX_TAG flag
 * @method JsonConverter withoutPreserveZeroFraction() removes the JSON_HEX_TAG flag
 * @method bool usePreserveZeroFraction() tells whether the JSON_HEX_TAG flag is used
 * @method JsonConverter withUnescapedLineTerminators() adds the JSON_HEX_TAG flag
 * @method JsonConverter withoutUnescapedLineTerminators() removes the JSON_HEX_TAG flag
 * @method bool useUnescapedLineTerminators() tells whether the JSON_HEX_TAG flag is used
 * @method JsonConverter withInvalidUtf8Ignore() adds the JSON_HEX_TAG flag
 * @method JsonConverter withoutInvalidUtf8Ignore() removes the JSON_HEX_TAG flag
 * @method bool useInvalidUtf8Ignore() tells whether the JSON_HEX_TAG flag is used
 * @method JsonConverter withInvalidUtf8Substitute() adds the JSON_HEX_TAG flag
 * @method JsonConverter withoutInvalidUtf8Substitute() removes the JSON_HEX_TAG flag
 * @method bool useInvalidUtf8Substitute() tells whether the JSON_HEX_TAG flag is used
 * @method JsonConverter withThrowOnError() adds the JSON_HEX_TAG flag
 * @method JsonConverter withoutThrowOnError() removes the JSON_HEX_TAG flag
 * @method bool useThrowOnError() tells whether the JSON_HEX_TAG flag is used
 */
final class JsonConverter
{
    public readonly int $flags;
    /** @var int<1, max> */
    public readonly int $depth;
    /** @var int<1, max> */
    public readonly int $indentSize;
    /** @var ?Closure(T, array-key): mixed */
    public readonly ?Closure $formatter;
    /** @var int<1, max> */
    public readonly int $chunkSize;
    /** @var non-empty-string */
    private readonly string $start;
    /** @var non-empty-string */
    private readonly string $end;
    /** @var non-empty-string */
    private readonly string $separator;
    /** @var non-empty-string */
    private readonly string $emptyIterable;
    /** @var non-empty-string */
    private readonly string $indentation;
    /** @var Closure(array<int, T>): string */
    private readonly Closure $jsonEncodeChunk;
    /** @var array<string> */
    private array $indentationLevels = [];

    /**
     * @param int<1, max> $depth
     * @param int<1, max> $indentSize
     * @param ?callable(T, array-key): mixed $formatter
     * @param int<1, max> $chunkSize
     *
     * @throws InvalidArgumentException
     */
    public function __construct(
        int $flags = 0,
        int $depth = 512,
        int $indentSize = 4,
        ?callable $formatter = null,
        int $chunkSize = 500
    ) {
        json_encode([], $flags & ~JSON_THROW_ON_ERROR, $depth);

        JSON_ERROR_NONE === ($errorCode = json_last_error()) || throw new InvalidArgumentException('The flags or the depth given are not valid JSON encoding parameters in PHP; '.json_last_error_msg(), $errorCode);
        1 <= $indentSize || throw new InvalidArgumentException('The indentation space must be greater or equal to 1.');
        1 <= $chunkSize || throw new InvalidArgumentException('The chunk size must be greater or equal to 1.');

        $this->flags = $flags;
        $this->depth = $depth;
        $this->indentSize = $indentSize;
        $this->formatter = ($formatter instanceof Closure || null === $formatter) ? $formatter : $formatter(...);
        $this->chunkSize = $chunkSize;

        // Initialize settings and closure to use for conversion.
        // To speed up the process we pre-calculate them
        $this->indentation = str_repeat(' ', $this->indentSize);
        $start = '[';
        $end = ']';
        $separator = ',';
        $chunkFormatter = array_values(...);
        $prettyPrintFormatter = fn (string $json): string => $json;
        if ($this->useForceObject()) {
            $start = '{';
            $end = '}';
            $chunkFormatter = fn (array $value): array => $value;
        }

        $this->emptyIterable = $start.$end;
        if ($this->usePrettyPrint()) {
            $start .= "\n";
            $end = "\n".$end;
            $separator .= "\n";
            $prettyPrintFormatter = $this->prettyPrint(...);
        }

        $flags = ($this->flags & ~JSON_PRETTY_PRINT) | JSON_THROW_ON_ERROR;
        $this->start = $start;
        $this->end = $end;
        $this->separator = $separator;
        $this->jsonEncodeChunk = fn (array $chunk): string => ($prettyPrintFormatter)(substr(
            string: json_encode(($chunkFormatter)($chunk), $flags, $this->depth), /* @phpstan-ignore-line */
            offset: 1,
            length: -1
        ));
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
        $str = [$this->indentation];
        for ($i = 0; $i < $length; $i++) {
            $char = $json[$i];
            if ('"' === $char && !$escape) {
                $inQuotes = !$inQuotes;
            }

            $escape = '\\' === $char && !$escape;
            $str[] = $inQuotes ? $char : match ($char) {
                '{', '[' => $char.($this->indentationLevels[++$level] ??= "\n".str_repeat($this->indentation, $level)),
                '}', ']' =>  ($this->indentationLevels[--$level] ??= "\n".str_repeat($this->indentation, $level)).$char,
                ',' => $char.($this->indentationLevels[$level] ??= "\n".str_repeat($this->indentation, $level)),
                ':' => $char.' ',
                default => $char,
            };
        }

        return implode('', $str);
    }

    /**
     * @throws BadMethodCallException
     */
    public function __call(string $name, array $arguments): self|bool
    {
        return match (true) {
            str_starts_with($name, 'without') => $this->removeFlags(self::methodToFlag($name, 7)),
            str_starts_with($name, 'with') => $this->addFlags(self::methodToFlag($name, 4)),
            str_starts_with($name, 'use') => $this->useFlags(self::methodToFlag($name, 3)),
            default => throw new BadMethodCallException('The method "'.self::class.'::'.$name.'" does not exist.'),
        };
    }

    /**
     * @param int<1, max>|null $indentSize
     */
    public function withPrettyPrint(?int $indentSize = null): self
    {
        $flags = $this->flags | JSON_PRETTY_PRINT;
        $indentSize = $indentSize ?? $this->indentSize;

        return match (true) {
            $flags === $this->flags && $indentSize === $this->indentSize => $this,
            default => new self($flags, $this->depth, $indentSize, $this->formatter, $this->chunkSize),
        };
    }

    /**
     * Returns the PHP json flag associated to its method suffix to ease method lookup.
     */
    private static function methodToFlag(string $method, int $prefixSize): int
    {
        static $suffix2Flag;

        if (null === $suffix2Flag) {
            $suffix2Flag = [];
            /** @var array<string, int> $jsonFlags */
            $jsonFlags = get_defined_constants(true)['json'];
            $jsonEncodeFlags = array_filter(
                $jsonFlags,
                fn (string $key) => 1 !== preg_match('/^(JSON_BIGINT_AS_STRING|JSON_OBJECT_AS_ARRAY|JSON_ERROR_)(.*)?$/', $key),
                ARRAY_FILTER_USE_KEY
            );

            foreach ($jsonEncodeFlags as $name => $value) {
                $suffix2Flag[str_replace('_', '', ucwords(strtolower(substr($name, 5)), '_'))] = $value;
            }
        }

        return $suffix2Flag[substr($method, $prefixSize)]
            ?? throw new BadMethodCallException('The method "'.self::class.'::'.$method.'" does not exist.');
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
            // the JSON_THROW_ON_ERROR flag is always used even if it is not set by the user
            if (JSON_THROW_ON_ERROR !== $flag && ($this->flags & $flag) !== $flag) {
                return false;
            }
        }

        return [] !== $flags;
    }

    /**
     * Sets the encoding flags.
     */
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
    public function formatter(?callable $formatter): self
    {
        return new self($this->flags, $this->depth, $this->indentSize, $formatter, $this->chunkSize);
    }

    /**
     * Apply the callback if the given "condition" is (or resolves to) true.
     *
     * @param (callable($this): bool)|bool $condition
     * @param callable($this): (self|null) $onSuccess
     * @param ?callable($this): (self|null) $onFail
     */
    public function when(callable|bool $condition, callable $onSuccess, ?callable $onFail = null): self
    {
        if (!is_bool($condition)) {
            $condition = $condition($this);
        }

        return match (true) {
            $condition => $onSuccess($this),
            null !== $onFail => $onFail($this),
            default => $this,
        } ?? $this;
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
    public function download(iterable $records, ?string $filename = null): int
    {
        if (null !== $filename) {
            HttpHeaders::forFileDownload($filename, 'application/json; charset=utf-8');
        }

        return $this->save($records, new SplFileObject('php://output', 'wb'));
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
     * @throws JsonException
     * @throws RuntimeException
     * @throws TypeError
     * @throws UnavailableStream
     */
    public function save(iterable $records, mixed $destination, $context = null): int
    {
        $stream = match (true) {
            $destination instanceof Stream,
            $destination instanceof SplFileObject => $destination,
            $destination instanceof SplFileInfo => $destination->openFile(mode:'wb', context: $context),
            is_resource($destination) => Stream::createFromResource($destination),
            is_string($destination) => Stream::createFromPath($destination, 'wb', $context),
            default => throw new TypeError('The destination path must be a filename, a stream or a SplFileInfo object.'),
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

        false !== $writtenBytes || throw new RuntimeException('Unable to write '.(isset($line) ? '`'.$line.'`' : '').' to the destination path `'.$stream->getPathname().'`.');

        return $bytes;
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
        $iterator = match ($this->formatter) {
            null => MapIterator::toIterator($records),
            default => MapIterator::fromIterable($records, $this->formatter)
        };

        $iterator->rewind();
        if (!$iterator->valid()) {
            yield $this->emptyIterable;

            return;
        }

        $chunk = [];
        $chunkOffset = 0;
        $offset = 0;
        $current = $iterator->current();
        $iterator->next();

        yield $this->start;

        while ($iterator->valid()) {
            if ($chunkOffset === $this->chunkSize) {
                yield ($this->jsonEncodeChunk)($chunk).$this->separator;

                $chunkOffset = 0;
                $chunk = [];
            }

            $chunk[$offset] = $current;
            ++$chunkOffset;
            ++$offset;
            $current = $iterator->current();
            $iterator->next();
        }

        if ([] !== $chunk) {
            yield ($this->jsonEncodeChunk)($chunk).$this->separator;
        }

        yield ($this->jsonEncodeChunk)([$offset => $current]).$this->end;
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @see JsonConverter::withPrettyPrint()
     * @deprecated Since version 9.19.0
     * @codeCoverageIgnore
     *
     * Set the indentation size.
     *
     * @param int<1, max> $indentSize
     */
    #[Deprecated(message:'use League\Csv\JsonConverter::withPrettyPrint() instead', since:'league/csv:9.19.0')]
    public function indentSize(int $indentSize): self
    {
        return match ($indentSize) {
            $this->indentSize => $this,
            default => new self($this->flags, $this->depth, $indentSize, $this->formatter, $this->chunkSize),
        };
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @see JsonConverter::__construct()
     * @deprecated Since version 9.22.0
     * @codeCoverageIgnore
     */
    #[Deprecated(message:'use League\Csv\JsonConverter::__construct() instead', since:'league/csv:9.22.0')]
    public static function create(): self
    {
        return new self(
            flags: 0,
            depth: 512,
            indentSize: 4,
            formatter: null,
            chunkSize: 500
        );
    }
}
