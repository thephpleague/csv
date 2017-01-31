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
use Generator;
use InvalidArgumentException;
use Iterator;
use IteratorAggregate;
use JsonSerializable;
use LimitIterator;
use SplFileObject;

/**
 *  A class to manage extracting and filtering a CSV
 *
 * @package League.csv
 * @since  3.0.0
 *
 */
class Reader extends AbstractCsv implements JsonSerializable, IteratorAggregate
{
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
     * @inheritdoc
     */
    protected $stream_filter_mode = STREAM_FILTER_READ;

    /**
     * Selected Header for the query
     *
     * @var array
     */
    protected $selected_header = [];

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
    public function toXML(string $root_name = 'csv', string $row_name = 'row', string $cell_name = 'cell'): DomDocument
    {
        $doc = new DomDocument('1.0', 'UTF-8');
        $root = $doc->createElement($root_name);
        foreach ($this->convertToUtf8($this->getIterator()) as $row) {
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
     * @inheritdoc
     */
    public function jsonSerialize()
    {
        return iterator_to_array($this->convertToUtf8($this->getIterator()), false);
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
        $this->iterator_offset = $this->filterInteger($offset, 0, 'the offset must be a positive integer or 0');

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
        $this->iterator_limit = $this->filterInteger($limit, -1, 'the limit must an integer greater or equals to -1');

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
     * Returns the inner CSV Document Iterator object
     *
     * @return Iterator
     */
    public function getIterator()
    {
        $iterator = $this->getCsvDocument();
        $iterator->setCsvControl($this->delimiter, $this->enclosure, $this->escape);
        $iterator->setFlags(SplFileObject::READ_CSV | SplFileObject::READ_AHEAD | SplFileObject::SKIP_EMPTY);
        $iterator = $this->applyBomStripping($iterator);
        $iterator = $this->applyHeader($iterator);
        $iterator = $this->applyFilter($iterator);
        $iterator = $this->applySortBy($iterator);

        return $this->applyIteratorInterval($iterator);
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
        $bom = $this->getInputBOM();
        if ('' == $bom) {
            return $iterator;
        }

        $bom_length = mb_strlen($bom);
        $enclosure = $this->getEnclosure();
        $strip_bom = function ($row, $index) use ($bom_length, $enclosure) {
            if (0 != $index || !is_array($row)) {
                return $row;
            }

            return $this->removeBom($row, $bom_length, $enclosure);
        };

        return new MapIterator($iterator, $strip_bom);
    }

    /**
     * Add the CSV header if present
     *
     * @param Iterator $iterator
     *
     * @return Iterator
     */
    public function applyHeader(Iterator $iterator): Iterator
    {
        $header = $this->getHeader();
        if (empty($header)) {
            return $iterator;
        }

        $header_count = count($header);
        $combine = function (array $row) use ($header, $header_count) {
            if ($header_count != count($row)) {
                $row = array_slice(array_pad($row, $header_count, null), 0, $header_count);
            }

            return array_combine($header, $row);
        };

        return new MapIterator($iterator, $combine);
    }

    /**
    * Filter the Iterator
    *
    * @param Iterator $iterator
    *
    * @return Iterator
    */
    protected function applyFilter(Iterator $iterator): Iterator
    {
        $header_offset = $this->getHeaderOffset();
        if (null !== $header_offset) {
            $strip_header = function ($row, $index) use ($header_offset) {
                return $index !== $header_offset;
            };
            array_unshift($this->iterator_filters, $strip_header);
        }

        $normalized_csv = function ($row) {
            return is_array($row) && $row != [null];
        };
        array_unshift($this->iterator_filters, $normalized_csv);

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
    protected function applySortBy(Iterator $iterator): Iterator
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

    /**
     * Returns a sequential array of all CSV lines
     *
     * The callable function will be applied to each Iterator item
     *
     * @return array
     */
    public function fetchAll(): array
    {
        return iterator_to_array($this->getIterator(), false);
    }

    /**
     * Returns a single row from the CSV
     *
     * By default if no offset is provided the first row of the CSV is selected
     *
     * @param int $offset the CSV row offset
     *
     * @return array
     */
    public function fetchOne(int $offset = 0): array
    {
        $this->setOffset($offset);
        $this->setLimit(1);
        $iterator = $this->getIterator();
        $iterator->rewind();

        return (array) $iterator->current();
    }

    /**
     * Returns the next value from a single CSV column
     *
     * The callable function will be applied to each value to be return
     *
     * By default if no column index is provided the first column of the CSV is selected
     *
     * @param string|int $column_index CSV column index
     *
     * @return Iterator
     */
    public function fetchColumn($column_index = 0): Iterator
    {
        $column_index = $this->getFieldIndex($column_index, 'the column index value is invalid');
        $filter = function (array $row) use ($column_index) {
            return isset($row[$column_index]);
        };

        $select = function ($row) use ($column_index) {
            return $row[$column_index];
        };

        $this->addFilter($filter);

        return new MapIterator($this->getIterator(), $select);
    }

    /**
     * Filter a field name against the CSV header if any
     *
     * @param string|int $field         the field name or the field index
     * @param string     $error_message the associated error message
     *
     * @throws InvalidArgumentException if the field is invalid
     *
     * @return string|int
     */
    protected function getFieldIndex($field, $error_message)
    {
        if (false !== array_search($field, $this->header, true)) {
            return $field;
        }

        $index = $this->filterInteger($field, 0, $error_message);
        if (empty($this->header)) {
            return $index;
        }

        if (false !== ($index = array_search($index, array_flip($this->header), true))) {
            return $index;
        }

        throw new InvalidArgumentException($error_message);
    }

    /**
     * Fetches the next key-value pairs from a result set (first
     * column is the key, second column is the value).
     *
     * By default if no column index is provided:
     * - the first CSV column is used to provide the keys
     * - the second CSV column is used to provide the value
     *
     * @param string|int $offset_index The column index to serve as offset
     * @param string|int $value_index  The column index to serve as value
     *
     * @return Generator
     */
    public function fetchPairs($offset_index = 0, $value_index = 1): Generator
    {
        $offset = $this->getFieldIndex($offset_index, 'the offset index value is invalid');
        $value = $this->getFieldIndex($value_index, 'the value index value is invalid');
        $filter = function ($row) use ($offset) {
            return isset($row[$offset]);
        };

        $select = function ($row) use ($offset, $value) {
            return [$row[$offset], isset($row[$value]) ? $row[$value] : null];
        };

        $this->addFilter($filter);
        foreach (new MapIterator($this->getIterator(), $select) as $row) {
            yield $row[0] => $row[1];
        }
    }
}
