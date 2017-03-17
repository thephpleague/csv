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
use Generator;
use Iterator;
use IteratorAggregate;
use League\Csv\Exception\InvalidArgumentException;
use League\Csv\Exception\RuntimeException;
use LimitIterator;

/**
 * A class to manage extracting and filtering a CSV
 *
 * @package League.csv
 * @since   9.0.0
 * @author  Ignace Nyamagana Butera <nyamsprod@gmail.com>
 *
 */
class RecordSet implements IteratorAggregate, Countable
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
     * Tell whether the CSV document offset
     * must be kept on output
     *
     * @var bool
     */
    protected $preserve_offset = false;

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
     * Tell whether the CSV document offset
     * must be kept on output
     *
     * @return bool
     */
    public function isRecordOffsetPreserved(): bool
    {
        return $this->preserve_offset;
    }

    /**
     * @inheritdoc
     */
    public function getIterator(): Generator
    {
        return $this->iteratorToGenerator($this->iterator, $this->preserve_offset);
    }

    /**
     * Return the generator depending on the preserveRecordOffset setting
     *
     * @param Iterator $iterator
     *
     * @return Generator
     */
    protected function iteratorToGenerator(Iterator $iterator, bool $preserve_offset): Generator
    {
        if ($preserve_offset) {
            foreach ($iterator as $offset => $value) {
                yield $offset => $value;
            }
            return;
        }

        foreach ($iterator as $value) {
            yield $value;
        }
    }

    /**
     * @inheritdoc
     */
    public function count(): int
    {
        return iterator_count($this->iterator);
    }

    /**
     * Returns a sequential array of all CSV lines
     *
     * @return array
     */
    public function fetchAll(): array
    {
        return iterator_to_array($this->iterator, $this->preserve_offset);
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
        $offset = $this->filterInteger($offset, 0, __METHOD__.': the submitted offset is invalid');
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
     * @return Generator
     */
    public function fetchColumn($index = 0): Generator
    {
        $offset = $this->getColumnIndex($index, __METHOD__.': the column index `%s` value is invalid');
        $filter = function (array $record) use ($offset): bool {
            return isset($record[$offset]);
        };

        $select = function (array $record) use ($offset): string {
            return $record[$offset];
        };

        $iterator = new MapIterator(new CallbackFilterIterator($this->iterator, $filter), $select);

        return $this->iteratorToGenerator($iterator, $this->preserve_offset);
    }

    /**
     * Filter a column name against the CSV header if any
     *
     * @param string|int $field         the field name or the field index
     * @param string     $error_message the associated error message
     *
     * @throws InvalidArgumentException if the field is invalid
     * @throws RuntimeException         if the column is not found
     *
     * @return string|int
     */
    protected function getColumnIndex($field, string $error_message)
    {
        if (false !== array_search($field, $this->column_names, true)) {
            return $field;
        }

        if (is_string($field)) {
            throw new InvalidArgumentException(sprintf($error_message, $field));
        }

        $index = $this->filterInteger($field, 0, $error_message);
        if (empty($this->column_names)) {
            return $index;
        }

        $index = array_search($index, array_flip($this->column_names), true);
        if (false !== $index) {
            return $index;
        }

        throw new RuntimeException(sprintf($error_message, $field));
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
        $offset = $this->getColumnIndex($offset_index, __METHOD__.': the offset index value is invalid');
        $value = $this->getColumnIndex($value_index, __METHOD__.': the value index value is invalid');

        $filter = function (array $record) use ($offset): bool {
            return isset($record[$offset]);
        };

        $select = function (array $record) use ($offset, $value): array {
            return [$record[$offset], $record[$value] ?? null];
        };

        $iterator = new MapIterator(new CallbackFilterIterator($this->iterator, $filter), $select);

        foreach ($iterator as $pair) {
            yield $pair[0] => $pair[1];
        }
    }

    /**
     * Whether we should preserve the CSV document record offset.
     *
     * If set to true CSV document record offset will added to
     * method output where it makes sense.
     *
     * @param bool $status
     *
     * @return static
     */
    public function preserveRecordOffset(bool $status)
    {
        $this->preserve_offset = $status;

        return $this;
    }
}
