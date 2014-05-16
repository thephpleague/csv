<?php
/**
* This file is part of the League.csv library
*
* @license http://opensource.org/licenses/MIT
* @link https://github.com/thephpleague/csv/
* @version 5.5.0
* @package League.csv
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
namespace League\Csv;

use InvalidArgumentException;
use Iterator;
use CallbackFilterIterator;
use League\Csv\Iterator\MapIterator;
use League\Csv\Iterator\Filter;
use League\Csv\Iterator\SortBy;
use League\Csv\Iterator\Interval;

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
     *  Iterator Filtering Trait
     */
    use Filter;

    /**
     *  Iterator Sorting Trait
     */
    use SortBy;

    /**
     *  Iterator Set Interval Trait
     */
    use Interval;

    /**
     * {@ihneritdoc}
     */
    protected $stream_filter_mode = STREAM_FILTER_READ;

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
    private static function combineArray(array $keys, array $value)
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
     * @param callable $callable The callback function to call on every element.
     *                           The function must return TRUE in order to continue
     *                           iterating over the iterator.
     *
     * @return integer the iteration count
     */
    public function each(callable $callable)
    {
        $iterator = $this->query();
        $index = 0;
        foreach ($iterator as $rowIndex => $row) {
            if (true !== $callable($row, $rowIndex, $iterator)) {
                break;
            }
            $index++;
        }

        return $index;
    }

    /**
     * Return a single row from the CSV
     *
     * @param integer $offset
     *
     * @return array
     *
     * @throws \InvalidArgumentException If the $offset is not a valid Integer
     */
    public function fetchOne($offset = 0)
    {
        $this->setOffset($offset);
        $this->setLimit(1);
        $iterator = $this->query();
        $iterator->rewind();
        $res = $iterator->getInnerIterator()->current();
        if (! is_array($res)) {
            return [];
        }

        return $res;
    }

    /**
     * Return a sequential array of all CSV lines
     *
     * @param callable $callable a callable function to be applied to each Iterator item
     *
     * @return array
     */
    public function fetchAll(callable $callable = null)
    {
        return iterator_to_array($this->query($callable), false);
    }

    /**
     * Return a sequential array of all CSV lines; the rows are presented as associated arrays
     *
     * @param array    $keys     the name for each key member
     * @param callable $callable a callable function to be applied to each Iterator item
     *
     * @return array
     *
     * @throws \InvalidArgumentException If the submitted keys are not integer or strng
     */
    public function fetchAssoc(array $keys, callable $callable = null)
    {
        $validKeys = array_unique(array_filter($keys, function ($value) {
            return self::isValidString($value);
        }));

        if ($keys !== $validKeys) {
            throw new InvalidArgumentException(
                'The named keys should be unique strings Or integer'
            );
        }

        $iterator = $this->query($callable);
        $iterator = new MapIterator($iterator, function ($row) use ($keys) {
            return self::combineArray($keys, $row);
        });

        return iterator_to_array($iterator, false);
    }

    /**
     * Return a single column from the CSV data
     *
     * @param integer  $column_index field Index
     * @param callable $callable     a callable function to be applied to each value to be return
     *
     * @return array
     *
     * @throws \InvalidArgumentException If the column index is not a positive integer or 0
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
     * Create a {@link Writer} instance from a {@link Reader} object
     *
     * @param string $open_mode the file open mode flag
     *
     * @return \League\Csv\Writer object
     */
    public function newWriter($open_mode = 'r+')
    {
        $csv = new Writer($this->path, $open_mode);
        $csv->delimiter = $this->delimiter;
        $csv->escape = $this->escape;
        $csv->enclosure = $this->enclosure;
        $csv->flags = $this->flags;
        $csv->encodingFrom = $this->encodingFrom;

        return $csv;
    }
}
