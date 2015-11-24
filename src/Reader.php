<?php
/**
* This file is part of the League.csv library
*
* @license http://opensource.org/licenses/MIT
* @link https://github.com/thephpleague/csv/
* @version 7.2.0
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
use UnexpectedValueException;

/**
 *  A class to manage extracting and filtering a CSV
 *
 * @package League.csv
 * @since  3.0.0
 *
 */
class Reader extends AbstractCsv
{
    const TYPE_ARRAY = 1;

    const TYPE_ITERATOR = 2;

    /**
     * @inheritdoc
     */
    protected $stream_filter_mode = STREAM_FILTER_READ;

    /**
     * Reader return type
     *
     * @var int
     */
    protected $returnType = self::TYPE_ARRAY;

    /**
     * Returns the return type for the next fetch call
     *
     * @return int
     */
    public function getReturnType()
    {
        return $this->returnType;
    }

    /**
     * Set the return type for the next fetch call
     *
     * @param int $type
     *
     * @throws UnexpectedValueException If the value is not one of the defined constant
     *
     * @return static
     */
    public function setReturnType($type)
    {
        $modes = [static::TYPE_ARRAY => 1, static::TYPE_ITERATOR => 1];
        if (!isset($modes[$type])) {
            throw new UnexpectedValueException('Unknown return type');
        }
        $this->returnType = $type;

        return $this;
    }

    /**
     * Return a Filtered Iterator
     *
     * @param callable|null $callable a callable function to be applied to each Iterator item
     *
     * @return Iterator
     */
    public function fetch(callable $callable = null)
    {
        $this->addFilter(function ($row) {
            return is_array($row);
        });
        $iterator = $this->getIterator();
        $iterator = $this->applyBomStripping($iterator);
        $iterator = $this->applyIteratorFilter($iterator);
        $iterator = $this->applyIteratorSortBy($iterator);
        $iterator = $this->applyIteratorInterval($iterator);
        $this->returnType = static::TYPE_ARRAY;
        if (!is_null($callable)) {
            return new MapIterator($iterator, $callable);
        }

        return $iterator;
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
        return iterator_to_array($this->fetch($callable), false);
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
     * @param int $offset
     *
     * @throws InvalidArgumentException If the $offset is not a valid Integer
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
     * Returns a single column from the CSV data
     *
     * The callable function will be applied to each value to be return
     *
     * By default if no column index is provided the first column of the CSV is selected
     *
     * @param int           $column_index field Index
     * @param callable|null $callable     a callable function
     *
     * @throws InvalidArgumentException If the column index is not a positive integer or 0
     *
     * @return Iterator|array
     */
    public function fetchColumn($columnIndex = 0, callable $callable = null)
    {
        $this->assertValidColumnIndex($columnIndex);

        $filterColumn = function ($row) use ($columnIndex) {
            return array_key_exists($columnIndex, $row);
        };

        $selectColumn = function ($row) use ($columnIndex) {
            return $row[$columnIndex];
        };

        $this->addFilter($filterColumn);
        $type = $this->returnType;
        $iterator = $this->fetch($selectColumn);
        if (!is_null($callable)) {
            $iterator = new MapIterator($iterator, $callable);
        }

        return $this->applyReturnType($type, $iterator, false);
    }

    /**
     * Validate a CSV row index
     *
     * @param int $index
     *
     * @throws InvalidArgumentException If the column index is not a positive integer or 0
     */
    protected function assertValidColumnIndex($index)
    {
        if (false === filter_var($index, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]])) {
            throw new InvalidArgumentException('the column index must be a positive integer or 0');
        }
    }

    /**
     * Convert the Iterator into an array depending on the class returnType
     *
     * @param int      $type
     * @param Iterator $iterator
     * @param bool     $use_keys Whether to use the iterator element keys as index
     *
     * @return Iterator|array
     */
    protected function applyReturnType($type, Iterator $iterator, $use_keys = true)
    {
        if (static::TYPE_ARRAY == $type) {
            return iterator_to_array($iterator, $use_keys);
        }

        return $iterator;
    }

    /**
     * Retrive CSV data as pairs
     *
     * Fetches an associative array of all rows as key-value pairs (first
     * column is the key, second column is the value).
     *
     * By default if no column index is provided:
     * - the first CSV column is used to provide the keys
     * - the second CSV column is used to provide the value
     *
     * @param int           $offsetColumnIndex The column index to server as offset
     * @param int           $valueColumnIndex  The column index to server as value
     * @param callable|null $callable          Callback function to run for each element in each array
     *
     * @return Generator|array
     */
    public function fetchPairs($offsetColumnIndex = 0, $valueColumnIndex = 1, callable $callable = null)
    {
        $this->assertValidColumnIndex($offsetColumnIndex);
        $this->assertValidColumnIndex($valueColumnIndex);
        $filterPairs = function ($row) use ($offsetColumnIndex, $valueColumnIndex) {
            return array_key_exists($offsetColumnIndex, $row) && array_key_exists($valueColumnIndex, $row);
        };
        $selectPairs = function ($row) use ($offsetColumnIndex, $valueColumnIndex) {
            return [$row[$offsetColumnIndex], $row[$valueColumnIndex]];
        };
        $this->addFilter($filterPairs);
        $type = $this->returnType;
        $iterator = $this->fetch($selectPairs);

        if (!is_null($callable)) {
            $iterator = new MapIterator($iterator, $callable);
        }

        return $this->applyReturnType($type, $this->fetchPairsGenerator($iterator), true);
    }

    /**
     * Return the key/pairs as a PHP generator
     *
     * @param Iterator $iterator
     *
     * @return Generator
     */
    protected function fetchPairsGenerator(Iterator $iterator)
    {
        foreach ($iterator as $row) {
            yield $row[0] => $row[1];
        }
    }

    /**
     * Returns a sequential array of all CSV lines;
     *
     * The rows are presented as associated arrays
     * The callable function will be applied to each Iterator item
     *
     * @param int|array $offset_or_keys the name for each key member OR the row Index to be
     *                                  used as the associated named keys
     *
     * @param callable $callable a callable function
     *
     * @throws InvalidArgumentException If the submitted keys are invalid
     *
     * @return Iterator|array
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

        return $this->applyReturnType(
            $this->returnType,
            new MapIterator($this->fetch($callable), $combineArray),
            false
        );
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
            $this->assertValidAssocKeys($offset_or_keys);

            return $offset_or_keys;
        }

        if (false === filter_var($offset_or_keys, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]])) {
            throw new InvalidArgumentException('the row index must be a positive integer, 0 or a non empty array');
        }

        $keys = $this->getRow($offset_or_keys);
        $this->assertValidAssocKeys($keys);
        $filterOutRow = function ($row, $rowIndex) use ($offset_or_keys) {
            return is_array($row) && $rowIndex != $offset_or_keys;
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
     */
    protected function assertValidAssocKeys(array $keys)
    {
        if (empty($keys) || $keys !== array_unique(array_filter($keys, [$this, 'isValidString']))) {
            throw new InvalidArgumentException('Use a flat array with unique string values');
        }
    }

    /**
     * Returns whether the submitted value can be used as string
     *
     * @param mixed $value
     *
     * @return bool
     */
    protected function isValidString($value)
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
        $csv = $this->getIterator();
        $csv->setFlags($this->getFlags() & ~SplFileObject::READ_CSV);
        $iterator = new LimitIterator($csv, $offset, 1);
        $iterator->rewind();
        $res = $iterator->current();

        if (empty($res)) {
            throw new InvalidArgumentException('the specified row does not exist or is empty');
        }

        if (0 == $offset && $this->isBomStrippable()) {
            $res = mb_substr($res, mb_strlen($this->getInputBom()));
        }

        return str_getcsv($res, $this->delimiter, $this->enclosure, $this->escape);
    }
}
