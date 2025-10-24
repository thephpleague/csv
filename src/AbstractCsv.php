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
use Deprecated;
use Generator;
use InvalidArgumentException;
use RuntimeException;
use SplFileInfo;
use SplFileObject;
use Stringable;
use Throwable;
use TypeError;

use function filter_var;
use function get_class;
use function gettype;
use function is_resource;
use function rawurlencode;
use function sprintf;
use function str_replace;
use function str_split;
use function strcspn;
use function strlen;

use const FILTER_FLAG_STRIP_HIGH;
use const FILTER_FLAG_STRIP_LOW;
use const FILTER_UNSAFE_RAW;
use const STREAM_FILTER_READ;
use const STREAM_FILTER_WRITE;

/**
 * An abstract class to enable CSV document loading.
 */
abstract class AbstractCsv implements ByteSequence
{
    protected const STREAM_FILTER_MODE = STREAM_FILTER_READ;

    /** @var array<string, bool> collection of stream filters. */
    protected array $stream_filters = [];
    protected ?Bom $input_bom = null;
    protected ?Bom $output_bom = null;
    protected string $delimiter = ',';
    protected string $enclosure = '"';
    protected string $escape = '\\';
    protected bool $is_input_bom_included = false;
    /** @var array<Closure(array): array> collection of Closure to format the record before reading. */
    protected array $formatters = [];

    /**
     * @final This method should not be overwritten in child classes
     */
    protected function __construct(protected readonly SplFileObject|Stream $document)
    {
        [$this->delimiter, $this->enclosure, $this->escape] = $this->document->getCsvControl();
        $this->resetProperties();
    }

    /**
     * Reset dynamic object properties to improve performance.
     */
    protected function resetProperties(): void
    {
    }

    /**
     * @throws UnavailableStream
     */
    public function __clone()
    {
        throw UnavailableStream::dueToForbiddenCloning(static::class);
    }

    /**
     * Returns a new instance from a string.
     */
    public static function fromString(Stringable|string $content = ''): static
    {
        return new static(Stream::fromString($content));
    }

    /**
     * Returns a new instance from a file path.
     *
     * @param SplFileInfo|SplFileObject|resource|string $filename an SPL file object, a resource stream or a file path
     * @param non-empty-string $mode the file path open mode used with a file path or a SplFileInfo object
     * @param resource|null $context the resource context used with a file pathor a SplFileInfo object
     *
     * @throws UnavailableStream
     */
    public static function from($filename, string $mode = 'r+', $context = null): static
    {
        return match (true) {
            $filename instanceof SplFileObject => new static($filename),
            $filename instanceof SplFileInfo => new static($filename->openFile(mode: $mode, context: $context)),
            default => new static(Stream::from($filename, $mode, $context)),
        };
    }

    /**
     * Returns the current field delimiter.
     */
    public function getDelimiter(): string
    {
        return $this->delimiter;
    }

    /**
     * Returns the current field enclosure.
     */
    public function getEnclosure(): string
    {
        return $this->enclosure;
    }

    /**
     * Returns the pathname of the underlying document.
     */
    public function getPathname(): string
    {
        return $this->document->getPathname();
    }

    /**
     * Returns the current field escape character.
     */
    public function getEscape(): string
    {
        return $this->escape;
    }

    /**
     * Returns the BOM sequence in use on Output methods.
     */
    public function getOutputBOM(): string
    {
        return $this->output_bom?->value ?? '';
    }

    /**
     * Returns the BOM sequence of the given CSV.
     */
    public function getInputBOM(): string
    {
        if (null === $this->input_bom) {
            $this->document->setFlags(SplFileObject::READ_CSV);
            $this->input_bom = Bom::tryFromSequence($this->document);
        }

        return $this->input_bom?->value ?? '';
    }

    /**
     * Tells whether the stream filter read capabilities can be used.
     */
    public function supportsStreamFilterOnRead(): bool
    {
        if (!$this->document instanceof Stream) {
            return false;
        }

        $mode = $this->document->getMode();

        return strcspn($mode, 'r+') !== strlen($mode);
    }

    /**
     * Tells whether the stream filter write capabilities can be used.
     */
    public function supportsStreamFilterOnWrite(): bool
    {
        if (!$this->document instanceof Stream) {
            return false;
        }

        $mode = $this->document->getMode();

        return strcspn($mode, 'wae+') !== strlen($mode);
    }

    /**
     * Tells whether the specified stream filter is attached to the current stream.
     */
    public function hasStreamFilter(string $filtername): bool
    {
        return $this->stream_filters[$filtername] ?? false;
    }

    /**
     * Tells whether the BOM can be stripped if presents.
     */
    public function isInputBOMIncluded(): bool
    {
        return $this->is_input_bom_included;
    }

    /**
     * Returns the CSV document as a Generator of string chunk.
     *
     * @throws Exception if the number of bytes is less than 1
     */
    public function chunk(int $length): Generator
    {
        0 < $length || throw InvalidArgument::dueToInvalidChunkSize($length, __METHOD__);

        $this->getInputBOM();
        $this->document->rewind();
        $this->document->setFlags(0);
        $this->is_input_bom_included || -1 < $this->document->fseek($this->input_bom?->length() ?? 0) || throw new RuntimeException('Unable to seek the document.');

        yield from str_split($this->output_bom?->value.$this->document->fread($length), $length);

        while (!$this->document->eof()) {
            $chunk = $this->document->fread($length);
            false !== $chunk || throw new RuntimeException('Unable to read the document.');

            yield $chunk;
        }
    }

    /**
     * Retrieves the CSV content.
     *
     * @throws Exception If the string representation cannot be returned
     */
    public function toString(): string
    {
        $raw = '';
        foreach ($this->chunk(8192) as $chunk) {
            $raw .= $chunk;
        }

        return $raw;
    }

    /**
     * Outputs all data on the CSV file.
     *
     * Returns the number of characters read from the handle and passed through to the output.
     *
     * @throws InvalidArgumentException|Exception
     */
    public function download(?string $filename = null): int
    {
        if (null !== $filename) {
            HttpHeaders::forFileDownload($filename, 'text/csv');
        }

        $bytes = 0;
        $output = new SplFileObject('php://output', 'wb');
        if (null !== $this->output_bom) {
            $bytes += $output->fwrite($this->output_bom->value);
        }

        $this->getInputBOM();
        $this->document->rewind();
        $this->document->setFlags(0);
        $this->is_input_bom_included || -1 < $this->document->fseek($this->input_bom?->length() ?? 0) || throw new RuntimeException('Unable to seek the document.');

        while (!$this->document->eof()) {
            $chunk = $this->document->fread(8192);
            false !== $chunk || throw new RuntimeException('Unable to read the document.');
            $bytes += $output->fwrite($chunk);
            $output->fflush();
        }

        return $bytes;
    }

    /**
     * Sets the field delimiter.
     *
     * @throws InvalidArgument If the Csv control character is not one character only.
     */
    public function setDelimiter(string $delimiter): static
    {
        if ($delimiter === $this->delimiter) {
            return $this;
        }

        1 === strlen($delimiter) || throw InvalidArgument::dueToInvalidDelimiterCharacter($delimiter, __METHOD__);

        $this->delimiter = $delimiter;
        $this->resetProperties();

        return $this;
    }

    /**
     * Sets the field enclosure.
     *
     * @throws InvalidArgument If the Csv control character is not one character only.
     */
    public function setEnclosure(string $enclosure): static
    {
        if ($enclosure === $this->enclosure) {
            return $this;
        }

        1 === strlen($enclosure) || throw InvalidArgument::dueToInvalidEnclosureCharacter($enclosure, __METHOD__);

        $this->enclosure = $enclosure;
        $this->resetProperties();

        return $this;
    }

    /**
     * Sets the field escape character.
     *
     * @throws InvalidArgument If the Csv control character is not one character only.
     */
    public function setEscape(string $escape): static
    {
        if ($escape === $this->escape) {
            return $this;
        }

        if ('' !== $escape && 1 !== strlen($escape)) {
            throw InvalidArgument::dueToInvalidEscapeCharacter($escape, __METHOD__);
        }

        $this->escape = $escape;
        $this->resetProperties();

        return $this;
    }

    /**
     * Adds a record formatter.
     *
     * @param callable(array): array $formatter
     */
    public function addFormatter(callable $formatter): static
    {
        $this->formatters[] = !$formatter instanceof Closure ? $formatter(...) : $formatter;

        return $this;
    }

    /**
     * Enables BOM Stripping.
     */
    public function skipInputBOM(): static
    {
        $this->is_input_bom_included = false;

        return $this;
    }

    /**
     * Disables skipping Input BOM.
     */
    public function includeInputBOM(): static
    {
        $this->is_input_bom_included = true;

        return $this;
    }

    /**
     * Sets the BOM sequence to prepend the CSV on output.
     *
     * @throws InvalidArgument if the given non-empty string is not a valid BOM sequence
     */
    public function setOutputBOM(Bom|string|null $str): static
    {
        try {
            $this->output_bom = match (true) {
                $str instanceof Bom => $str,
                null === $str,
                '' === $str => null,
                default => Bom::fromSequence($str),
            };

            return $this;
        } catch (Throwable $exception) {
            throw InvalidArgument::dueToInvalidBOMCharacter(__METHOD__, $exception);
        }
    }

    /**
     * Append a stream filter.
     *
     * @throws InvalidArgument If the stream filter API can not be appended
     * @throws UnavailableFeature If the stream filter API can not be used
     */
    public function appendStreamFilterOnRead(string $filtername, mixed $params = null): static
    {
        $this->document instanceof Stream || throw UnavailableFeature::dueToUnsupportedStreamFilterApi(get_class($this->document));

        $this->document->appendFilter($filtername, STREAM_FILTER_READ, $params);
        $this->stream_filters[$filtername] = true;
        $this->resetProperties();
        $this->input_bom = null;

        return $this;
    }

    /**
     * Append a stream filter.
     *
     * @throws InvalidArgument If the stream filter API can not be appended
     * @throws UnavailableFeature If the stream filter API can not be used
     */
    public function appendStreamFilterOnWrite(string $filtername, mixed $params = null): static
    {
        $this->document instanceof Stream || throw UnavailableFeature::dueToUnsupportedStreamFilterApi(get_class($this->document));

        $this->document->appendFilter($filtername, STREAM_FILTER_WRITE, $params);
        $this->stream_filters[$filtername] = true;
        $this->resetProperties();
        $this->input_bom = null;

        return $this;
    }

    /**
     * Prepend a stream filter.
     *
     * @throws InvalidArgument If the stream filter API can not be appended
     * @throws UnavailableFeature If the stream filter API can not be used
     */
    public function prependStreamFilterOnWrite(string $filtername, mixed $params = null): static
    {
        $this->document instanceof Stream || throw UnavailableFeature::dueToUnsupportedStreamFilterApi(get_class($this->document));

        $this->document->prependFilter($filtername, STREAM_FILTER_READ, $params);
        $this->stream_filters[$filtername] = true;
        $this->resetProperties();
        $this->input_bom = null;

        return $this;
    }

    /**
     * Prepend a stream filter.
     *
     * @throws InvalidArgument If the stream filter API can not be appended
     * @throws UnavailableFeature If the stream filter API can not be used
     */
    public function prependStreamFilterOnRead(string $filtername, mixed $params = null): static
    {
        $this->document instanceof Stream || throw UnavailableFeature::dueToUnsupportedStreamFilterApi(get_class($this->document));

        $this->document->prependFilter($filtername, STREAM_FILTER_READ, $params);
        $this->stream_filters[$filtername] = true;
        $this->resetProperties();
        $this->input_bom = null;

        return $this;
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @deprecated since version 9.7.0
     * @see AbstractCsv::supportsStreamFilterOnRead
     * @see AbstractCsv::supportsStreamFilterOnWrite
     * @codeCoverageIgnore
     *
     * Returns the stream filter mode.
     */
    #[Deprecated(message:'use League\Csv\AbstractCsv::supportsStreamFilterOnRead() or League\Csv\AbstractCsv::supportsStreamFilterOnWrite() instead', since:'league/csv:9.7.0')]
    public function getStreamFilterMode(): int
    {
        return static::STREAM_FILTER_MODE;
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @deprecated since version 9.7.0
     * @see AbstractCsv::supportsStreamFilterOnRead
     * @see AbstractCsv::supportsStreamFilterOnWrite
     * @codeCoverageIgnore
     *
     * Tells whether the stream filter capabilities can be used.
     */
    #[Deprecated(message:'use League\Csv\AbstractCsv::supportsStreamFilterOnRead() or League\Csv\AbstractCsv::supportsStreamFilterOnWrite() instead', since:'league/csv:9.7.0')]
    public function supportsStreamFilter(): bool
    {
        return $this->document instanceof Stream;
    }

    /**
     * Retrieves the CSV content.
     *
     * DEPRECATION WARNING! This method will be removed in the next major point release
     *
     * @deprecated since version 9.7.0
     * @see AbstractCsv::toString
     * @codeCoverageIgnore
     */
    #[Deprecated(message:'use League\Csv\AbstractCsv::toString() instead', since:'league/csv:9.7.0')]
    public function getContent(): string
    {
        return $this->toString();
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @deprecated since version 9.1.0
     * @see AbstractCsv::toString
     * @codeCoverageIgnore
     *
     * Retrieves the CSV content
     */
    #[Deprecated(message:'use League\Csv\AbstractCsv::toString() instead', since:'league/csv:9.1.0')]
    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @throws Exception if the submitted header is invalid according to RFC 6266
     *
     * @see HttpHeaders::forFileDownload()
     * @codeCoverageIgnore
     *
     * Send the CSV headers.
     *
     * Adapted from Symfony\Component\HttpFoundation\ResponseHeaderBag::makeDisposition
     *
     * @deprecated since version 9.17.0
     * @see https://tools.ietf.org/html/rfc6266#section-4.3
     */
    #[Deprecated(message:'the method no longer affect the outcome of the class, use League\Csv\HttpHeaders::forFileDownload instead', since:'league/csv:9.17.0')]
    protected function sendHeaders(string $filename): void
    {
        if (strlen($filename) !== strcspn($filename, '\\/')) {
            throw InvalidArgument::dueToInvalidHeaderFilename($filename);
        }

        $flag = FILTER_FLAG_STRIP_LOW;
        if (1 === preg_match('/[^\x20-\x7E]/', $filename)) {
            $flag |= FILTER_FLAG_STRIP_HIGH;
        }

        /** @var string $filtered_name */
        $filtered_name = filter_var($filename, FILTER_UNSAFE_RAW, $flag);
        $filename_fallback = str_replace('%', '', $filtered_name);

        $disposition = sprintf('attachment; filename="%s"', str_replace('"', '\\"', $filename_fallback));
        if ($filename !== $filename_fallback) {
            $disposition .= sprintf("; filename*=utf-8''%s", rawurlencode($filename));
        }

        header('Content-Type: text/csv');
        header('Content-Transfer-Encoding: binary');
        header('Content-Description: File Transfer');
        header('Content-Disposition: '.$disposition);
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @codeCoverageIgnore
     * @deprecated since version 9.18.0
     * @see AbstractCsv::download()
     *
     * Outputs all data on the CSV file.
     *
     * Returns the number of characters read from the handle and passed through to the output.
     *
     * @throws Exception
     */
    #[Deprecated(message:'use League\Csv\AbstractCsv::download() instead', since:'league/csv:9.18.0')]
    public function output(?string $filename = null): int
    {
        try {
            return $this->download($filename);
        } catch (InvalidArgumentException $exception) {
            throw new InvalidArgument($exception->getMessage());
        }
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     * @codeCoverageIgnore
     * @deprecated since version 9.22.0
     * @see AbstractCsv::appendStreamFilterOnRead()
     * @see AbstractCsv::appendStreamFilterOnWrite()
     *
     * Append a stream filter.
     *
     * @throws InvalidArgument If the stream filter API can not be appended
     * @throws UnavailableFeature If the stream filter API can not be used
     */
    #[Deprecated(message:'use League\Csv\AbstractCsv::appendStreamFilterOnRead() or League\Csv\AbstractCsv::prependStreamFilterOnRead() instead', since:'league/csv:9.18.0')]
    public function addStreamFilter(string $filtername, ?array $params = null): static
    {
        if (STREAM_FILTER_READ === static::STREAM_FILTER_MODE) {
            return $this->appendStreamFilterOnRead($filtername, $params);
        }

        return $this->appendStreamFilterOnWrite($filtername, $params);
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     * @codeCoverageIgnore
     * @deprecated since version 9.27.0
     *
     * Returns a new instance from a SplFileObject.
     */
    #[Deprecated(message:'use League\Csv\AbstractCsv::from() instead', since:'league/csv:9.27.0')]
    public static function createFromFileObject(SplFileObject $file): static
    {
        return new static($file);
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     * @codeCoverageIgnore
     * @deprecated since version 9.27.0
     *
     * Returns a new instance from a PHP resource stream.
     *
     * @param resource $stream
     */
    #[Deprecated(message:'use League\Csv\AbstractCsv::from() instead', since:'league/csv:9.27.0')]
    public static function createFromStream($stream): static
    {
        is_resource($stream) || throw new TypeError('Argument passed must be a stream resource or a string, '.gettype($stream).' given.');

        return new static(Stream::from($stream));
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     * @codeCoverageIgnore
     * @deprecated since version 9.27.0
     *
     * Returns a new instance from a string.
     */
    #[Deprecated(message:'use League\Csv\AbstractCsv::fromString() instead', since:'league/csv:9.27.0')]
    public static function createFromString(Stringable|string $content = ''): static
    {
        return self::fromString($content);
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     * @codeCoverageIgnore
     * @deprecated since version 9.27.0
     *
     * Returns a new instance from a file path.
     *
     * @param non-empty-string $open_mode
     * @param resource|null $context the resource context
     *
     * @throws UnavailableStream
     */
    #[Deprecated(message:'use League\Csv\AbstractCsv::from() instead', since:'league/csv:9.27.0')]
    public static function createFromPath(string $path, string $open_mode = 'r+', $context = null): static
    {
        return new static(Stream::from($path, $open_mode, $context));
    }
}
