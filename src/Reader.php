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

use BadMethodCallException;
use CallbackFilterIterator;
use Iterator;
use IteratorAggregate;
use League\Csv\Exception\RuntimeException;
use LimitIterator;
use SplFileObject;

/**
 * A class to manage records selection from a CSV document
 *
 * @package League.csv
 * @since  3.0.0
 *
 * @method array fetchAll() Returns a sequential array of all CSV records
 * @method array fetchOne(int $offset = 0) Returns a single record from the CSV
 * @method Generator fetchColumn(string|int $column_index) Returns the next value from a single CSV record field
 * @method Generator fetchPairs(string|int $offset_index, string|int $value_index) Fetches the next key-value pairs from the CSV document
 */
class Reader extends AbstractCsv implements IteratorAggregate
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
     * CSV Document Header record
     *
     * @var string[]
     */
    protected $header = [];

    /**
     * Tell whether the header needs to be re-generated
     *
     * @var bool
     */
    protected $is_header_loaded = false;

    /**
     * The value to pad if the record is less than header size.
     *
     * @var mixed
     */
    protected $record_padding_value;

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
     * Returns wether the selected header can be combine to each record
     *
     * A valid header must be empty or contains unique string field names
     *
     * @return bool
     */
    public function supportsHeaderAsRecordKeys(): bool
    {
        $header = $this->getHeader();

        return empty($header) || $header === array_unique(array_filter($header, 'is_string'));
    }

    /**
     * Returns the CSV record header
     *
     * The returned header is represented as an array of string values
     *
     * @return string[]
     */
    public function getHeader(): array
    {
        if ($this->is_header_loaded) {
            return $this->header;
        }

        $this->is_header_loaded = true;
        $this->header = [];
        if (null !== $this->header_offset) {
            $this->header = $this->setHeader($this->header_offset);
        }

        return $this->header;
    }

    /**
     * Determine the CSV record header
     *
     * @param int $offset
     *
     * @throws RuntimeException If the header offset is an integer
     *                          and the corresponding record is missing
     *                          or is an empty array
     *
     * @return string[]
     */
    protected function setHeader(int $offset): array
    {
        $this->document->setFlags(SplFileObject::READ_CSV | SplFileObject::READ_AHEAD | SplFileObject::SKIP_EMPTY);
        $this->document->setCsvControl($this->delimiter, $this->enclosure, $this->escape);
        $this->document->seek($offset);
        $header = $this->document->current();
        if (empty($header)) {
            throw new RuntimeException(sprintf('The header record does not exist or is empty at offset: `%s`', $offset));
        }

        if (0 === $offset) {
            return $this->removeBOM($header, mb_strlen($this->getInputBOM()), $this->enclosure);
        }

        return $header;
    }

    /**
     * Strip the BOM sequence from a record
     *
     * @param string[] $record
     * @param int      $bom_length
     * @param string   $enclosure
     *
     * @return string[]
     */
    protected function removeBOM(array $record, int $bom_length, string $enclosure): array
    {
        if (0 == $bom_length) {
            return $record;
        }

        $record[0] = mb_substr($record[0], $bom_length);
        if ($enclosure == mb_substr($record[0], 0, 1) && $enclosure == mb_substr($record[0], -1, 1)) {
            $record[0] = mb_substr($record[0], 1, -1);
        }

        return $record;
    }

    /**
     * Returns the record padding value
     *
     * @return mixed
     */
    public function getRecordPaddingValue()
    {
        return $this->record_padding_value;
    }

    /**
     * Returns a CSV records collection
     *
     * @param Statement $stmt
     *
     * @return ResultSet
     */
    public function select(Statement $stmt): ResultSet
    {
        return $stmt->process($this);
    }

    /**
     * @inheritdoc
     */
    public function __call($method, array $arguments)
    {
        $whitelisted = ['fetchColumn' => 1, 'fetchPairs' => 1, 'fetchOne' => 1, 'fetchAll' => 1];
        if (isset($whitelisted[$method])) {
            return (new ResultSet($this->getRecords(), $this->getHeader()))
                ->$method(...$arguments)
            ;
        }

        throw new BadMethodCallException(sprintf('Reader::%s does not exists', $method));
    }

    /**
     * Detect Delimiters occurences in the CSV
     *
     * Returns a associative array where each key represents
     * a valid delimiter and each value the number of occurences
     *
     * @param string[] $delimiters the delimiters to consider
     * @param int      $nb_records Detection is made using $nb_records of the CSV
     *
     * @return array
     */
    public function fetchDelimitersOccurrence(array $delimiters, int $nb_records = 1): array
    {
        $filter = function ($value): bool {
            return 1 == strlen($value);
        };

        $nb_records = $this->filterMinRange($nb_records, 1, 'The number of records to consider must be a valid positive integer');
        $delimiters = array_unique(array_filter($delimiters, $filter));
        $reducer = function (array $res, string $delimiter) use ($nb_records): array {
            $res[$delimiter] = $this->getCellCount($delimiter, $nb_records);

            return $res;
        };

        $res = array_reduce($delimiters, $reducer, []);
        arsort($res, SORT_NUMERIC);

        return $res;
    }

    /**
     * Returns the cell count for a specified delimiter
     * and a specified number of records
     *
     * @param string $delimiter  CSV delimiter
     * @param int    $nb_records CSV records to consider
     *
     * @return int
     */
    protected function getCellCount(string $delimiter, int $nb_records): int
    {
        $filter = function ($record): bool {
            return is_array($record) && count($record) > 1;
        };

        $this->document->setFlags(SplFileObject::READ_CSV | SplFileObject::READ_AHEAD | SplFileObject::SKIP_EMPTY);
        $this->document->setCsvControl($delimiter, $this->enclosure, $this->escape);
        $iterator = new CallbackFilterIterator(new LimitIterator($this->document, 0, $nb_records), $filter);

        return count(iterator_to_array($iterator, false), COUNT_RECURSIVE);
    }

    /**
     * @inheritdoc
     */
    public function getIterator(): Iterator
    {
        return $this->getRecords();
    }

    /**
     * Returns the CSV records in an iterator object.
     *
     * Each CSV record is represented as a simple array of string or null values.
     *
     * If the CSV document has a header record then each record is combined
     * to each header record and the header record is removed from the iterator.
     *
     * If the CSV document is inconsistent. Missing record fields are
     * filled with null values while extra record fields are strip from
     * the returned object.
     *
     * @throws RuntimeException If the header contains non unique column name
     *
     * @return Iterator
     */
    public function getRecords(): Iterator
    {
        if (!$this->supportsHeaderAsRecordKeys()) {
            throw new RuntimeException('The header record must be empty or a flat array with unique string values');
        }

        $normalized = function ($record): bool {
            return is_array($record) && $record != [null];
        };
        $bom = $this->getInputBOM();
        $this->document->setFlags(SplFileObject::READ_CSV | SplFileObject::READ_AHEAD | SplFileObject::SKIP_EMPTY);
        $this->document->setCsvControl($this->delimiter, $this->enclosure, $this->escape);

        return $this->combineHeader($this->stripBOM(new CallbackFilterIterator($this->document, $normalized), $bom));
    }

    /**
     * Add the CSV header if present and valid
     *
     * @param Iterator $iterator
     *
     * @return Iterator
     */
    protected function combineHeader(Iterator $iterator): Iterator
    {
        if (null === $this->header_offset) {
            return $iterator;
        }

        $iterator = new CallbackFilterIterator($iterator, function (array $record, int $offset): bool {
            return $offset != $this->header_offset;
        });

        $header = $this->getHeader();
        $field_count = count($header);
        $mapper = function (array $record) use ($header, $field_count): array {
            if (count($record) != $field_count) {
                $record = array_slice(array_pad($record, $field_count, $this->record_padding_value), 0, $field_count);
            }

            return array_combine($header, $record);
        };

        return new MapIterator($iterator, $mapper);
    }

    /**
     * Strip the BOM sequence if present
     *
     * @param Iterator $iterator
     * @param string   $bom
     *
     * @return Iterator
     */
    protected function stripBOM(Iterator $iterator, string $bom): Iterator
    {
        if ('' === $bom) {
            return $iterator;
        }

        $bom_length = mb_strlen($bom);
        $mapper = function (array $record, int $index) use ($bom_length): array {
            if (0 != $index) {
                return $record;
            }

            return $this->removeBOM($record, $bom_length, $this->enclosure);
        };

        return new MapIterator($iterator, $mapper);
    }


    /**
     * Selects the record to be used as the CSV header
     *
     * Because of the header is represented as an array, to be valid
     * a header MUST contain only unique string value.
     *
     * @param int|null $offset the header record offset
     *
     * @return static
     */
    public function setHeaderOffset($offset): self
    {
        if (null !== $offset) {
            $offset = $this->filterMinRange($offset, 0, 'The header offset index must be a positive integer or 0');
        }

        if ($offset !== $this->header_offset) {
            $this->header_offset = $offset;
            $this->resetProperties();
        }

        return $this;
    }

    /**
     * Set the record padding value
     *
     * @param mixed $record_padding_value
     *
     * @return static
     */
    public function setRecordPaddingValue($record_padding_value): self
    {
        $this->record_padding_value = $record_padding_value;

        return $this;
    }

    /**
     * @inheritdoc
     */
    protected function resetProperties()
    {
        return $this->is_header_loaded = false;
    }
}
