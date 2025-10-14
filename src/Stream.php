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

use Deprecated;
use RuntimeException;
use SeekableIterator;
use SplFileObject;
use Stringable;
use TypeError;
use ValueError;

use function array_keys;
use function fclose;
use function feof;
use function fflush;
use function fgetcsv;
use function fopen;
use function fpassthru;
use function fputcsv;
use function fread;
use function fseek;
use function fwrite;
use function get_resource_type;
use function gettype;
use function is_array;
use function is_resource;
use function is_string;
use function rewind;
use function stream_filter_remove;
use function stream_get_meta_data;
use function strlen;

use const SEEK_SET;

/**
 * An object-oriented API to handle a PHP stream resource.
 *
 * @internal used internally to iterate over a stream resource
 */
final class Stream implements SeekableIterator
{
    /** @var mixed can be a null, false or a scalar type value. Current iterator value. */
    private mixed $value = null;
    /** Current iterator key. */
    private int $offset = -1;
    /** Flags for the Document. */
    private int $flags = 0;
    private string $delimiter = ',';
    private string $enclosure = '"';
    private string $escape = '\\';
    /** @var array<string, array<resource>> Attached filters. */
    private array $filters = [];
    private int $maxLength = 0;

    /**
     * @param resource $stream stream type resource
     */
    private function __construct(
        private $stream,
        private readonly bool $is_seekable,
        private readonly bool $should_close_stream = false,
    ) {
    }

    public function __destruct()
    {
        Warning::cloak(
            array_walk_recursive(...),
            $this->filters,
            static function ($filter): void {
                if (is_resource($filter)) {
                    stream_filter_remove($filter);
                }
            }
        );

        if ($this->should_close_stream && is_resource($this->stream)) {
            fclose($this->stream);
        }

        unset($this->stream);
    }

    public function __clone(): void
    {
        throw UnavailableStream::dueToForbiddenCloning(self::class);
    }

    public function __debugInfo(): array
    {
        return stream_get_meta_data($this->stream) + [
            'delimiter' => $this->delimiter,
            'enclosure' => $this->enclosure,
            'escape' => $this->escape,
            'stream_filters' => array_keys($this->filters),
        ];
    }

    /**
     * Returns the actual mode used to open the resource stream.
     */
    public function getMode(): string
    {
        return stream_get_meta_data($this->stream)['mode'];
    }

    public function ftell(): int|false
    {
        return ftell($this->stream);
    }

    /**
     * Returns a new instance from a file path.
     *
     * @param resource|string $filename
     * @param resource|null $context
     *
     * @throws UnavailableStream if the stream resource cannot be created
     */
    public static function from($filename, string $mode = 'r', $context = null): self
    {
        $should_close_stream = false;
        if (is_string($filename)) {
            $should_close_stream = true;
            /** @var resource|false $resource */
            $resource = @fopen(filename: $filename, mode: $mode, context: $context);
            is_resource($resource) || throw UnavailableStream::dueToPathNotFound($filename);

            $filename = $resource;
        }

        is_resource($filename) || throw new TypeError('Argument passed must be a stream resource or a string, '.gettype($filename).' given.');
        'stream' === ($type = get_resource_type($filename)) || throw new TypeError('Argument passed must be a stream resource, '.$type.' resource given');

        return new self($filename, stream_get_meta_data($filename)['seekable'], $should_close_stream);
    }

    /**
     * Returns a new instance from a string.
     */
    public static function fromString(Stringable|string $content = ''): self
    {
        $instance = self::from('php://temp', 'r+');
        $instance->fwrite((string) $content);

        return $instance;
    }

    /**
     * Returns the URI of the underlying stream.
     *
     * @see https://www.php.net/manual/en/splfileinfo.getpathname.php
     */
    public function getPathname(): string
    {
        return stream_get_meta_data($this->stream)['uri'];
    }

    /**
     * Appends a filter.
     *
     * @see http://php.net/manual/en/function.stream-filter-append.php
     *
     * @throws InvalidArgument if the filter can not be appended
     */
    public function appendFilter(string $filtername, int $read_write, mixed $params = null): void
    {
        /** @var resource|false $res */
        $res = Warning::cloak(stream_filter_append(...), $this->stream, $filtername, $read_write, $params);
        is_resource($res) || throw InvalidArgument::dueToStreamFilterNotFound($filtername);

        $this->filters[$filtername][] = $res;
    }

    /**
     * Appends a filter.
     *
     * @see http://php.net/manual/en/function.stream-filter-append.php
     *
     * @throws InvalidArgument if the filter can not be appended
     */
    public function prependFilter(string $filtername, int $read_write, mixed $params = null): void
    {
        /** @var resource|false $res */
        $res = Warning::cloak(stream_filter_prepend(...), $this->stream, $filtername, $read_write, $params);
        is_resource($res) || throw InvalidArgument::dueToStreamFilterNotFound($filtername);

        $this->filters[$filtername][] = $res;
    }

    /**
     * Sets CSV control.
     *
     * @see https://www.php.net/manual/en/splfileobject.setcsvcontrol.php
     *
     * @throws InvalidArgument
     */
    public function setCsvControl(string $delimiter = ',', string $enclosure = '"', string $escape = '\\'): void
    {
        [$this->delimiter, $this->enclosure, $this->escape] = $this->filterControl($delimiter, $enclosure, $escape, __METHOD__);
    }

    /**
     * Filters CSV control characters.
     *
     * @throws InvalidArgument If the CSV control character is not exactly one character.
     *
     * @return array{0:string, 1:string, 2:string}
     */
    private function filterControl(string $delimiter, string $enclosure, string $escape, string $caller): array
    {
        return match (true) {
            1 !== strlen($delimiter) => throw InvalidArgument::dueToInvalidDelimiterCharacter($delimiter, $caller),
            1 !== strlen($enclosure) => throw InvalidArgument::dueToInvalidEnclosureCharacter($enclosure, $caller),
            1 !== strlen($escape) && '' !== $escape => throw InvalidArgument::dueToInvalidEscapeCharacter($escape, $caller),
            default => [$delimiter, $enclosure, $escape],
        };
    }

    /**
     * Returns CSV control.
     *
     * @see https://www.php.net/manual/en/splfileobject.getcsvcontrol.php
     *
     * @return array<string>
     */
    public function getCsvControl(): array
    {
        return [$this->delimiter, $this->enclosure, $this->escape];
    }

    /**
     * Sets CSV stream flags.
     *
     * @see https://www.php.net/manual/en/splfileobject.setflags.php
     */
    public function setFlags(int $flags): void
    {
        $this->flags = $flags;
    }

    /**
     * Writes a field array as a CSV line.
     *
     * @see https://www.php.net/manual/en/splfileobject.fputcsv.php
     *
     * @throws InvalidArgument If the CSV control character is not exactly one character.
     */
    public function fputcsv(array $fields, string $delimiter = ',', string $enclosure = '"', string $escape = '\\', string $eol = "\n"): int|false
    {
        return fputcsv(
            $this->stream,
            $fields,
            ...[...$this->filterControl($delimiter, $enclosure, $escape, __METHOD__), $eol]
        );
    }

    /**
     * Gets line number.
     *
     * @see https://www.php.net/manual/en/splfileobject.key.php
     */
    public function key(): int
    {
        return $this->offset;
    }

    /**
     * Reads next line.
     *
     * @see https://www.php.net/manual/en/splfileobject.next.php
     */
    public function next(): void
    {
        $this->value = false;
        $this->offset++;
    }

    /**
     * Rewinds the file to the first line.
     *
     * @see https://www.php.net/manual/en/splfileobject.rewind.php
     *
     * @throws Exception if the stream resource is not seekable
     * @throws RuntimeException if rewinding the stream fails.
     */
    public function rewind(): void
    {
        $this->is_seekable || throw UnavailableFeature::dueToMissingStreamSeekability();
        false !== rewind($this->stream) || throw new RuntimeException('Unable to rewind the document.');

        $this->offset = 0;
        $this->value = false;
        if (SplFileObject::READ_AHEAD === ($this->flags & SplFileObject::READ_AHEAD)) {
            $this->current();
        }
    }

    /**
     * Not at EOF.
     *
     * @see https://www.php.net/manual/en/splfileobject.valid.php
     */
    public function valid(): bool
    {
        return match (true) {
            SplFileObject::READ_AHEAD === ($this->flags & SplFileObject::READ_AHEAD) => false !== $this->current(),
            default => !feof($this->stream),
        };
    }

    /**
     * Retrieves the current line of the file.
     *
     * @see https://www.php.net/manual/en/splfileobject.current.php
     */
    public function current(): mixed
    {
        if (false !== $this->value) {
            return $this->value;
        }

        $this->value = match (true) {
            SplFileObject::READ_CSV === ($this->flags & SplFileObject::READ_CSV) => $this->getCurrentRecord(),
            default => $this->getCurrentLine(),
        };

        return $this->value;
    }

    public function fgets(): string|false
    {
        $arg = [$this->stream];
        if (0 < $this->maxLength) {
            $arg[] = $this->maxLength;
        }
        return fgets(...$arg);
    }

    /**
     * Sets the maximum length of a line to be read.
     *
     * @see https://www.php.net/manual/en/splfileobject.setmaxlinelen.php
     */
    public function setMaxLineLen(int $maxLength): void
    {
        0 <= $maxLength || throw new ValueError(' Argument #1 ($maxLength) must be greater than or equal to 0');

        $this->maxLength = $maxLength;
    }

    /**
     * Gets the maximum line length as set by setMaxLineLen.
     *
     * @see https://www.php.net/manual/en/splfileobject.getmaxlinelen.php
     */
    public function getMaxLineLen(): int
    {
        return $this->maxLength;
    }

    /**
     * Tells whether the end of file has been reached.
     *
     * @see https://www.php.net/manual/en/splfileobject.eof.php
     */
    public function eof(): bool
    {
        return feof($this->stream);
    }

    /**
     * Retrieves the current line as a CSV Record.
     */
    private function getCurrentRecord(): array|false
    {
        $isEmptyLine = SplFileObject::SKIP_EMPTY === ($this->flags & SplFileObject::SKIP_EMPTY);
        do {
            $ret = fgetcsv($this->stream, 0, $this->delimiter, $this->enclosure, $this->escape);
        } while ($isEmptyLine && is_array($ret) && null === $ret[0]);

        return $ret;
    }

    /**
     * Retrieves the current line.
     */
    private function getCurrentLine(): string|false
    {
        $isEmptyLine = SplFileObject::SKIP_EMPTY === ($this->flags & SplFileObject::SKIP_EMPTY);
        $dropNewLine = SplFileObject::DROP_NEW_LINE === ($this->flags & SplFileObject::DROP_NEW_LINE);
        $shouldBeIgnored = fn (string|false $line): bool => ($isEmptyLine || $dropNewLine)
            && (false !== $line && '' === rtrim($line, "\r\n"));
        $arguments = [$this->stream];
        if (0 < $this->maxLength) {
            $arguments[] = $this->maxLength;
        }

        do {
            $line = fgets(...$arguments);
        } while ($shouldBeIgnored($line));

        if ($dropNewLine && false !== $line) {
            return rtrim($line, "\r\n");
        }

        return $line;
    }

    /**
     * Seeks to specified line.
     *
     * @see https://www.php.net/manual/en/splfileobject.seek.php
     *
     * @throws Exception if the position is negative
     */
    public function seek(int $offset): void
    {
        $offset >= 0 || throw InvalidArgument::dueToInvalidSeekingPosition($offset, __METHOD__);

        $this->rewind();
        while ($this->key() !== $offset && $this->valid()) {
            $this->current();
            $this->next();
        }

        if (0 !== $offset) {
            $this->offset--;
        }

        $this->current();
    }

    /**
     * Outputs all remaining data on a file pointer.
     *
     * @see https://www.php.net/manual/en/splfileobject.fpassthru.php
     */
    public function fpassthru(): int|false
    {
        return fpassthru($this->stream);
    }

    /**
     * Reads from file.
     *
     * @see https://www.php.net/manual/en/splfileobject.fread.php
     *
     * @param int<1, max> $length The number of bytes to read
     */
    public function fread(int $length): string|false
    {
        return fread($this->stream, $length);
    }

    /**
     * Seeks to a position.
     *
     * @see https://www.php.net/manual/en/splfileobject.fseek.php
     *
     * @throws Exception if the stream resource is not seekable
     */
    public function fseek(int $offset, int $whence = SEEK_SET): int
    {
        return match (true) {
            !$this->is_seekable => throw UnavailableFeature::dueToMissingStreamSeekability(),
            default => fseek($this->stream, $offset, $whence),
        };
    }

    /**
     * Write to stream.
     *
     * @see http://php.net/manual/en/SplFileObject.fwrite.php
     */
    public function fwrite(string $str, ?int $length = null): int|false
    {
        $args = [$this->stream, $str];
        if (null !== $length) {
            $args[] = $length;
        }

        return fwrite(...$args);
    }

    /**
     * Flushes the output to a file.
     *
     * @see https://www.php.net/manual/en/splfileobject.fflush.php
     */
    public function fflush(): bool
    {
        return fflush($this->stream);
    }

    /**
     * Gets file size.
     *
     * @see https://www.php.net/manual/en/splfileinfo.getsize.php
     */
    public function getSize(): int|false
    {
        return fstat($this->stream)['size'] ?? false;
    }

    public function getContents(?int $length = null, int $offset = -1): string|false
    {
        return stream_get_contents($this->stream, $length, $offset);
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     * @deprecated since version 9.27.0
     * @codeCoverageIgnore
     *
     * @param resource $stream
     *
     * @throws UnavailableStream if the stream resource is invalid
     *
     * Returns a new instance from a stream resource
     */
    #[Deprecated(message:'use League\Csv\Stream::from() instead', since:'league/csv:9.27.0')]
    public static function createFromResource(mixed $stream): self
    {
        is_resource($stream) || throw new TypeError('Argument passed must be a stream resource or a string, '.gettype($stream).' given.');

        return self::from($stream);
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     * @deprecated since version 9.27.0
     * @codeCoverageIgnore
     *
     * @param resource|null $context
     *
     * @throws UnavailableStream if the stream resource cannot be created
     *
     * Returns a new instance from a file path.
     */
    #[Deprecated(message:'use League\Csv\Stream::from() instead', since:'league/csv:9.27.0')]
    public static function createFromPath(string $path, string $open_mode = 'r', $context = null): self
    {
        return self::from($path, $open_mode, $context);
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     * @deprecated since version 9.27.0
     * @codeCoverageIgnore
     *
     * Returns a new instance from a string.
     */
    #[Deprecated(message:'use League\Csv\Stream::fromString() instead', since:'league/csv:9.27.0')]
    public static function createFromString(Stringable|string $content = ''): self
    {
        return self::fromString($content);
    }
}
