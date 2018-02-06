<?php
/**
* This file is part of the League.csv library
*
* @license http://opensource.org/licenses/MIT
* @link https://github.com/thephpleague/csv/
* @version 8.2.3
* @package League.csv
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
namespace League\Csv;

use Generator;
use InvalidArgumentException;
use Iterator;
use League\Csv\Modifier\MapIterator;
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
     * @inheritdoc
     */
    protected $stream_filter_mode = STREAM_FILTER_READ;

    /**
     * Returns a sequential array of all CSV lines
     *
     * The callable function will be applied to each Iterator item
     *
     * @param callable|null $callable a callable function
     *
     * @return array
     */
    public function fetchAll(callable $callable = null)
    {
        return iterator_to_array($this->applyCallable($this->getQueryIterator(), $callable), false);
    }

    /**
     * Fetch the next row from a result set
     *
     * @param callable|null $callable a callable function to be applied to each Iterator item
     *
     * @return Iterator
     */
    public function fetch(callable $callable = null)
    {
        return $this->applyCallable($this->getQueryIterator(), $callable);
    }

    /**
     * Apply The callable function
     *
     * @param Iterator      $iterator
     * @param callable|null $callable
     *
     * @return Iterator
     */
    protected function applyCallable(Iterator $iterator, callable $callable = null)
    {
        if (null !== $callable) {
            return new MapIterator($iterator, $callable);
        }

        return $iterator;
    }

    /**
     * Applies a callback function on the CSV
     *
     * The callback function must return TRUE in order to continue
     * iterating over the iterator.
     *
     * @param callable $callable a callable function to apply to each selected CSV rows
     *
     * @return int the iteration count
     */
    public function each(callable $callable)
    {
        $index = 0;
        $iterator = $this->getQueryIterator();
        $iterator->rewind();
        while ($iterator->valid() && true === call_user_func(
            $callable,
            $iterator->current(),
            $iterator->key(),
            $iterator
        )) {
            ++$index;
            $iterator->next();
        }

        return $index;
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
    public function fetchOne($offset = 0)
    {
        $this->setOffset($offset);
        $this->setLimit(1);
        $iterator = $this->getQueryIterator();
        $iterator->rewind();

        return (array) $iterator->current();
    }

    /**
     * Returns the next value from a single CSV column
     *
     * The callable function will be applied to each value to be return
     *
     * By default if no column index is provided the first column of the CSV is selected
     *
     * @param int           $column_index CSV column index
     * @param callable|null $callable     A callable to be applied to each of the value to be returned.
     *
     * @return Iterator
     */
    public function fetchColumn($column_index = 0, callable $callable = null)
    {
        $column_index = $this->validateInteger($column_index, 0, 'the column index must be a positive integer or 0');

        $filter_column = function ($row) use ($column_index) {
            return isset($row[$column_index]);
        };

        $select_column = function ($row) use ($column_index) {
            return $row[$column_index];
        };

        $this->addFilter($filter_column);

        return $this->applyCallable(new MapIterator($this->getQueryIterator(), $select_column), $callable);
    }

    /**
     * Retrieve CSV data as pairs
     *
     * DEPRECATION WARNING! This method will be removed in the next major point release
     *
     * @deprecated deprecated since version 8.2
     * @see Reader::fetchPairs
     *
     * Fetches an associative array of all rows as key-value pairs (first
     * column is the key, second column is the value).
     *
     * By default if no column index is provided:
     * - the first CSV column is used to provide the keys
     * - the second CSV column is used to provide the value
     *
     * If the value from the column key index is duplicated its corresponding value will
     * be overwritten
     *
     * @param int           $offset_index The column index to serve as offset
     * @param int           $value_index  The column index to serve as value
     * @param callable|null $callable     A callable to be applied to each of the rows to be returned.
     *
     * @return array
     */
    public function fetchPairsWithoutDuplicates($offset_index = 0, $value_index = 1, callable $callable = null)
    {
        return iterator_to_array($this->fetchPairs($offset_index, $value_index, $callable), true);
    }

    /**
     * Fetches the next key-value pairs from a result set (first
     * column is the key, second column is the value).
     *
     * By default if no column index is provided:
     * - the first CSV column is used to provide the keys
     * - the second CSV column is used to provide the value
     *
     * @param int           $offset_index The column index to serve as offset
     * @param int           $value_index  The column index to serve as value
     * @param callable|null $callable     A callable to be applied to each of the rows to be returned.
     *
     * @return Generator
     */
    public function fetchPairs($offset_index = 0, $value_index = 1, callable $callable = null)
    {
        $offset_index = $this->validateInteger($offset_index, 0, 'the offset column index must be a positive integer or 0');
        $value_index = $this->validateInteger($value_index, 0, 'the value column index must be a positive integer or 0');
        $filter_pairs = function ($row) use ($offset_index) {
            return isset($row[$offset_index]);
        };
        $select_pairs = function ($row) use ($offset_index, $value_index) {
            return [
                $row[$offset_index],
                isset($row[$value_index]) ? $row[$value_index] : null,
            ];
        };

        $this->addFilter($filter_pairs);
        $iterator = $this->applyCallable(new MapIterator($this->getQueryIterator(), $select_pairs), $callable);
        foreach ($iterator as $row) {
            yield $row[0] => $row[1];
        }
    }

    /**
     * Fetch the next row from a result set
     *
     * The rows are presented as associated arrays
     * The callable function will be applied to each row
     *
     * @param int|array $offset_or_keys the name for each key member OR the row Index to be
     *                                  used as the associated named keys
     *
     * @param callable $callable A callable to be applied to each of the rows to be returned.
     *
     * @throws InvalidArgumentException If the submitted keys are invalid
     *
     * @return Iterator
     */
    public function fetchAssoc($offset_or_keys = 0, callable $callable = null)
    {
        $keys = $this->getAssocKeys($offset_or_keys);
        $keys_count = count($keys);
        $combine_array = function (array $row) use ($keys, $keys_count) {
            if ($keys_count != count($row)) {
                $row = array_slice(array_pad($row, $keys_count, null), 0, $keys_count);
            }

            return array_combine($keys, $row);
        };

        return $this->applyCallable(new MapIterator($this->getQueryIterator(), $combine_array), $callable);
    }

    /**
     * Selects the array to be used as key for the fetchAssoc method
     *
     * @param int|array $offset_or_keys the assoc key OR the row Index to be used
     *                                  as the key index
     *
     * @throws InvalidArgumentException If the row index and/or the resulting array is invalid
     *
     * @return array
     */
    protected function getAssocKeys($offset_or_keys)
    {
        if (is_array($offset_or_keys)) {
            return $this->validateKeys($offset_or_keys);
        }

        $offset_or_keys = $this->validateInteger(
            $offset_or_keys,
            0,
            'the row index must be a positive integer, 0 or a non empty array'
        );
        $keys = $this->validateKeys($this->getRow($offset_or_keys));
        $filterOutRow = function ($row, $rowIndex) use ($offset_or_keys) {
            return $rowIndex != $offset_or_keys;
        };
        $this->addFilter($filterOutRow);

        return $keys;
    }

    /**
     * Validates the array to be used by the fetchAssoc method
     *
     * @param array $keys
     *
     * @throws InvalidArgumentException If the submitted array fails the assertion
     *
     * @return array
     */
    protected function validateKeys(array $keys)
    {
        if (empty($keys) || $keys !== array_unique(array_filter($keys, [$this, 'isValidKey']))) {
            throw new InvalidArgumentException('Use a flat array with unique string values');
        }

        return $keys;
    }

    /**
     * Returns whether the submitted value can be used as string
     *
     * @param mixed $value
     *
     * @return bool
     */
    protected function isValidKey($value)
    {
        return is_scalar($value) || (is_object($value) && method_exists($value, '__toString'));
    }

    /**
     * Returns a single row from the CSV without filtering
     *
     * @param int $offset
     *
     * @throws InvalidArgumentException If the $offset is not valid or the row does not exist
     *
     * @return array
     */
    protected function getRow($offset)
    {
        $row = $this->seekRow($offset);
        if (empty($row)) {
            throw new InvalidArgumentException('the specified row does not exist or is empty');
        }

        if (0 !== $offset || !$this->isBomStrippable()) {
            return $row;
        }

        $bom_length = mb_strlen($this->getInputBOM());
        $row[0] = mb_substr($row[0], $bom_length);
        if ($this->enclosure == mb_substr($row[0], 0, 1) && $this->enclosure == mb_substr($row[0], -1, 1)) {
            $row[0] = mb_substr($row[0], 1, -1);
        }

        return $row;
    }

    /**
     * Returns the row at a given offset
     *
     * @param int $offset
     *
     * @return mixed
     */
    protected function seekRow($offset)
    {
        $stream = $this->getIterator();
        $stream->rewind();
        //Workaround for SplFileObject::seek bug in PHP7.2+ see https://bugs.php.net/bug.php?id=75917
        if (PHP_VERSION_ID > 70200 && !$stream instanceof StreamIterator) {
            while ($offset !== $stream->key() && $stream->valid()) {
                $stream->next();
            }

            return $stream->current();
        }

        $iterator = new LimitIterator($stream, $offset, 1);
        $iterator->rewind();

        return $iterator->current();
    }
}
