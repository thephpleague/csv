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
use DomDocument;
use Generator;
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
class Reader extends AbstractCsv implements JsonSerializable, Countable, IteratorAggregate
{
    /**
     * @inheritdoc
     */
    protected $stream_filter_mode = STREAM_FILTER_READ;

    /**
     * CSV Document header offset
     *
     * @var int|null
     */
    protected $header_offset;

    /**
     * Returns the record offset used as header
     *
     * If no CSV record is used this method MUST return null
     *
     * @return int|null
     */
    public function getHeaderOffset()
    {
        return $this->header_offset;
    }

    /**
     * Selects the record to be used as the CSV header
     *
     * Because of the header is represented as an array, to be valid
     * a header MUST contain only unique string value.
     *
     * @param int|null $offset the header row offset
     *
     * @return static
     */
    public function setHeaderOffset($offset): self
    {
        $this->header_offset = null;
        if (null !== $offset) {
            $this->header_offset = $this->filterInteger(
                $offset,
                0,
                'the header offset index must be a positive integer or 0'
            );
        }

        return $this;
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
        $nb_rows = $this->filterInteger($nb_rows, 1, 'The number of rows to consider must be a valid positive integer');
        $filter_row = function ($row) {
            return is_array($row) && count($row) > 1;
        };
        $delimiters = array_unique(array_filter($delimiters, function ($value) {
            return 1 == strlen($value);
        }));
        $this->document->setFlags(SplFileObject::READ_CSV);
        $res = [];
        foreach ($delimiters as $delim) {
            $this->document->setCsvControl($delim, $this->enclosure, $this->escape);
            $iterator = new CallbackFilterIterator(new LimitIterator($this->document, 0, $nb_rows), $filter_row);
            $res[$delim] = count(iterator_to_array($iterator, false), COUNT_RECURSIVE);
        }
        arsort($res, SORT_NUMERIC);

        return $res;
    }

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
        return iterator_count($this->getIterator());
    }

    /**
     * @inheritdoc
     */
    public function getIterator(): Iterator
    {
        $this->document->setFlags(SplFileObject::READ_CSV | SplFileObject::READ_AHEAD | SplFileObject::SKIP_EMPTY);
        $this->document->setCsvControl($this->delimiter, $this->enclosure, $this->escape);

        return $this->document;
    }

    /**
     * @inheritdoc
     */
    public function jsonSerialize()
    {
        return $this->select()->jsonSerialize();
    }

    /**
     * Returns the column header associate with the RecordSet
     *
     * @throws Exception If no header is found
     *
     * @return string[]
     */
    public function getHeader()
    {
        if (null === $this->header_offset) {
            return [];
        }

        $csv = $this->getIterator();
        $csv->seek($this->header_offset);
        $header = $csv->current();
        if (empty($header)) {
            throw new Exception('The header record specified by `Reader::setHeaderOffset` does not exist or is empty');
        }

        if (0 === $this->header_offset) {
            $header = $this->removeBOM($header, mb_strlen($this->getInputBOM()), $this->enclosure);
        }

        return $header;
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
