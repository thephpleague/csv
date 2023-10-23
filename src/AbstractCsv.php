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

use Generator;
use RuntimeException;
use SplFileObject;
use Stringable;

use function filter_var;
use function get_class;
use function mb_strlen;
use function rawurlencode;
use function sprintf;
use function str_replace;
use function str_split;
use function strcspn;
use function strlen;

use const FILTER_FLAG_STRIP_HIGH;
use const FILTER_FLAG_STRIP_LOW;
use const FILTER_UNSAFE_RAW;

/**
 * An abstract class to enable CSV document loading.
 */
abstract class AbstractCsv implements ByteSequence
{
    protected const STREAM_FILTER_MODE = STREAM_FILTER_READ;

    /** @var array<string, bool> collection of stream filters. */
    protected array $stream_filters = [];
    protected ?string $input_bom = null;
    protected string $output_bom = '';
    protected string $delimiter = ',';
    protected string $enclosure = '"';
    protected string $escape = '\\';
    protected bool $is_input_bom_included = false;

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
     * Returns a new instance from a SplFileObject.
     */
    public static function createFromFileObject(SplFileObject $file): static
    {
        return new static($file);
    }

    /**
     * Returns a new instance from a PHP resource stream.
     *
     * @param resource $stream
     */
    public static function createFromStream($stream): static
    {
        return new static(Stream::createFromResource($stream));
    }

    /**
     * Returns a new instance from a string.
     */
    public static function createFromString(Stringable|string $content = ''): static
    {
        return new static(Stream::createFromString((string) $content));
    }

    /**
     * Returns a new instance from a file path.
     *
     * @param resource|null $context the resource context
     *
     * @throws UnavailableStream
     */
    public static function createFromPath(string $path, string $open_mode = 'r+', $context = null): static
    {
        return new static(Stream::createFromPath($path, $open_mode, $context));
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
        return $this->output_bom;
    }

    /**
     * Returns the BOM sequence of the given CSV.
     */
    public function getInputBOM(): string
    {
        if (null !== $this->input_bom) {
            return $this->input_bom;
        }

        $this->document->setFlags(SplFileObject::READ_CSV);
        $this->document->rewind();
        $this->input_bom = Info::fetchBOMSequence((string) $this->document->fread(4)) ?? '';

        return $this->input_bom;
    }

    /**
     * Tells whether the stream filter read capabilities can be used.
     */
    public function supportsStreamFilterOnRead(): bool
    {
        return $this->document instanceof Stream
            && (static::STREAM_FILTER_MODE & STREAM_FILTER_READ) === STREAM_FILTER_READ;
    }

    /**
     * Tells whether the stream filter write capabilities can be used.
     */
    public function supportsStreamFilterOnWrite(): bool
    {
        return $this->document instanceof Stream
            && (static::STREAM_FILTER_MODE & STREAM_FILTER_WRITE) === STREAM_FILTER_WRITE;
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
        if ($length < 1) {
            throw InvalidArgument::dueToInvalidChunkSize($length, __METHOD__);
        }

        $this->document->rewind();
        $this->document->setFlags(0);
        if (-1 === $this->document->fseek(strlen($this->getInputBOM()))) {
            throw new RuntimeException('Unable to seek the document.');
        }

        yield from str_split($this->output_bom.$this->document->fread($length), $length);

        while ($this->document->valid()) {
            yield $this->document->fread($length);
        }
    }

    /**
     * Retrieves the CSV content.
     *
     * @throws Exception If the string representation can not be returned
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
     * @throws Exception
     */
    public function output(string $filename = null): int
    {
        if (null !== $filename) {
            $this->sendHeaders($filename);
        }

        $this->document->rewind();
        $this->document->setFlags(0);
        if (!$this->is_input_bom_included && -1 === $this->document->fseek(strlen($this->getInputBOM()))) {
            throw new RuntimeException('Unable to seek the document.');
        }

        $stream = Stream::createFromString($this->output_bom);
        $stream->rewind();

        $res1 = $stream->fpassthru();
        if (false === $res1) {
            throw new RuntimeException('Unable to output the document.');
        }

        $res2 = $this->document->fpassthru();
        if (false === $res2) {
            throw new RuntimeException('Unable to output the document.');
        }

        return $res1 + $res2;
    }

    /**
     * Send the CSV headers.
     *
     * Adapted from Symfony\Component\HttpFoundation\ResponseHeaderBag::makeDisposition
     *
     * @throws Exception if the submitted header is invalid according to RFC 6266
     *
     * @see https://tools.ietf.org/html/rfc6266#section-4.3
     */
    protected function sendHeaders(string $filename): void
    {
        if (strlen($filename) !== strcspn($filename, '\\/')) {
            throw InvalidArgument::dueToInvalidHeaderFilename($filename);
        }

        $flag = FILTER_FLAG_STRIP_LOW;
        if (strlen($filename) !== mb_strlen($filename)) {
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
     * Sets the field delimiter.
     *
     * @throws InvalidArgument If the Csv control character is not one character only.
     */
    public function setDelimiter(string $delimiter): static
    {
        if ($delimiter === $this->delimiter) {
            return $this;
        }

        if (1 !== strlen($delimiter)) {
            throw InvalidArgument::dueToInvalidDelimiterCharacter($delimiter, __METHOD__);
        }

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

        if (1 !== strlen($enclosure)) {
            throw InvalidArgument::dueToInvalidEnclosureCharacter($enclosure, __METHOD__);
        }

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
     */
    public function setOutputBOM(string $str): static
    {
        $this->output_bom = $str;

        return $this;
    }

    /**
     * Append a stream filter.
     *
     * @throws InvalidArgument If the stream filter API can not be appended
     * @throws UnavailableFeature If the stream filter API can not be used
     */
    public function addStreamFilter(string $filtername, null|array $params = null): static
    {
        if (!$this->document instanceof Stream) {
            throw UnavailableFeature::dueToUnsupportedStreamFilterApi(get_class($this->document));
        }

        $this->document->appendFilter($filtername, static::STREAM_FILTER_MODE, $params);
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
    public function __toString(): string
    {
        return $this->toString();
    }
}
