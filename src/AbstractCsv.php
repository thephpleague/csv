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
use League\Csv\Exception\LogicException;
use League\Csv\Exception\RuntimeException;
use SplFileObject;
use function League\Csv\bom_match;

/**
 *  An abstract class to enable CSV document loading.
 *
 * @package League.csv
 * @since   4.0.0
 * @author  Ignace Nyamagana Butera <nyamsprod@gmail.com>
 *
 */
abstract class AbstractCsv
{
    use ValidatorTrait;

    /**
     * The CSV document
     *
     * @var StreamIterator|SplFileObject
     */
    protected $document;

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
     * collection of stream filters
     *
     * @var array
     */
    protected $stream_filters = [];

    /**
     * The stream filter mode (read or write)
     *
     * @var int
     */
    protected $stream_filter_mode;

    /**
     * New instance
     *
     * @param SplFileObject|StreamIterator $document The CSV Object instance
     */
    protected function __construct($document)
    {
        $this->document = $document;
    }

    /**
     * @inheritdoc
     */
    public function __clone()
    {
        throw new LogicException('An object of class '.get_class($this).' cannot be cloned');
    }

    /**
     * Return a new {@link AbstractCsv} from a SplFileObject
     *
     * @param SplFileObject $file
     *
     * @return static
     */
    public static function createFromFileObject(SplFileObject $file): self
    {
        $csv = new static($file);
        $controls = $file->getCsvControl();
        $csv->delimiter = $controls[0];
        $csv->enclosure = $controls[1];
        if (isset($controls[2])) {
            $csv->escape = $controls[2];
        }

        return $csv;
    }

    /**
     * Return a new {@link AbstractCsv} from a PHP resource stream
     *
     * @param resource $stream
     *
     * @return static
     */
    public static function createFromStream($stream): self
    {
        return new static(new StreamIterator($stream));
    }

    /**
     * Return a new {@link AbstractCsv} from a string
     *
     * @param string $str the CSV document as a string
     *
     * @return static
     */
    public static function createFromString(string $str): self
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $str);

        return new static(new StreamIterator($stream));
    }

    /**
     * Return a new {@link AbstractCsv} from a file path
     *
     * @param string $path      file path
     * @param string $open_mode the file open mode flag
     *
     * @return static
     */
    public static function createFromPath(string $path, string $open_mode = 'r+'): self
    {
        if (!$stream = @fopen($path, $open_mode)) {
            throw new RuntimeException(error_get_last()['message']);
        }

        return new static(new StreamIterator($stream));
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
        if (null === $this->input_bom) {
            $this->document->setFlags(SplFileObject::READ_CSV);
            $this->document->rewind();
            $line = $this->document->fgets();
            $this->input_bom = false === $line ? '' : bom_match($line);
        }

        return $this->input_bom;
    }

    /**
     * Tells whether the stream filter capabilities can be used
     *
     * @return bool
     */
    public function supportsStreamFilter(): bool
    {
        return $this->document instanceof StreamIterator;
    }

    /**
     * Tell whether the specify stream filter is attach to the current stream
     *
     * @param  string $filtername
     * @return bool
     */
    public function hasStreamFilter(string $filtername): bool
    {
        return isset($this->stream_filters[$filtername]);
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
     * @throws InvalidArgumentException If the length is invalid
     *
     * @return Generator
     */
    public function chunk(int $length): Generator
    {
        $length = $this->filterMinRange($length, 1, 'The length must be a positive integer');
        $input_bom = $this->getInputBOM();
        $this->document->rewind();
        if ($input_bom != $this->output_bom) {
            $this->document->fseek(strlen($input_bom));
            $chunk = $this->output_bom.$this->document->fread($length);
            $this->document->fflush();
            yield $chunk;
        }

        while ($this->document->valid()) {
            $chunk = $this->document->fread($length);
            $this->document->fflush();
            yield $chunk;
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
            header('content-type: text/csv');
            header('content-transfer-encoding: binary');
            header('content-description: File Transfer');
            header('content-disposition: attachment; filename="'.rawurlencode($filename).'"');
        }

        $res = 0;
        $input_bom = $this->getInputBOM();
        $this->document->rewind();
        if ($input_bom != $this->output_bom) {
            $res = strlen($this->output_bom);
            $this->document->fseek(mb_strlen($input_bom));
            echo $this->output_bom;
        }

        return $res + $this->document->fpassthru();
    }

    /**
     * Sets the field delimiter
     *
     * @param string $delimiter
     *
     * @return static
     */
    public function setDelimiter(string $delimiter): self
    {
        $delimiter = $this->filterControl($delimiter, 'delimiter');
        if ($delimiter != $this->delimiter) {
            $this->delimiter = $delimiter;
            $this->resetProperties();
        }

        return $this;
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
     * @return static
     */
    public function setEnclosure(string $enclosure): self
    {
        $enclosure = $this->filterControl($enclosure, 'enclosure');
        if ($enclosure != $this->enclosure) {
            $this->enclosure = $enclosure;
            $this->resetProperties();
        }

        return $this;
    }

    /**
     * Sets the field escape character
     *
     * @param string $escape
     *
     * @return static
     */
    public function setEscape(string $escape): self
    {
        $escape = $this->filterControl($escape, 'escape');
        if ($escape != $this->escape) {
            $this->escape = $escape;
            $this->resetProperties();
        }


        return $this;
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
     * @throws LogicException If the stream filter API can not be used
     *
     * @return static
     */
    public function addStreamFilter(string $filtername, $params = null): self
    {
        if (!$this->document instanceof StreamIterator) {
            throw new LogicException('The stream filter API can not be used');
        }

        $this->stream_filters[$filtername][] = $this->document->appendFilter($filtername, $this->stream_filter_mode, $params);
        $this->resetProperties();
        $this->input_bom = null;

        return $this;
    }

    /**
     * The destructor
     */
    public function __destruct()
    {
        if ($this->document instanceof StreamIterator) {
            $walker = function ($filter): bool {
                return $this->document->removeFilter($filter);
            };

            array_walk_recursive($this->stream_filters, $walker);
        }

        $this->document = null;
    }
}
