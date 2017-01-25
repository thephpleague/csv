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

use ArrayIterator;
use CallbackFilterIterator;
use DomDocument;
use InvalidArgumentException;
use Iterator;
use IteratorAggregate;
use JsonSerializable;
use LimitIterator;
use LogicException;
use OutOfBoundsException;
use SplFileInfo;
use SplFileObject;
use SplTempFileObject;

/**
 *  An abstract class to enable basic CSV manipulation
 *
 * @package League.csv
 * @since  4.0.0
 *
 */
abstract class AbstractCsv implements JsonSerializable, IteratorAggregate
{
    /**
     *  UTF-8 BOM sequence
     */
    const BOM_UTF8 = "\xEF\xBB\xBF";

    /**
     * UTF-16 BE BOM sequence
     */
    const BOM_UTF16_BE = "\xFE\xFF";

    /**
     * UTF-16 LE BOM sequence
     */
    const BOM_UTF16_LE = "\xFF\xFE";

    /**
     * UTF-32 BE BOM sequence
     */
    const BOM_UTF32_BE = "\x00\x00\xFE\xFF";

    /**
     * UTF-32 LE BOM sequence
     */
    const BOM_UTF32_LE = "\xFF\xFE\x00\x00";

    /**
     * The path
     *
     * can be a StreamIterator object, a SplFileObject object or the string path to a file
     *
     * @var StreamIterator|SplFileObject|string
     */
    protected $path;

    /**
     * The file open mode flag
     *
     * @var string
     */
    protected $open_mode;

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
     * newline character
     *
     * @var string
     */
    protected $newline = "\n";

    /**
     * Charset Encoding for the CSV
     *
     * @var string
     */
    protected $input_encoding = 'UTF-8';

    /**
     * The Input file BOM character
     * @var string
     */
    protected $input_bom;

    /**
     * The Output file BOM character
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
     * Stream filtering mode to apply on all filters
     *
     * @var int
     */
    protected $stream_filter_mode = STREAM_FILTER_ALL;

    /**
     *the real path
     *
     * @var string the real path to the file
     *
     */
    protected $stream_uri;

    /**
     * PHP Stream Filter Regex
     *
     * @var string
     */
    protected $stream_regex = ',^
        php://filter/
        (?P<mode>:?read=|write=)?  # The resource open mode
        (?P<filters>.*?)           # The resource registered filters
        /resource=(?P<resource>.*) # The resource path
        $,ix';

    /**
     * Callables to filter the iterator
     *
     * @var callable[]
     */
    protected $iterator_filters = [];

    /**
     * Callables to sort the iterator
     *
     * @var callable[]
     */
    protected $iterator_sort_by = [];

    /**
     * iterator Offset
     *
     * @var int
     */
    protected $iterator_offset = 0;

    /**
     * iterator maximum length
     *
     * @var int
     */
    protected $iterator_limit = -1;

    /**
     * Stripping BOM status
     *
     * @var boolean
     */
    protected $strip_bom = false;

    /**
     * Creates a new instance
     *
     * The path must be an SplFileInfo object
     * an object that implements the `__toString` method
     * a path to a file
     *
     * @param StreamIterator|SplFileObject|string $path      The file path
     * @param string                              $open_mode The file open mode flag
     */
    protected function __construct($path, string $open_mode = 'r+')
    {
        $this->open_mode = strtolower($open_mode);
        $this->path = $path;
        $this->initStreamFilter();
    }

    /**
     * The destructor
     */
    public function __destruct()
    {
        $this->path = null;
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
        $csv->setDelimiter($controls[0]);
        $csv->setEnclosure($controls[1]);
        if (isset($controls[2])) {
            $csv->setEscape($controls[2]);
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
     * The string must be an object that implements the `__toString` method,
     * or a string
     *
     * @param string $str the string
     *
     * @return static
     */
    public static function createFromString(string $str): self
    {
        $file = new SplTempFileObject();
        $file->fwrite(static::validateString($str));

        return new static($file);
    }

    /**
     * validate a string
     *
     * @param mixed $str the value to evaluate as a string
     *
     * @throws InvalidArgumentException if the submitted data can not be converted to string
     *
     * @return string
     */
    protected static function validateString($str): string
    {
        if (is_string($str) || (is_object($str) && method_exists($str, '__toString'))) {
            return (string) $str;
        }
        throw new InvalidArgumentException('Expected data must be a string or stringable');
    }

    /**
     * Return a new {@link AbstractCsv} from a file path
     *
     * @param mixed  $path      file path
     * @param string $open_mode the file open mode flag
     *
     * @throws InvalidArgumentException If $path is a SplTempFileObject object
     *
     * @return static
     */
    public static function createFromPath($path, string $open_mode = 'r+'): self
    {
        if ($path instanceof SplTempFileObject) {
            throw new InvalidArgumentException('an `SplTempFileObject` object does not contain a valid path');
        }

        if ($path instanceof SplFileInfo) {
            return new static($path->getPathname(), $open_mode);
        }

        return new static(static::validateString($path), $open_mode);
    }

    /**
     * Return a new {@link AbstractCsv} instance from another {@link AbstractCsv} object
     *
     * @param string $class     the class to be instantiated
     * @param string $open_mode the file open mode flag
     *
     * @return static
     */
    protected function newInstance(string $class, string $open_mode): self
    {
        $csv = new $class($this->path, $open_mode);
        $csv->delimiter = $this->delimiter;
        $csv->enclosure = $this->enclosure;
        $csv->escape = $this->escape;
        $csv->input_encoding = $this->input_encoding;
        $csv->input_bom = $this->input_bom;
        $csv->output_bom = $this->output_bom;
        $csv->newline = $this->newline;

        return $csv;
    }

    /**
     * Return a new {@link Writer} instance from a {@link AbstractCsv} object
     *
     * @param string $open_mode the file open mode flag
     *
     * @return Writer
     */
    public function newWriter(string $open_mode = 'r+'): self
    {
        return $this->newInstance(Writer::class, $open_mode);
    }

    /**
     * Return a new {@link Reader} instance from a {@link AbstractCsv} object
     *
     * @param string $open_mode the file open mode flag
     *
     * @return Reader
     */
    public function newReader(string $open_mode = 'r+'): self
    {
        return $this->newInstance(Reader::class, $open_mode);
    }

    /**
     * Returns the inner CSV Document Iterator object
     *
     * @return StreamIterator|SplFileObject
     */
    public function getIterator()
    {
        $iterator = $this->setIterator();
        $iterator->setCsvControl($this->delimiter, $this->enclosure, $this->escape);
        $iterator->setFlags(SplFileObject::READ_CSV | SplFileObject::READ_AHEAD | SplFileObject::SKIP_EMPTY);

        return $iterator;
    }

    /**
     * Set the Inner Iterator
     *
     * @return StreamIterator|SplFileObject
     */
    protected function setIterator()
    {
        if ($this->path instanceof StreamIterator || $this->path instanceof SplFileObject) {
            return $this->path;
        }

        return new SplFileObject($this->getStreamFilterPath(), $this->open_mode);
    }

    /**
     * Sets the field delimiter
     *
     * @param string $delimiter
     *
     * @throws InvalidArgumentException If $delimiter is not a single character
     *
     * @return $this
     */
    public function setDelimiter(string $delimiter): self
    {
        $this->delimiter = $this->filterControl($delimiter, 'delimiter');

        return $this;
    }

    /**
     * Filter Csv control character
     *
     * @param string $char Csv control character
     * @param string $type Csv control character type
     *
     * @throws InvalidArgumentException If the Csv control character is not one character only.
     *
     * @return string
     */
    protected function filterControl(string $char, string $type)
    {
        if (1 == strlen($char)) {
            return $char;
        }

        throw new InvalidArgumentException(sprintf('The %s must be a single character', $type));
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
     * Detect Delimiters occurences in the CSV
     *
     * Returns a associative array where each key represents
     * a valid delimiter and each value the number of occurences
     *
     * @param string[] $delimiters the delimiters to consider
     * @param int      $nb_rows    Detection is made using $nb_rows of the CSV
     *
     * @return array
     */
    public function fetchDelimitersOccurrence(array $delimiters, int $nb_rows = 1): array
    {
        $nb_rows = $this->validateInteger($nb_rows, 1, 'The number of rows to consider must be a valid positive integer');
        $filter_row = function ($row) {
            return is_array($row) && count($row) > 1;
        };
        $delimiters = array_unique(array_filter($delimiters, function ($value) {
            return 1 == strlen($value);
        }));
        $csv = $this->getIterator();
        $res = [];
        foreach ($delimiters as $delim) {
            $csv->setCsvControl($delim, $this->enclosure, $this->escape);
            $iterator = new CallbackFilterIterator(new LimitIterator($csv, 0, $nb_rows), $filter_row);
            $res[$delim] = count(iterator_to_array($iterator, false), COUNT_RECURSIVE);
        }
        arsort($res, SORT_NUMERIC);

        return $res;
    }

    /**
     * Validate an integer
     *
     * @param int    $int
     * @param int    $minValue
     * @param string $errorMessage
     *
     * @throws InvalidArgumentException If the value is invalid
     *
     * @return int
     */
    protected function validateInteger(int $int, int $minValue, string $errorMessage): int
    {
        if ($int < $minValue) {
            throw new InvalidArgumentException($errorMessage);
        }

        return $int;
    }

    /**
     * Sets the field enclosure
     *
     * @param string $enclosure
     *
     * @throws InvalidArgumentException If $enclosure is not a single character
     *
     * @return $this
     */
    public function setEnclosure(string $enclosure): self
    {
        $this->enclosure = $this->filterControl($enclosure, 'enclosure');

        return $this;
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
     * Sets the field escape character
     *
     * @param string $escape
     *
     * @throws InvalidArgumentException If $escape is not a single character
     *
     * @return $this
     */
    public function setEscape(string $escape): self
    {
        $this->escape = $this->filterControl($escape, 'escape');

        return $this;
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
     * Sets the newline sequence characters
     *
     * @param string $newline
     *
     * @return static
     */
    public function setNewline(string $newline): self
    {
        $this->newline = (string) $newline;

        return $this;
    }

    /**
     * Returns the current newline sequence characters
     *
     * @return string
     */
    public function getNewline(): string
    {
        return $this->newline;
    }
    /**
     * Sets the CSV encoding charset
     *
     * @param string $str
     *
     * @return static
     */
    public function setInputEncoding(string $str): self
    {
        $str = str_replace('_', '-', $str);
        $str = filter_var($str, FILTER_SANITIZE_STRING, ['flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH]);
        if (empty($str)) {
            throw new InvalidArgumentException('you should use a valid charset');
        }
        $this->input_encoding = strtoupper($str);

        return $this;
    }

    /**
     * Gets the source CSV encoding charset
     *
     * @return string
     */
    public function getInputEncoding(): string
    {
        return $this->input_encoding;
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
        if (empty($str)) {
            $this->output_bom = '';

            return $this;
        }

        $this->output_bom = (string) $str;

        return $this;
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
            $bom = [
                AbstractCsv::BOM_UTF32_BE, AbstractCsv::BOM_UTF32_LE,
                AbstractCsv::BOM_UTF16_BE, AbstractCsv::BOM_UTF16_LE, AbstractCsv::BOM_UTF8,
            ];
            $csv = $this->getIterator();
            $csv->setFlags(SplFileObject::READ_CSV);
            $csv->rewind();
            $line = $csv->fgets();
            $res  = array_filter($bom, function ($sequence) use ($line) {
                return strpos($line, $sequence) === 0;
            });

            $this->input_bom = (string) array_shift($res);
        }

        return $this->input_bom;
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
            $filename = filter_var($filename, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW);
            header('Content-Type: text/csv');
            header('Content-Transfer-Encoding: binary');
            header("Content-Disposition: attachment; filename=\"$filename\"");
        }

        return $this->fpassthru();
    }

    /**
     * Outputs all data from the CSV
     *
     * @return int Returns the number of characters read from the handle
     *             and passed through to the output.
     */
    protected function fpassthru(): int
    {
        $bom = '';
        $input_bom = $this->getInputBOM();
        if ($this->output_bom && $input_bom != $this->output_bom) {
            $bom = $this->output_bom;
        }
        $csv = $this->getIterator();
        $csv->setFlags(SplFileObject::READ_CSV);
        $csv->rewind();
        if (!empty($bom)) {
            $csv->fseek(mb_strlen($input_bom));
        }
        echo $bom;
        $res = $csv->fpassthru();

        return $res + strlen($bom);
    }

    /**
     * Retrieves the CSV content
     *
     * @return string
     */
    public function __toString(): string
    {
        ob_start();
        $this->fpassthru();

        return ob_get_clean();
    }

    /**
     * @inheritdoc
     */
    public function jsonSerialize()
    {
        return iterator_to_array($this->convertToUtf8($this->getQueryIterator()), false);
    }

    /**
     * Convert Csv file into UTF-8
     *
     * @param Iterator $iterator
     *
     * @return Iterator
     */
    protected function convertToUtf8(Iterator $iterator): Iterator
    {
        if (stripos($this->input_encoding, 'UTF-8') !== false) {
            return $iterator;
        }

        $convert_cell = function ($value) {
            return mb_convert_encoding($value, 'UTF-8', $this->input_encoding);
        };

        $convert_row = function (array $row) use ($convert_cell) {
            return array_map($convert_cell, $row);
        };

        return new MapIterator($iterator, $convert_row);
    }

    /**
     * Returns a HTML table representation of the CSV Table
     *
     * @param string $class_attr optional classname
     *
     * @return string
     */
    public function toHTML(string $class_attr = 'table-csv-data'): string
    {
        $doc = $this->toXML('table', 'tr', 'td');
        $doc->documentElement->setAttribute('class', $class_attr);

        return $doc->saveHTML($doc->documentElement);
    }

    /**
     * Transforms a CSV into a XML
     *
     * @param string $root_name XML root node name
     * @param string $row_name  XML row node name
     * @param string $cell_name XML cell node name
     *
     * @return DomDocument
     */
    public function toXML(string $root_name = 'csv', string $row_name = 'row', string $cell_name = 'cell')
    {
        $doc = new DomDocument('1.0', 'UTF-8');
        $root = $doc->createElement($root_name);
        foreach ($this->convertToUtf8($this->getQueryIterator()) as $row) {
            $rowElement = $doc->createElement($row_name);
            array_walk($row, function ($value) use (&$rowElement, $doc, $cell_name) {
                $content = $doc->createTextNode($value);
                $cell = $doc->createElement($cell_name);
                $cell->appendChild($content);
                $rowElement->appendChild($cell);
            });
            $root->appendChild($rowElement);
        }
        $doc->appendChild($root);

        return $doc;
    }
    /**
     * Internal path setter
     */
    protected function initStreamFilter()
    {
        if (!is_string($this->path)) {
            return;
        }

        if (!preg_match($this->stream_regex, $this->path, $matches)) {
            $this->stream_uri = $this->path;

            return;
        }

        $this->stream_uri = $matches['resource'];
        $this->stream_filters = array_map('urldecode', explode('|', $matches['filters']));
        $this->stream_filter_mode = $this->fetchStreamModeAsInt($matches['mode']);
    }

    /**
     * Get the stream mode
     *
     * @param string $mode
     *
     * @return int
     */
    protected function fetchStreamModeAsInt(string $mode): int
    {
        $mode = strtolower($mode);
        $mode = rtrim($mode, '=');
        if ('write' == $mode) {
            return STREAM_FILTER_WRITE;
        }

        if ('read' == $mode) {
            return STREAM_FILTER_READ;
        }

        return STREAM_FILTER_ALL;
    }

    /**
     * Check if the trait methods can be used
     *
     * @throws LogicException If the API can not be use
     */
    protected function assertStreamable()
    {
        if (!is_string($this->stream_uri)) {
            throw new LogicException('The stream filter API can not be used');
        }
    }

    /**
     * Tells whether the stream filter capabilities can be used
     *
     * @return bool
     */
    public function isActiveStreamFilter(): bool
    {
        return is_string($this->stream_uri);
    }

    /**
     * stream filter mode Setter
     *
     * Set the new Stream Filter mode and remove all
     * previously attached stream filters
     *
     * @param int $mode
     *
     * @throws OutOfBoundsException If the mode is invalid
     *
     * @return $this
     */
    public function setStreamFilterMode(int $mode): self
    {
        $this->assertStreamable();
        if (!in_array($mode, [STREAM_FILTER_ALL, STREAM_FILTER_READ, STREAM_FILTER_WRITE])) {
            throw new OutOfBoundsException('the $mode should be a valid `STREAM_FILTER_*` constant');
        }

        $this->stream_filter_mode = $mode;
        $this->stream_filters = [];

        return $this;
    }

    /**
     * stream filter mode getter
     *
     * @return int
     */
    public function getStreamFilterMode(): int
    {
        $this->assertStreamable();

        return $this->stream_filter_mode;
    }

    /**
     * append a stream filter
     *
     * @param string $filter_name a string or an object that implements the '__toString' method
     *
     * @return $this
     */
    public function appendStreamFilter(string $filter_name): self
    {
        $this->assertStreamable();
        $this->stream_filters[] = $this->sanitizeStreamFilter($filter_name);

        return $this;
    }

    /**
     * prepend a stream filter
     *
     * @param string $filter_name a string or an object that implements the '__toString' method
     *
     * @return $this
     */
    public function prependStreamFilter(string $filter_name): self
    {
        $this->assertStreamable();
        array_unshift($this->stream_filters, $this->sanitizeStreamFilter($filter_name));

        return $this;
    }

    /**
     * Sanitize the stream filter name
     *
     * @param string $filter_name the stream filter name
     *
     * @return string
     */
    protected function sanitizeStreamFilter(string $filter_name): string
    {
        return urldecode($this->validateString($filter_name));
    }

    /**
     * Detect if the stream filter is already present
     *
     * @param string $filter_name
     *
     * @return bool
     */
    public function hasStreamFilter(string $filter_name): bool
    {
        $this->assertStreamable();

        return false !== array_search(urldecode($filter_name), $this->stream_filters, true);
    }

    /**
     * Remove a filter from the collection
     *
     * @param string $filter_name
     *
     * @return $this
     */
    public function removeStreamFilter(string $filter_name): self
    {
        $this->assertStreamable();
        $res = array_search(urldecode($filter_name), $this->stream_filters, true);
        if (false !== $res) {
            unset($this->stream_filters[$res]);
        }

        return $this;
    }

    /**
     * Remove all registered stream filter
     *
     * @return $this
     */
    public function clearStreamFilter(): self
    {
        $this->assertStreamable();
        $this->stream_filters = [];

        return $this;
    }

    /**
     * Return the filter path
     *
     * @return string
     */
    protected function getStreamFilterPath(): string
    {
        $this->assertStreamable();
        if (!$this->stream_filters) {
            return $this->stream_uri;
        }

        return 'php://filter/'
            .$this->getStreamFilterPrefix()
            .implode('|', array_map('urlencode', $this->stream_filters))
            .'/resource='.$this->stream_uri;
    }

    /**
     * Return PHP stream filter prefix
     *
     * @return string
     */
    protected function getStreamFilterPrefix(): string
    {
        if (STREAM_FILTER_READ == $this->stream_filter_mode) {
            return 'read=';
        }

        if (STREAM_FILTER_WRITE == $this->stream_filter_mode) {
            return 'write=';
        }

        return '';
    }

    /**
     * Stripping BOM setter
     *
     * DEPRECATION WARNING! This method will be removed in the next major point release
     *
     * @deprecated deprecated since version 8.2
     *
     * @param bool $status
     *
     * @return $this
     */
    public function stripBom(bool $status): self
    {
        $this->strip_bom = (bool) $status;

        return $this;
    }

    /**
     * Set LimitIterator Offset
     *
     * @param $offset
     *
     * @return $this
     */
    public function setOffset(int $offset = 0): self
    {
        $this->iterator_offset = $this->validateInteger($offset, 0, 'the offset must be a positive integer or 0');

        return $this;
    }

    /**
     * Set LimitIterator Count
     *
     * @param int $limit
     *
     * @return $this
     */
    public function setLimit(int $limit = -1): self
    {
        $this->iterator_limit = $this->validateInteger($limit, -1, 'the limit must an integer greater or equals to -1');

        return $this;
    }

    /**
     * Set an Iterator sorting callable function
     *
     * @param callable $callable
     *
     * @return $this
     */
    public function addSortBy(callable $callable): self
    {
        $this->iterator_sort_by[] = $callable;

        return $this;
    }

    /**
     * Set the Iterator filter method
     *
     * @param callable $callable
     *
     * @return $this
     */
    public function addFilter(callable $callable): self
    {
        $this->iterator_filters[] = $callable;

        return $this;
    }

    /**
     * Returns the CSV Iterator
     *
     * @return Iterator
     */
    protected function getQueryIterator(): Iterator
    {
        $normalizedCsv = function ($row) {
            return is_array($row) && $row != [null];
        };
        array_unshift($this->iterator_filters, $normalizedCsv);
        $iterator = $this->getIterator();
        $iterator = $this->applyBomStripping($iterator);
        $iterator = $this->applyIteratorFilter($iterator);
        $iterator = $this->applyIteratorSortBy($iterator);
        $iterator = $this->applyIteratorInterval($iterator);

        return $iterator;
    }

    /**
     * Remove the BOM sequence from the CSV
     *
     * @param Iterator $iterator
     *
     * @return Iterator
     */
    protected function applyBomStripping(Iterator $iterator): Iterator
    {
        if (!$this->strip_bom) {
            return $iterator;
        }

        if (!$this->isBomStrippable()) {
            $this->strip_bom = false;

            return $iterator;
        }

        $this->strip_bom = false;

        return $this->getStripBomIterator($iterator);
    }

    /**
     * Tell whether we can strip or not the leading BOM sequence
     *
     * @return bool
     */
    protected function isBomStrippable(): bool
    {
        return !empty($this->getInputBOM()) && $this->strip_bom;
    }

    /**
     * Return the Iterator without the BOM sequence
     *
     * @param Iterator $iterator
     *
     * @return Iterator
     */
    protected function getStripBomIterator(Iterator $iterator): Iterator
    {
        $bom_length = mb_strlen($this->getInputBOM());
        $enclosure = $this->getEnclosure();
        $strip_bom = function ($row, $index) use ($bom_length, $enclosure) {
            if (0 != $index) {
                return $row;
            }

            $row[0] = mb_substr($row[0], $bom_length);
            if (mb_substr($row[0], 0, 1) === $enclosure && mb_substr($row[0], -1, 1) === $enclosure) {
                $row[0] = mb_substr($row[0], 1, -1);
            }

            return $row;
        };

        return new MapIterator($iterator, $strip_bom);
    }

    /**
    * Filter the Iterator
    *
    * @param Iterator $iterator
    *
    * @return Iterator
    */
    protected function applyIteratorFilter(Iterator $iterator): Iterator
    {
        $reducer = function ($iterator, $callable) {
            return new CallbackFilterIterator($iterator, $callable);
        };
        $iterator = array_reduce($this->iterator_filters, $reducer, $iterator);
        $this->iterator_filters = [];

        return $iterator;
    }

    /**
    * Sort the Iterator
    *
    * @param Iterator $iterator
    *
    * @return Iterator
    */
    protected function applyIteratorSortBy(Iterator $iterator): Iterator
    {
        if (!$this->iterator_sort_by) {
            return $iterator;
        }

        $obj = new ArrayIterator(iterator_to_array($iterator));
        $obj->uasort(function ($row_a, $row_b) {
            $res = 0;
            foreach ($this->iterator_sort_by as $compare) {
                if (0 !== ($res = ($compare)($row_a, $row_b))) {
                    break;
                }
            }

            return $res;
        });
        $this->iterator_sort_by = [];

        return $obj;
    }

    /**
    * Sort the Iterator
    *
    * @param Iterator $iterator
    *
    * @return Iterator
    */
    protected function applyIteratorInterval(Iterator $iterator): Iterator
    {
        $offset = $this->iterator_offset;
        $limit = $this->iterator_limit;
        $this->iterator_limit = -1;
        $this->iterator_offset = 0;

        return new LimitIterator($iterator, $offset, $limit);
    }
}
