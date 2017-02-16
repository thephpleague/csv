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

use CallbackFilterIterator;
use Countable;
use DOMDocument;
use DOMElement;
use Generator;
use Iterator;
use IteratorAggregate;
use JsonSerializable;
use LimitIterator;

/**
 * A class to manage extracting and filtering a CSV
 *
 * @package League.csv
 * @since   9.0.0
 * @author  Ignace Nyamagana Butera <nyamsprod@gmail.com>
 *
 */
class RecordSet implements JsonSerializable, IteratorAggregate, Countable
{
    use ValidatorTrait;

    /**
     * The CSV iterator result
     *
     * @var Iterator
     */
    protected $iterator;

    /**
     * The CSV header
     *
     * @var array
     */
    protected $column_names = [];

    /**
     * Charset Encoding for the CSV
     *
     * This information is used when converting the CSV to XML or JSON
     *
     * @var string
     */
    protected $conversion_input_encoding = 'UTF-8';

    /**
     * New instance
     *
     * @param Iterator $iterator     a CSV iterator
     * @param array    $column_names the CSV header
     */
    public function __construct(Iterator $iterator, array $column_names = [])
    {
        $this->iterator = $iterator;
        $this->column_names = $column_names;
    }

    /**
     * @inheritdoc
     */
    public function __destruct()
    {
        $this->iterator = null;
    }

    /**
     * Returns the field names associate with the RecordSet
     *
     * @return string[]
     */
    public function getColumnNames(): array
    {
        return $this->column_names;
    }

    /**
     * Returns a specific field names according to its offset
     *
     * If no field name is found or associated to the submitted
     * offset an empty string is returned
     *
     * @param int $offset
     *
     * @return string
     */
    public function getColumnName(int $offset): string
    {
        return $this->column_names[$offset] ?? '';
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
        $doc = $this->toXML('table', 'tr', 'td', 'title');
        $doc->documentElement->setAttribute('class', $class_attr);

        return $doc->saveHTML($doc->documentElement);
    }

    /**
     * Transforms a CSV into a XML
     *
     * @param string $root_name        XML root node name
     * @param string $row_name         XML row node name
     * @param string $cell_name        XML cell node name
     * @param string $column_attr_name XML column attribute name
     *
     * @return DOMDocument
     */
    public function toXML(
        string $root_name = 'csv',
        string $row_name = 'row',
        string $cell_name = 'cell',
        string $column_attr_name = 'name'
    ): DOMDocument {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $root = $doc->createElement($root_name);
        foreach ($this->convertToUtf8($this->iterator) as $row) {
            $root->appendChild($this->toDOMNode($doc, $row, $row_name, $cell_name, $column_attr_name));
        }
        $doc->appendChild($root);

        return $doc;
    }

    /**
     * convert a Record into a DOMNode
     *
     * @param DOMDocument $doc              The DOMDocument
     * @param array       $row              The CSV record
     * @param string      $row_name         XML row node name
     * @param string      $cell_name        XML cell node name
     * @param string      $column_attr_name XML header attribute name
     *
     * @return DOMElement
     */
    protected function toDOMNode(
        DOMDocument $doc,
        array $row,
        string $row_name,
        string $cell_name,
        string $column_attr_name
    ): DOMElement {
        $rowElement = $doc->createElement($row_name);
        foreach ($row as $name => $value) {
            $content = $doc->createTextNode($value);
            $cell = $doc->createElement($cell_name);
            if (!empty($this->column_names)) {
                $cell->setAttribute($column_attr_name, $name);
            }
            $cell->appendChild($content);
            $rowElement->appendChild($cell);
        }

        return $rowElement;
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
        if (stripos($this->conversion_input_encoding, 'UTF-8') !== false) {
            return $iterator;
        }

        $convert_cell = function ($value) {
            return mb_convert_encoding((string) $value, 'UTF-8', $this->conversion_input_encoding);
        };

        $convert_row = function (array $row) use ($convert_cell) {
            $res = [];
            foreach ($row as $key => $value) {
                $res[$convert_cell($key)] = $convert_cell($value);
            }

            return $res;
        };

        return new MapIterator($iterator, $convert_row);
    }

    /**
     * @inheritdoc
     */
    public function getIterator(): Iterator
    {
        return $this->iterator;
    }

    /**
     * @inheritdoc
     */
    public function count(): int
    {
        return iterator_count($this->iterator);
    }

    /**
     * @inheritdoc
     */
    public function jsonSerialize()
    {
        return iterator_to_array($this->convertToUtf8($this->iterator), false);
    }

    /**
     * Returns a sequential array of all CSV lines
     *
     * @return array
     */
    public function fetchAll(): array
    {
        return iterator_to_array($this->iterator);
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
        $offset = $this->filterInteger($offset, 0, 'the submitted offset is invalid');
        $it = new LimitIterator($this->iterator, $offset, 1);
        $it->rewind();

        return (array) $it->current();
    }

    /**
     * Returns the next value from a single CSV column
     *
     * By default if no column index is provided the first column of the CSV is selected
     *
     * @param string|int $index CSV column index
     *
     * @return Iterator
     */
    public function fetchColumn($index = 0): Iterator
    {
        $offset = $this->getColumnIndex($index, 'the column index value is invalid');
        $filter = function (array $row) use ($offset) {
            return isset($row[$offset]);
        };

        $select = function ($row) use ($offset) {
            return $row[$offset];
        };

        return new MapIterator(new CallbackFilterIterator($this->iterator, $filter), $select);
    }

    /**
     * Filter a column name against the CSV header if any
     *
     * @param string|int $field         the field name or the field index
     * @param string     $error_message the associated error message
     *
     * @throws Exception if the field is invalid
     *
     * @return string|int
     */
    protected function getColumnIndex($field, string $error_message)
    {
        if (false !== array_search($field, $this->column_names, true) || is_string($field)) {
            return $field;
        }

        $index = $this->filterInteger($field, 0, $error_message);
        if (empty($this->column_names)) {
            return $index;
        }

        if (false !== ($index = array_search($index, array_flip($this->column_names), true))) {
            return $index;
        }

        throw new Exception($error_message);
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
        $offset = $this->getColumnIndex($offset_index, 'the offset index value is invalid');
        $value = $this->getColumnIndex($value_index, 'the value index value is invalid');

        $filter = function ($row) use ($offset) {
            return isset($row[$offset]);
        };

        $select = function ($row) use ($offset, $value) {
            return [$row[$offset], $row[$value] ?? null];
        };

        $it = new MapIterator(new CallbackFilterIterator($this->iterator, $filter), $select);

        foreach ($it as $row) {
            yield $row[0] => $row[1];
        }
    }

    /**
     * Sets the CSV encoding charset
     *
     * @param string $str
     *
     * @return static
     */
    public function setConversionInputEncoding(string $str): self
    {
        $str = str_replace('_', '-', $str);
        $str = filter_var($str, FILTER_SANITIZE_STRING, ['flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH]);
        $str = trim($str);
        if ('' === $str) {
            throw new Exception('you should use a valid charset');
        }
        $this->conversion_input_encoding = strtoupper($str);

        return $this;
    }
}
