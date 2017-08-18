<?php
/**
* This file is part of the League.csv library
*
* @license http://opensource.org/licenses/MIT
* @link https://github.com/thephpleague/csv/
* @version 9.0.0
* @package League.csv
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
declare(strict_types=1);

namespace League\Csv;

use Generator;
use SplFileObject;
use function League\Csv\bom_match;

/**
 * An abstract class to enable CSV document loading.
 *
 * @package League.csv
 * @since   4.0.0
 * @author  Ignace Nyamagana Butera <nyamsprod@gmail.com>
 */
abstract class AbstractCsv implements ByteSequence
{
    /**
     * The stream filter mode (read or write)
     *
     * @var int
     */
    protected $stream_filter_mode;


    /**
     * collection of stream filters
     *
     * @var bool[]
     */
    protected $stream_filters = [];

    /**
     * The CSV document BOM sequence
     *
     * @var string|null
     */
    protected $input_bom = null;

    /**
     * The Output file BOM character
     *
     * @var string
     */
    protected $output_bom = '';

    /**
     * the field delimiter (one character only)
     *
     * @var string
     */
    protected $delimiter = ',';

    /**
     * the field enclosure character (one character only)
     *
     * @var string
     */
    protected $enclosure = '"';

    /**
     * the field escape character (one character only)
     *
     * @var string
     */
    protected $escape = '\\';

    /**
     * The CSV document
     *
     * @var SplFileObject|Stream
     */
    protected $document;

    /**
     * New instance
     *
     * @param SplFileObject|Stream $document The CSV Object instance
     */
    protected function __construct($document)
    {
        $this->document = $document;
        list($this->delimiter, $this->enclosure, $this->escape) = $this->document->getCsvControl();
    }

    /**
     * @inheritdoc
     */
    public function __destruct()
    {
        unset($this->document);
    }

    /**
     * @inheritdoc
     */
    public function __clone()
    {
        throw new Exception(sprintf('An object of class %s cannot be cloned', get_class($this)));
    }

    /**
     * Return a new instance from a SplFileObject
     *
     * @param SplFileObject $file
     *
     * @return static
     */
    public static function createFromFileObject(SplFileObject $file): self
    {
        return new static($file);
    }

    /**
     * Return a new instance from a PHP resource stream
     *
     * @param resource $stream
     *
     * @return static
     */
    public static function createFromStream($stream): self
    {
        return new static(new Stream($stream));
    }

    /**
     * Return a new instance from a string
     *
     * @param string $content the CSV document as a string
     *
     * @return static
     */
    public static function createFromString(string $content): self
    {
        return new static(Stream::createFromString($content));
    }

    /**
     * Return a new instance from a file path
     *
     * @param string        $path      file path
     * @param string        $open_mode the file open mode flag
     * @param resource|null $context   the resource context
     *
     * @return static
     */
    public static function createFromPath(string $path, string $open_mode = 'r+', $context = null): self
    {
        return new static(Stream::createFromPath($path, $open_mode, $context));
    }

    /**
     * Returns the current field delimiter
     *
     * @return string
     */
    public function getDelimiter(): string
    {
        return $this->delimiter;
    }

    /**
     * Returns the current field enclosure
     *
     * @return string
     */
    public function getEnclosure(): string
    {
        return $this->enclosure;
    }

    /**
     * Returns the current field escape character
     *
     * @return string
     */
    public function getEscape(): string
    {
        return $this->escape;
    }

    /**
     * Returns the BOM sequence in use on Output methods
     *
     * @return string
     */
    public function getOutputBOM(): string
    {
        return $this->output_bom;
    }

    /**
     * Returns the BOM sequence of the given CSV
     *
     * @return string
     */
    public function getInputBOM(): string
    {
        if (null !== $this->input_bom) {
            return $this->input_bom;
        }

        $this->document->setFlags(SplFileObject::READ_CSV);
        $this->document->setCsvControl($this->delimiter, $this->enclosure, $this->escape);
        $this->document->rewind();
        $this->input_bom = bom_match(implode(',', (array) $this->document->current()));

        return $this->input_bom;
    }

    /**
     * Returns the stream filter mode
     *
     * @return int
     */
    public function getStreamFilterMode(): int
    {
        return $this->stream_filter_mode;
    }

    /**
     * Tells whether the stream filter capabilities can be used
     *
     * @return bool
     */
    public function supportsStreamFilter(): bool
    {
        return $this->document instanceof Stream;
    }

    /**
     * Tell whether the specify stream filter is attach to the current stream
     *
     * @param string $filtername
     *
     * @return bool
     */
    public function hasStreamFilter(string $filtername): bool
    {
        return $this->stream_filters[$filtername] ?? false;
    }

    /**
     * Retrieves the CSV content
     *
     * @return string
     */
    public function __toString(): string
    {
        $raw = '';
        foreach ($this->chunk(8192) as $chunk) {
            $raw .= $chunk;
        }

        return $raw;
    }

    /**
     * Retuns the CSV document as a Generator of string chunk
     *
     * @param int $length number of bytes read
     *
     * @return Generator
     */
    public function chunk(int $length): Generator
    {
        if ($length < 1) {
            throw new Exception(sprintf('%s() expects the length to be a positive integer %d given', __METHOD__, $length));
        }

        $input_bom = $this->getInputBOM();
        $this->document->rewind();
        $this->document->fseek(strlen($input_bom));
        foreach (str_split($this->output_bom.$this->document->fread($length), $length) as $chunk) {
            yield $chunk;
        }

        while ($this->document->valid()) {
            yield $this->document->fread($length);
        }
    }

    /**
     * Outputs all data on the CSV file
     *
     * @param string $filename CSV downloaded name if present adds extra headers
     *
     * @return int Returns the number of characters read from the handle
     *             and passed through to the output.
     */
    public function output(string $filename = null): int
    {
        if (null !== $filename) {
            header('Content-Type: text/csv');
            header('Content-Transfer-Encoding: binary');
            header('Content-Description: File Transfer');
            header('Content-Disposition: attachment; filename="'.rawurlencode($filename).'"');
        }

        $input_bom = $this->getInputBOM();
        $this->document->rewind();
        $this->document->fseek(strlen($input_bom));
        echo $this->output_bom;

        return strlen($this->output_bom) + $this->document->fpassthru();
    }

    /**
     * Sets the field delimiter
     *
     * @param string $delimiter
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
     * Reset dynamic object properties to improve performance
     */
    protected function resetProperties()
    {
    }

    /**
     * Sets the field enclosure
     *
     * @param string $enclosure
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
     * Sets the field escape character
     *
     * @param string $escape
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

        if (1 === strlen($escape)) {
            $this->escape = $escape;
            $this->resetProperties();

            return $this;
        }

        throw new Exception(sprintf('%s() expects escape to be a single character %s given', __METHOD__, $escape));
    }

    /**
     * Sets the BOM sequence to prepend the CSV on output
     *
     * @param string $str The BOM sequence
     *
     * @return static
     */
    public function setOutputBOM(string $str): self
    {
        $this->output_bom = $str;

        return $this;
    }

    /**
     * append a stream filter
     *
     * @param string $filtername a string or an object that implements the '__toString' method
     * @param mixed  $params     additional parameters for the filter
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
