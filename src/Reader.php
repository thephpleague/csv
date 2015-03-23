<?php
/**
* This file is part of the League.csv library
*
* @license http://opensource.org/licenses/MIT
* @link https://github.com/thephpleague/csv/
* @version 7.0.1
* @package League.csv
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
namespace League\Csv;

use CallbackFilterIterator;
use InvalidArgumentException;
use League\Csv\Modifier;
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
     * Query Filter Trait
     */
    use Modifier\QueryFilter;

    /**
     * {@ihneritdoc}
     */
    protected $stream_filter_mode = STREAM_FILTER_READ;

    /**
     * Returns a Filtered Iterator
     *
     * @param callable $callable a callable function to be applied to each Iterator item
     *
     * @return \Iterator
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
            $iterator = new Modifier\MapIterator($iterator, $callable);
        }

        return $iterator;
    }

    /**
     * {@inheritdoc}
     */
    protected function getConversionIterator()
    {
        $iterator = $this->getIterator();
        $iterator = $this->applyIteratorFilter($iterator);
        $iterator = $this->applyIteratorSortBy($iterator);
        $iterator = $this->applyIteratorInterval($iterator);

        return $iterator;
    }

    /**
     * Applies a callback function on the CSV
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
     * Returns a single column from the CSV data
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
        $iterator = new CallbackFilterIterator($iterator, function ($row) use ($column_index) {
            return array_key_exists($column_index, $row);
        });
        $iterator = new Modifier\MapIterator($iterator, function ($row) use ($column_index) {
            return $row[$column_index];
        });

        return iterator_to_array($iterator, false);
    }

    /**
     * Returns a single row from the CSV
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
     * Returns a sequential array of all CSV lines
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
     * Returns a sequential array of all CSV lines;
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
        $keys       = $this->getAssocKeys($offset_or_keys);
        $keys_count = count($keys);
        $iterator   = $this->query($callable);
        $iterator   = new Modifier\MapIterator($iterator, function (array $row) use ($keys, $keys_count) {
            if ($keys_count != count($row)) {
                $row = array_slice(array_pad($row, $keys_count, null), 0, $keys_count);
            }

            return array_combine($keys, $row);
        });

        return iterator_to_array($iterator, false);
    }

    /**
     * Selects the array to be used as key for the fetchAssoc method
     *
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
        if (! is_array($offset_or_keys)) {
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
     * Returns a single row from the CSV without filtering
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
     * Validates the array to be used by the fetchAssoc method
     *
     * @param array $keys
     *
     * @return boolean
     */
    protected function isValidAssocKeys(array $keys)
    {
        return count($keys) && $keys === array_unique(array_filter($keys, function ($value) {
            return is_scalar($value) || (is_object($value) && method_exists($value, '__toString'));
        }));
    }
}
