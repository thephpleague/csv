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
    use CsvControlTrait;

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
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, static::validateString($str));

        return new static(new StreamIterator($stream));
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
        if (empty($this->iterator_sort_by)) {
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
