<?php
/**
* This file is part of the League.csv library
*
* @license http://opensource.org/licenses/MIT
* @link https://github.com/thephpleague/csv/
* @version 9.1.4
* @package League.csv
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
declare(strict_types=1);

namespace League\Csv;

use CallbackFilterIterator;
use Countable;
use Generator;
use Iterator;
use IteratorAggregate;
use JsonSerializable;
use LimitIterator;

/**
 * Represents the result set of a {@link Reader} processed by a {@link Statement}
 *
 * @package League.csv
 * @since   9.0.0
 * @author  Ignace Nyamagana Butera <nyamsprod@gmail.com>
 */
class ResultSet implements Countable, IteratorAggregate, JsonSerializable
{
    /**
     * The CSV records collection
     *
     * @var Iterator
     */
    protected $records;

    /**
     * The CSV records collection header
     *
     * @var array
     */
    protected $header = [];

    /**
     * New instance
     *
     * @param Iterator $records a CSV records collection iterator
     * @param array    $header  the associated collection column names
     */
    public function __construct(Iterator $records, array $header)
    {
        $this->records = $records;
        $this->header = $header;
    }

    /**
     * {@inheritdoc}
     */
    public function __destruct()
    {
        unset($this->records);
    }

    /**
     * Returns the header associated with the result set
     *
     * @return string[]
     */
    public function getHeader(): array
    {
        return $this->header;
    }

    /**
     * {@inheritdoc}
     */
    public function getRecords(): Generator
    {
        foreach ($this->records as $offset => $value) {
            yield $offset => $value;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator(): Generator
    {
        return $this->getRecords();
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        return iterator_count($this->records);
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize(): array
    {
        return iterator_to_array($this->records, false);
    }

    /**
     * Returns the nth record from the result set
     *
     * By default if no index is provided the first record of the resultet is returned
     *
     * @param int $nth_record the CSV record offset
     *
     * @throws Exception if argument is lesser than 0
     *
     * @return array
     */
    public function fetchOne(int $nth_record = 0): array
    {
        if ($nth_record < 0) {
            throw new Exception(sprintf('%s() expects the submitted offset to be a positive integer or 0, %s given', __METHOD__, $nth_record));
        }

        $iterator = new LimitIterator($this->records, $nth_record, 1);
        $iterator->rewind();

        return (array) $iterator->current();
    }

    /**
     * Returns a single column from the next record of the result set
     *
     * By default if no value is supplied the first column is fetch
     *
     * @param string|int $index CSV column index
     *
     * @return Generator
     */
    public function fetchColumn($index = 0): Generator
    {
        $offset = $this->getColumnIndex($index, __METHOD__.'() expects the column index to be a valid string or integer, `%s` given');
        $filter = function (array $record) use ($offset): bool {
            return isset($record[$offset]);
        };

        $select = function (array $record) use ($offset): string {
            return $record[$offset];
        };

        $iterator = new MapIterator(new CallbackFilterIterator($this->records, $filter), $select);
        foreach ($iterator as $offset => $value) {
            yield $offset => $value;
        }
    }

    /**
     * Filter a column name against the header if any
     *
     * @param string|int $field         the field name or the field index
     * @param string     $error_message the associated error message
     *
     * @return string|int
     */
    protected function getColumnIndex($field, string $error_message)
    {
        $method = is_string($field) ? 'getColumnIndexByValue' : 'getColumnIndexByKey';

        return $this->$method($field, $error_message);
    }

    /**
     * Returns the selected column name
     *
     * @param string $value
     * @param string $error_message
     *
     * @throws Exception if the column is not found
     *
     * @return string
     */
    protected function getColumnIndexByValue(string $value, string $error_message): string
    {
        if (false !== array_search($value, $this->header, true)) {
            return $value;
        }

        throw new Exception(sprintf($error_message, $value));
    }

    /**
     * Returns the selected column name according to its offset
     *
     * @param int    $index
     * @param string $error_message
     *
     * @throws Exception if the field is invalid or not found
     *
     * @return int|string
     */
    protected function getColumnIndexByKey(int $index, string $error_message)
    {
        if ($index < 0) {
            throw new Exception($error_message);
        }

        if (empty($this->header)) {
            return $index;
        }

        $value = array_search($index, array_flip($this->header), true);
        if (false !== $value) {
            return $value;
        }

        throw new Exception(sprintf($error_message, $index));
    }

    /**
     * Returns the next key-value pairs from a result set (first
     * column is the key, second column is the value).
     *
     * By default if no column index is provided:
     * - the first column is used to provide the keys
     * - the second column is used to provide the value
     *
     * @param string|int $offset_index The column index to serve as offset
     * @param string|int $value_index  The column index to serve as value
     *
     * @return Generator
     */
    public function fetchPairs($offset_index = 0, $value_index = 1): Generator
    {
        $offset = $this->getColumnIndex($offset_index, __METHOD__.'() expects the offset index value to be a valid string or integer, `%s` given');
        $value = $this->getColumnIndex($value_index, __METHOD__.'() expects the value index value to be a valid string or integer, `%s` given');

        $filter = function (array $record) use ($offset): bool {
            return isset($record[$offset]);
        };

        $select = function (array $record) use ($offset, $value): array {
            return [$record[$offset], $record[$value] ?? null];
        };

        $iterator = new MapIterator(new CallbackFilterIterator($this->records, $filter), $select);
        foreach ($iterator as $pair) {
            yield $pair[0] => $pair[1];
        }
    }
}
