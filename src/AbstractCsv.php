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
use SplFileObject;
use function filter_var;
use function mb_strlen;
use function rawurlencode;
use function sprintf;
use function str_replace;
use function str_split;
use function strcspn;
use function strlen;
use const FILTER_FLAG_STRIP_HIGH;
use const FILTER_FLAG_STRIP_LOW;
use const FILTER_SANITIZE_STRING;

/**
 * An abstract class to enable CSV document loading.
 */
abstract class AbstractCsv implements ByteSequence
{
    /**
     * The stream filter mode (read or write).
     *
     * @var int
     */
    protected $stream_filter_mode;

    /**
     * collection of stream filters.
     *
     * @var bool[]
     */
    protected $stream_filters = [];

    /**
     * The CSV document BOM sequence.
     *
     * @var string|null
     */
    protected $input_bom = null;

    /**
     * The Output file BOM character.
     *
     * @var string
     */
    protected $output_bom = '';

    /**
     * the field delimiter (one character only).
     *
     * @var string
     */
    protected $delimiter = ',';

    /**
     * the field enclosure character (one character only).
     *
     * @var string
     */
    protected $enclosure = '"';

    /**
     * the field escape character (one character only).
     *
     * @var string
     */
    protected $escape = '\\';

    /**
     * The CSV document.
     *
     * @var SplFileObject|Stream
     */
    protected $document;

    /**
     * New instance.
     *
     * @param SplFileObject|Stream $document The CSV Object instance
     */
    protected function __construct($document)
    {
        $this->document = $document;
        list($this->delimiter, $this->enclosure, $this->escape) = $this->document->getCsvControl();
        $this->resetProperties();
    }

    /**
     * Reset dynamic object properties to improve performance.
     */
    protected function resetProperties()
    {
    }

    /**
     * {@inheritdoc}
     */
    public function __destruct()
    {
        unset($this->document);
    }

    /**
     * {@inheritdoc}
     */
    public function __clone()
    {
        throw new Exception(sprintf('An object of class %s cannot be cloned', static::class));
    }

    /**
     * Return a new instance from a SplFileObject.
     *
     * @return static
     */
    public static function createFromFileObject(SplFileObject $file)
    {
        return new static($file);
    }

    /**
     * Return a new instance from a PHP resource stream.
     *
     * @param resource $stream
     *
     * @return static
     */
    public static function createFromStream($stream)
    {
        return new static(new Stream($stream));
    }

    /**
     * Return a new instance from a string.
     *
     * @return static
     */
    public static function createFromString(string $content = '')
    {
        return new static(Stream::createFromString($content));
    }

    /**
     * Return a new instance from a file path.
     *
     * @param resource|null $context the resource context
     *
     * @return static
     */
    public static function createFromPath(string $path, string $open_mode = 'r+', $context = null)
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
        $this->input_bom = bom_match((string) $this->document->fread(4));

        return $this->input_bom;
    }

    /**
     * Returns the stream filter mode.
     */
    public function getStreamFilterMode(): int
    {
        return $this->stream_filter_mode;
    }

    /**
     * Tells whether the stream filter capabilities can be used.
     */
    public function supportsStreamFilter(): bool
    {
        return $this->document instanceof Stream;
    }

    /**
     * Tell whether the specify stream filter is attach to the current stream.
     */
    public function hasStreamFilter(string $filtername): bool
    {
        return $this->stream_filters[$filtername] ?? false;
    }

    /**
     * Retuns the CSV document as a Generator of string chunk.
     *
     * @param int $length number of bytes read
     *
     * @throws Exception if the number of bytes is lesser than 1
     */
    public function chunk(int $length): Generator
    {
        if ($length < 1) {
            throw new Exception(sprintf('%s() expects the length to be a positive integer %d given', __METHOD__, $length));
        }

        $input_bom = $this->getInputBOM();
        $this->document->rewind();
        $this->document->setFlags(0);
        $this->document->fseek(strlen($input_bom));
        foreach (str_split($this->output_bom.$this->document->fread($length), $length) as $chunk) {
            yield $chunk;
        }

        while ($this->document->valid()) {
            yield $this->document->fread($length);
        }
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @deprecated deprecated since version 9.1.0
     * @see AbstractCsv::getContent
     *
     * Retrieves the CSV content
     */
    public function __toString(): string
    {
        return $this->getContent();
    }

    /**
     * Retrieves the CSV content.
     */
    public function getContent(): string
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
     * @return int Returns the number of characters read from the handle
     *             and passed through to the output.
     */
    public function output(string $filename = null): int
    {
        if (null !== $filename) {
            $this->sendHeaders($filename);
        }
        $input_bom = $this->getInputBOM();
        $this->document->rewind();
        $this->document->fseek(strlen($input_bom));
        echo $this->output_bom;

        return strlen($this->output_bom) + $this->document->fpassthru();
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
    protected function sendHeaders(string $filename)
    {
        if (strlen($filename) != strcspn($filename, '\\/')) {
            throw new Exception('The filename cannot contain the "/" and "\\" characters.');
        }

        $flag = FILTER_FLAG_STRIP_LOW;
        if (strlen($filename) !== mb_strlen($filename)) {
            $flag |= FILTER_FLAG_STRIP_HIGH;
        }

        $filenameFallback = str_replace('%', '', filter_var($filename, FILTER_SANITIZE_STRING, $flag));

        $disposition = sprintf('attachment; filename="%s"', str_replace('"', '\\"', $filenameFallback));
        if ($filename !== $filenameFallback) {
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
     * @throws Exception If the Csv control character is not one character only.
     *
     * @return static
     */
    public function setDelimiter(string $delimiter): self
    {
        if ($delimiter === $this->delimiter) {
            return $this;
        }

        if (1 === strlen($delimiter)) {
            $this->delimiter = $delimiter;
            $this->resetProperties();

            return $this;
        }

        throw new Exception(sprintf('%s() expects delimiter to be a single character %s given', __METHOD__, $delimiter));
    }

    /**
     * Sets the field enclosure.
     *
     * @throws Exception If the Csv control character is not one character only.
     *
     * @return static
     */
    public function setEnclosure(string $enclosure): self
    {
        if ($enclosure === $this->enclosure) {
            return $this;
        }

        if (1 === strlen($enclosure)) {
            $this->enclosure = $enclosure;
            $this->resetProperties();

            return $this;
        }

        throw new Exception(sprintf('%s() expects enclosure to be a single character %s given', __METHOD__, $enclosure));
    }

    /**
     * Sets the field escape character.
     *
     * @throws Exception If the Csv control character is not one character only.
     *
     * @return static
     */
    public function setEscape(string $escape): self
    {
        if ($escape === $this->escape) {
            return $this;
        }

        if ('' === $escape || 1 === strlen($escape)) {
            $this->escape = $escape;
            $this->resetProperties();

            return $this;
        }

        throw new Exception(sprintf('%s() expects escape to be a single character or the empty string %s given', __METHOD__, $escape));
    }

    /**
     * Sets the BOM sequence to prepend the CSV on output.
     *
     * @return static
     */
    public function setOutputBOM(string $str): self
    {
        $this->output_bom = $str;

        return $this;
    }

    /**
     * append a stream filter.
     *
     * @param null|mixed $params
     *
     * @throws Exception If the stream filter API can not be used
     *
     * @return static
     */
    public function addStreamFilter(string $filtername, $params = null): self
    {
        if (!$this->document instanceof Stream) {
            throw new Exception('The stream filter API can not be used');
        }

        $this->document->appendFilter($filtername, $this->stream_filter_mode, $params);
        $this->stream_filters[$filtername] = true;
        $this->resetProperties();
        $this->input_bom = null;

        return $this;
    }
}
