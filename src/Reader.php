<?php
/**
* This file is part of the League.csv library
*
* @license http://opensource.org/licenses/MIT
* @link https://github.com/thephpleague/csv/
* @version 8.0.0
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
use SplFileObject;

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
        return iterator_to_array($this->fetch($callable), false);
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
        $iterator = $this->fetch();
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
        $iterator = $this->fetch();
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
     * @param int           $columnIndex CSV column index
     * @param callable|null $callable    A callable to be applied to each of the value to be returned.
     *
     * @return Iterator
     */
    public function fetchColumn($columnIndex = 0, callable $callable = null)
    {
        $columnIndex = $this->validateInteger($columnIndex, 0, 'the column index must be a positive integer or 0');

        $filterColumn = function ($row) use ($columnIndex) {
            return isset($row[$columnIndex]);
        };

        $selectColumn = function ($row) use ($columnIndex) {
            return $row[$columnIndex];
        };

        $this->addFilter($filterColumn);
        $iterator = $this->fetch($selectColumn);
        $iterator = $this->applyCallable($iterator, $callable);

        return $iterator;
    }

    /**
     * Retrieve CSV data as pairs
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
     * @param int           $offsetIndex The column index to serve as offset
     * @param int           $valueIndex  The column index to serve as value
     * @param callable|null $callable    A callable to be applied to each of the rows to be returned.
     *
     * @return array
     */
    public function fetchPairsWithoutDuplicates($offsetIndex = 0, $valueIndex = 1, callable $callable = null)
    {
        return iterator_to_array($this->fetchPairs($offsetIndex, $valueIndex, $callable), true);
    }

    /**
     * Fetches the next key-value pairs from a result set (first
     * column is the key, second column is the value).
     *
     * By default if no column index is provided:
     * - the first CSV column is used to provide the keys
     * - the second CSV column is used to provide the value
     *
     * @param int           $offsetIndex The column index to serve as offset
     * @param int           $valueIndex  The column index to serve as value
     * @param callable|null $callable    A callable to be applied to each of the rows to be returned.
     *
     * @return Generator
     */
    public function fetchPairs($offsetIndex = 0, $valueIndex = 1, callable $callable = null)
    {
        $offsetIndex = $this->validateInteger($offsetIndex, 0, 'the offset column index must be a positive integer or 0');
        $valueIndex = $this->validateInteger($valueIndex, 0, 'the value column index must be a positive integer or 0');
        $filterPairs = function ($row) use ($offsetIndex) {
            return isset($row[$offsetIndex]);
        };
        $selectPairs = function ($row) use ($offsetIndex, $valueIndex) {
            return [
                $row[$offsetIndex],
                isset($row[$valueIndex]) ? $row[$valueIndex] : null,
            ];
        };

        $this->addFilter($filterPairs);
        $iterator = $this->fetch($selectPairs);
        $iterator = $this->applyCallable($iterator, $callable);

        return $this->generatePairs($iterator);
    }

    /**
     * Return the key/pairs as a PHP generator
     *
     * @param Iterator $iterator
     *
     * @return Generator
     */
    protected function generatePairs(Iterator $iterator)
    {
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
        $combineArray = function (array $row) use ($keys, $keys_count) {
            if ($keys_count != count($row)) {
                $row = array_slice(array_pad($row, $keys_count, null), 0, $keys_count);
            }

            return array_combine($keys, $row);
        };

        $iterator = $this->fetch($combineArray);
        $iterator = $this->applyCallable($iterator, $callable);

        return $iterator;
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
        $fileObj = $this->getIterator();
        $fileObj->setFlags(SplFileObject::READ_AHEAD | SplFileObject::SKIP_EMPTY);
        $iterator = new LimitIterator($fileObj, $offset, 1);
        $iterator->rewind();
        $line = $iterator->current();

        if (empty($line)) {
            throw new InvalidArgumentException('the specified row does not exist or is empty');
        }

        if (0 === $offset && $this->isBomStrippable()) {
            $line = mb_substr($line, mb_strlen($this->getInputBom()));
        }

        return str_getcsv($line, $this->delimiter, $this->enclosure, $this->escape);
    }
}
