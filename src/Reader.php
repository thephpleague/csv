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
use Iterator;
use IteratorAggregate;
use League\Csv\Exception\RuntimeException;
use LimitIterator;
use SplFileObject;

/**
 *  A class to manage extracting and filtering a CSV
 *
 * @package League.csv
 * @since  3.0.0
 *
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
     * Returns a collection of selected records
     *
     * @param Statement $stmt
     *
     * @return RecordSet
     */
    public function select(Statement $stmt): RecordSet
    {
        return $stmt->process($this);
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

        $nb_records = $this->filterInteger($nb_records, 1, __METHOD__.': the number of rows to consider must be a valid positive integer');
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
    protected function getCellCount(string $delimiter, int $nb_records)
    {
        $filter = function ($row): bool {
            return is_array($row) && count($row) > 1;
        };

        $this->document->setFlags(SplFileObject::READ_CSV);
        $this->document->setCsvControl($delimiter, $this->enclosure, $this->escape);
        $iterator = new CallbackFilterIterator(new LimitIterator($this->document, 0, $nb_records), $filter);

        return count(iterator_to_array($iterator, false), COUNT_RECURSIVE);
    }

    /**
     * @inheritdoc
     */
    public function getIterator(): Iterator
    {
        $bom = $this->getInputBOM();
        $header = $this->getHeader();
        $this->document->setFlags(SplFileObject::READ_CSV | SplFileObject::READ_AHEAD | SplFileObject::SKIP_EMPTY);
        $this->document->setCsvControl($this->delimiter, $this->enclosure, $this->escape);
        $normalized = function ($row): bool {
            return is_array($row) && $row != [null];
        };

        $iterator = $this->combineHeader(new CallbackFilterIterator($this->document, $normalized), $header);

        return $this->stripBOM($iterator, $bom);
    }

    /**
     * Returns the column header associate with the RecordSet
     *
     * @throws RuntimeException If no header is found
     *
     * @return string[]
     */
    public function getHeader(): array
    {
        if ($this->is_header_loaded) {
            return $this->header;
        }

        $this->is_header_loaded = true;
        if (null === $this->header_offset) {
            $this->header = [];

            return $this->header;
        }

        $this->document->setFlags(SplFileObject::READ_CSV | SplFileObject::READ_AHEAD | SplFileObject::SKIP_EMPTY);
        $this->document->setCsvControl($this->delimiter, $this->enclosure, $this->escape);
        $this->document->seek($this->header_offset);
        $this->header = $this->document->current();
        if (empty($this->header)) {
            throw new RuntimeException(sprintf('The header record does not exist or is empty at offset: `%s`', $this->header_offset));
        }

        if (0 === $this->header_offset) {
            $this->header = $this->removeBOM($this->header, mb_strlen($this->getInputBOM()), $this->enclosure);
        }

        return $this->header;
    }

    /**
     * Add the CSV header if present and valid
     *
     * @param Iterator $iterator
     * @param string[] $header
     *
     * @return Iterator
     */
    protected function combineHeader(Iterator $iterator, array $header): Iterator
    {
        if (null === $this->header_offset) {
            return $iterator;
        }

        $header = $this->filterColumnNames($header);
        $header_count = count($header);
        $iterator = new CallbackFilterIterator($iterator, function (array $record, int $offset): bool {
            return $offset != $this->header_offset;
        });

        $mapper = function (array $record) use ($header_count, $header): array {
            if ($header_count != count($record)) {
                $record = array_slice(array_pad($record, $header_count, null), 0, $header_count);
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
     * Strip the BOM sequence from a record
     *
     * @param string[] $row
     * @param int      $bom_length
     * @param string   $enclosure
     *
     * @return string[]
     */
    protected function removeBOM(array $row, int $bom_length, string $enclosure): array
    {
        if (0 == $bom_length) {
            return $row;
        }

        $row[0] = mb_substr($row[0], $bom_length);
        if ($enclosure == mb_substr($row[0], 0, 1) && $enclosure == mb_substr($row[0], -1, 1)) {
            $row[0] = mb_substr($row[0], 1, -1);
        }

        return $row;
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
        if (null !== $offset) {
            $offset = $this->filterInteger($offset, 0, __METHOD__.': the header offset index must be a positive integer or 0');
        }

        if ($offset !== $this->header_offset) {
            $this->header_offset = $offset;
            $this->resetProperties();
        }

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
