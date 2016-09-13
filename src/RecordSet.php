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
     * @param Iterator $iterator
     * @param array    $header
     */
    public function __construct(Iterator $iterator, array $header)
    {
        $this->iterator = $iterator;
        $this->header = $header;
        $this->flip_header = array_flip($header);
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
        return iterator_count($this);
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
        return iterator_to_array($this, false);
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
        $filter = function ($row) use ($column_index) {
            return isset($row[$column_index]);
        };
        $select = function ($row) use ($column_index) {
            return $row[$column_index];
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
        $filter = function ($row) use ($offset_index) {
            return isset($row[$offset_index]);
        };
        $select = function ($row) use ($offset_index, $value_index) {
            return [$row[$offset_index], isset($row[$value_index]) ? $row[$value_index] : null];
        };

        $iterator = new MapIterator(new CallbackFilterIterator($this->iterator, $filter), $select);
        foreach ($iterator as $row) {
            yield $row[0] => $row[1];
        }
    }
}
