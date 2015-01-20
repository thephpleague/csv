<?php
/**
* This file is part of the League.csv library
*
* @license http://opensource.org/licenses/MIT
* @link https://github.com/thephpleague/csv/
* @version 6.3.0
* @package League.csv
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
namespace League\Csv;

use CallbackFilterIterator;
use InvalidArgumentException;
use Iterator;
use League\Csv\Iterator\MapIterator;
use League\Csv\Iterator\Query;
use LimitIterator;

/**
 *  A class to manage extracting and filtering a CSV
 *
 * @package League.csv
 * @since  3.0.0
 *
 */
class Reader extends AbstractCsv
{
    /**
     *  Iterator Query Trait
     */
    use Query;

    /**
     * {@ihneritdoc}
     */
    protected $stream_filter_mode = STREAM_FILTER_READ;

    /**
     * Return a Filtered Iterator
     *
     * @param callable $callable a callable function to be applied to each Iterator item
     *
     * @return \Traversable
     */
    public function query(callable $callable = null)
    {
        $iterator = new CallbackFilterIterator($this->getIterator(), function ($row) {
            return is_array($row);
        });

        $iterator = $this->applyIteratorFilter($iterator);
        $iterator = $this->applyIteratorSortBy($iterator);
        $iterator = $this->applyIteratorInterval($iterator);
        if (! is_null($callable)) {
            $iterator = new MapIterator($iterator, $callable);
        }

        return $iterator;
    }

    /**
     * Apply a callback function on the CSV
     *
     * The callback function must return TRUE in order to continue
     * iterating over the iterator.
     *
     * @param callable $callable The callback function
     *
     * @return int the iteration count
     */
    public function each(callable $callable)
    {
        $index = 0;
        $iterator = $this->query();
        $iterator->rewind();
        while ($iterator->valid() && true === $callable($iterator->current(), $iterator->key(), $iterator)) {
            ++$index;
            $iterator->next();
        }

        return $index;
    }

    /**
     * Return a single column from the CSV data
     *
     * The callable function will be applied to each value to be return
     *
     * @param int      $column_index field Index
     * @param callable $callable     a callable function
     *
     * @throws \InvalidArgumentException If the column index is not a positive integer or 0
     *
     * @return array
     */
    public function fetchColumn($column_index = 0, callable $callable = null)
    {
        if (false === filter_var($column_index, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]])) {
            throw new InvalidArgumentException(
                'the column index must be a positive integer or 0'
            );
        }

        $iterator = $this->query($callable);
        $iterator = new MapIterator($iterator, function ($row) use ($column_index) {
            if (! array_key_exists($column_index, $row)) {
                return null;
            }

            return $row[$column_index];
        });

        return iterator_to_array($iterator, false);
    }

    /**
     * Return a single row from the CSV
     *
     * @param int $offset
     *
     * @throws \InvalidArgumentException If the $offset is not a valid Integer
     *
     * @return array
     */
    public function fetchOne($offset = 0)
    {
        $this->setOffset($offset);
        $this->setLimit(1);
        $iterator = $this->query();
        $iterator->rewind();

        return (array) $iterator->current();
    }

    /**
     * Return a sequential array of all CSV lines
     *
     * The callable function will be applied to each Iterator item
     *
     * @param callable $callable a callable function
     *
     * @return array
     */
    public function fetchAll(callable $callable = null)
    {
        return iterator_to_array($this->query($callable), false);
    }

    /**
     * Return a sequential array of all CSV lines;
     *
     * The rows are presented as associated arrays
     * The callable function will be applied to each Iterator item
     *
     * @param array|int $offset_or_keys the name for each key member OR the row Index to be
     *                                  used as the associated named keys
     * @param callable  $callable       a callable function
     *
     * @throws \InvalidArgumentException If the submitted keys are invalid
     *
     * @return array
     */
    public function fetchAssoc($offset_or_keys = 0, callable $callable = null)
    {
        $keys = $this->getAssocKeys($offset_or_keys);

        $iterator = $this->query($callable);
        $iterator = new MapIterator($iterator, function ($row) use ($keys) {
            return static::combineArray($keys, $row);
        });

        return iterator_to_array($iterator, false);
    }

    /**
     * Select the array to be used as key for the fetchAssoc method
     * @param array|int $offset_or_keys the assoc key OR the row Index to be used
     *                                  as the key index
     *
     * @throws \InvalidArgumentException If the row index and/or the resulting array is invalid
     *
     * @return array
     */
    protected function getAssocKeys($offset_or_keys)
    {
        $res = $offset_or_keys;
        if (! is_array($res)) {
            $res = $this->getRow($offset_or_keys);
            $this->addFilter(function ($row, $rowIndex) use ($offset_or_keys) {
                return is_array($row) && $rowIndex != $offset_or_keys;
            });
        }
        if (! $this->isValidAssocKeys($res)) {
            throw new InvalidArgumentException(
                'Use a flat non empty array with unique string values'
            );
        }

        return $res;
    }

    /**
     * Return a single row from the CSV without filtering
     *
     * @param int $offset
     *
     * @throws \InvalidArgumentException If the $offset is not valid or the row does not exist
     *
     * @return array
     */
    protected function getRow($offset)
    {
        if (false === filter_var($offset, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]])) {
            throw new InvalidArgumentException('the row index must be a positive integer or 0');
        }

        $iterator = new LimitIterator($this->getIterator(), $offset, 1);
        $iterator->rewind();
        $res = $iterator->current();
        if (is_null($res)) {
            throw new InvalidArgumentException('the specified row does not exist');
        }

        return $res;
    }

    /**
     * Validate the array to be used by the fetchAssoc method
     *
     * @param array $keys
     *
     * @return boolean
     */
    protected function isValidAssocKeys(array $keys)
    {
        $validKeys = array_unique(array_filter($keys, function ($value) {
            return self::isValidString($value);
        }));

        return count($keys) && $keys === $validKeys;
    }

    /**
     * Intelligent Array Combine
     *
     * add or remove values from the $value array to
     * match array $keys length before using PHP array_combine function
     *
     * @param array $keys
     * @param array $value
     *
     * @return array
     */
    protected static function combineArray(array $keys, array $value)
    {
        $nbKeys = count($keys);
        $diff = $nbKeys - count($value);
        if ($diff > 0) {
            $value = array_merge($value, array_fill(0, $diff, null));
        } elseif ($diff < 0) {
            $value = array_slice($value, 0, $nbKeys);
        }

        return array_combine($keys, $value);
    }
}
