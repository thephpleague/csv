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
namespace League\Csv;

use ArrayIterator;
use CallbackFilterIterator;
use Countable;
use DOMDocument;
use Generator;
use InvalidArgumentException;
use Iterator;
use IteratorAggregate;
use JsonSerializable;
use League\Csv\Config\Validator;
use LimitIterator;

/**
 * A class to extract and convert data from a CSV
 *
 * @package League.csv
 * @since  9.0.0
 *
 */
class RecordSet implements Countable, IteratorAggregate, JsonSerializable
{
    use Validator;

    /**
     * @var array
     */
    protected $header;

    /**
     * @var array
     */
    protected $flip_header;

    /**
     * @var Iterator
     */
    protected $iterator;

    /**
     * New Instance
     *
     * @param Reader    $csv
     * @param Statement $stmt
     */
    public function __construct(Reader $csv, Statement $stmt)
    {
        $filters = $stmt->getFilter();
        if (null !== ($header_offset = $csv->getHeaderOffset())) {
            array_unshift($filters, function (array $record, $index) use ($header_offset) {
                return $index !== $header_offset;
            });
        }

        $iterator = $this->prepare($csv);
        $iterator = $this->filter($iterator, $filters);
        $iterator = $this->sort($iterator, $stmt->getSortBy());

        $this->iterator = new LimitIterator($iterator, $stmt->getOffset(), $stmt->getLimit());
    }

    /**
     * Prepare the Reader for manipulation
     *
     * - remove the BOM sequence if present
     * - attach the header to the records if present
     * - convert the CSV to UTF-8 if needed
     *
     * @param Reader $csv
     *
     * @throws InvalidRowException if the column is inconsistent
     *
     * @return Iterator
     */
    protected function prepare(Reader $csv)
    {
        $this->header = $csv->getHeader();
        $this->flip_header = array_flip($this->header);
        $input_encoding = $csv->getInputEncoding();
        $use_converter = $this->useInternalConverter($csv);
        $iterator = $this->removeBOM($csv);
        if (!empty($this->header)) {
            $header_column_count = count($this->header);
            $combine_array = function (array $record) use ($header_column_count) {
                if ($header_column_count != count($record)) {
                    throw new InvalidRowException('csv_consistency', $record, 'record and header column count differ');
                }

                return array_combine($this->header, $record);
            };
            $iterator = new MapIterator($iterator, $combine_array);
        }

        return $this->convert($iterator, $input_encoding, $use_converter);
    }

    /**
     * Remove the BOM sequence from the CSV
     *
     * @param Reader $csv
     *
     * @return Iterator
     */
    protected function removeBOM(Reader $csv)
    {
        $bom = $csv->getInputBOM();
        if ('' === $bom) {
            return $csv->getIterator();
        }

        $enclosure = $csv->getEnclosure();
        $formatter = function (array $record, $index) use ($bom, $enclosure) {
            if (0 != $index) {
                return $record;
            }

            return $this->stripBOM($record, $bom, $enclosure);
        };

        return new MapIterator($csv->getIterator(), $formatter);
    }

    /**
     * Convert the iterator to UTF-8 if needed
     *
     * @param Iterator $iterator
     * @param string   $input_encoding
     * @param bool     $use_converter
     *
     * @return Iterator
     */
    protected function convert(Iterator $iterator, $input_encoding, $use_converter)
    {
        if (!$use_converter) {
            return $iterator;
        }

        $converter = function ($record) use ($input_encoding) {
            return $this->convertRecordToUtf8($record, $input_encoding);
        };

        return new MapIterator($iterator, $converter);
    }

    /**
    * Filter the Iterator
    *
    * @param Iterator $iterator
    * @param callable[] $filters
    *
    * @return Iterator
    */
    protected function filter(Iterator $iterator, array $filters)
    {
        $reducer = function ($iterator, $callable) {
            return new CallbackFilterIterator($iterator, $callable);
        };

        array_unshift($filters, function ($row) {
            return is_array($row) && $row != [null];
        });

        return array_reduce($filters, $reducer, $iterator);
    }

    /**
    * Sort the Iterator
    *
    * @param Iterator $iterator
    * @param callable[] $sort
    *
    * @return Iterator
    */
    protected function sort(Iterator $iterator, array $sort)
    {
        if (empty($sort)) {
            return $iterator;
        }

        $obj = new ArrayIterator(iterator_to_array($iterator));
        $obj->uasort(function ($record_a, $record_b) use ($sort) {
            $res = 0;
            foreach ($sort as $compare) {
                if (0 !== ($res = call_user_func($compare, $record_a, $record_b))) {
                    break;
                }
            }

            return $res;
        });

        return $obj;
    }

    /**
     * @inheritdoc
     */
    public function __destruct()
    {
        $this->iterator = null;
    }

    /**
     * @inheritdoc
     */
    public function getIterator()
    {
        return $this->iterator;
    }

    /**
     * Returns the object header
     *
     * @return string[]
     */
    public function getHeader()
    {
        return $this->header;
    }

    /**
     * Returns a HTML table representation of the CSV Table
     *
     * @param string $class_attr optional classname
     *
     * @return string
     */
    public function toHTML($class_attr = 'table-csv-data')
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
     * @return DOMDocument
     */
    public function toXML($root_name = 'csv', $row_name = 'row', $cell_name = 'cell')
    {
        $this->row_name = $this->validateString($row_name);
        $this->cell_name = $this->validateString($cell_name);
        $doc = new DOMDocument('1.0', 'UTF-8');
        $root = $doc->createElement($this->validateString($root_name));
        if (!empty($this->header)) {
            $root->appendChild($this->convertRecordToDOMNode($this->header, $doc));
        }

        foreach ($this->iterator as $row) {
            $root->appendChild($this->convertRecordToDOMNode($row, $doc));
        }
        $doc->appendChild($root);

        return $doc;
    }

    /**
     * @inheritdoc
     */
    public function count()
    {
        return iterator_count($this->iterator);
    }

    /**
     * @inheritdoc
     */
    public function jsonSerialize()
    {
        return $this->fetchAll();
    }

    /**
     * Returns a sequential array of all founded RecordSet
     *
     * @return array
     */
    public function fetchAll()
    {
        return iterator_to_array($this->iterator, false);
    }

    /**
     * Returns a single record from the recordSet
     *
     * @param int $offset the record offset relative to the RecordSet
     *
     * @return array
     */
    public function fetchOne($offset = 0)
    {
        $offset = $this->validateInteger($offset, 0, 'the submitted offset is invalid');
        $it = new LimitIterator($this->iterator, $offset, 1);
        $it->rewind();

        return (array) $it->current();
    }

    /**
     * Returns the next value from a specific record column
     *
     * By default if no column index is provided the first column of the founded RecordSet is returned
     *
     * @param string|int $column_index CSV column index or header field name
     *
     * @return Iterator
     */
    public function fetchColumn($column_index = 0)
    {
        $column_index = $this->filterFieldName($column_index, 'the column index value is invalid');
        $filter = function (array $record) use ($column_index) {
            return isset($record[$column_index]);
        };
        $select = function (array $record) use ($column_index) {
            return $record[$column_index];
        };

        return new MapIterator(new CallbackFilterIterator($this->iterator, $filter), $select);
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
    protected function filterFieldName($field, $error_message)
    {
        if (false !== array_search($field, $this->header, true)) {
            return $field;
        }

        $index = $this->validateInteger($field, 0, $error_message);
        if (empty($this->header)) {
            return $index;
        }

        if (false !== ($index = array_search($index, $this->flip_header, true))) {
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
     * @param string|int $offset_index The field index or name to serve as offset
     * @param string|int $value_index  The field index or name to serve as value
     *
     * @return Generator
     */
    public function fetchPairs($offset_index = 0, $value_index = 1)
    {
        $offset_index = $this->filterFieldName($offset_index, 'the offset column index is invalid');
        $value_index = $this->filterFieldName($value_index, 'the value column index is invalid');
        $filter = function (array $record) use ($offset_index) {
            return isset($record[$offset_index]);
        };
        $select = function (array $record) use ($offset_index, $value_index) {
            return [$record[$offset_index], isset($record[$value_index]) ? $record[$value_index] : null];
        };

        $iterator = new MapIterator(new CallbackFilterIterator($this->iterator, $filter), $select);
        foreach ($iterator as $row) {
            yield $row[0] => $row[1];
        }
    }
}
