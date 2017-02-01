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

use Countable;
use DomDocument;
use Generator;
use Iterator;
use IteratorAggregate;
use JsonSerializable;

/**
 *  A class to manage extracting and filtering a CSV
 *
 * @package League.csv
 * @since  3.0.0
 *
 */
class Reader extends AbstractCsv implements JsonSerializable, Countable, IteratorAggregate
{
    /**
     * @inheritdoc
     */
    protected $stream_filter_mode = STREAM_FILTER_READ;

    /**
     * Returns a collection of selected records
     *
     * @param Statement|null $stmt
     *
     * @return RecordSet
     */
    public function select(Statement $stmt = null): RecordSet
    {
        $stmt = $stmt ?: new Statement();

        return $stmt->process($this);
    }

    /**
     * @inheritdoc
     */
    public function count(): int
    {
        return $this->select()->count();
    }

    /**
     * @inheritdoc
     */
    public function getIterator(): Iterator
    {
        return $this->select()->getIterator();
    }

    /**
     * @inheritdoc
     */
    public function jsonSerialize()
    {
        return $this->select()->jsonSerialize();
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
        return $this->select()->toHTML($class_attr);
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
        return $this->select()->toXML($root_name, $row_name, $cell_name);
    }

    /**
     * Returns a sequential array of all CSV lines
     *
     * @return array
     */
    public function fetchAll(): array
    {
        return $this->select()->fetchAll();
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
        return $this->select()->fetchOne($offset);
    }

    /**
     * Returns the next value from a single CSV column
     *
     * By default if no column index is provided the first column of the CSV is selected
     *
     * @param string|int $column_index CSV column index
     *
     * @return Iterator
     */
    public function fetchColumn($column_index = 0): Iterator
    {
        return $this->select()->fetchColumn($column_index);
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
    public function fetchPairs($offset_index = 0, $value_index = 1)
    {
        return $this->select()->fetchPairs($offset_index, $value_index);
    }
}
