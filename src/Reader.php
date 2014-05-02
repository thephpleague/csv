<?php
/**
* League.csv - A CSV data manipulation library
*
* @author Ignace Nyamagana Butera <nyamsprod@gmail.com>
* @copyright 2014 Ignace Nyamagana Butera
* @link https://github.com/thephpleague/csv/
* @license http://opensource.org/licenses/MIT
* @version 5.5.0
* @package League.csv
*
* MIT LICENSE
*
* Permission is hereby granted, free of charge, to any person obtaining
* a copy of this software and associated documentation files (the
* "Software"), to deal in the Software without restriction, including
* without limitation the rights to use, copy, modify, merge, publish,
* distribute, sublicense, and/or sell copies of the Software, and to
* permit persons to whom the Software is furnished to do so, subject to
* the following conditions:
*
* The above copyright notice and this permission notice shall be
* included in all copies or substantial portions of the Software.
*
* THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
* EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
* MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
* NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
* LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
* OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
* WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/
namespace League\Csv;

use InvalidArgumentException;
use Iterator;
use CallbackFilterIterator;
use League\Csv\Iterator\MapIterator;
use League\Csv\Iterator\IteratorFilter;
use League\Csv\Iterator\IteratorSortBy;
use League\Csv\Iterator\IteratorInterval;

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
    use IteratorFilter;

    /**
     *  Iterator Sorting Trait
     */
    use IteratorSortBy;

    /**
     *  Iterator Set Interval Trait
     */
    use IteratorInterval;

    /**
     * {@ihneritdoc}
     */
    protected $stream_filter_mode = STREAM_FILTER_READ;

    /**
     * Intelligent Array Combine
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
        $iterator = $this->query($callable);

        return iterator_to_array($iterator, false);
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
     * DEPRECATION WARNING! This method will be removed in the next major point release
     *
     * @deprecated deprecated since version 5.4
     *
     * @param integer  $column_index field Index
     * @param callable $callable     a callable function to be applied to each value to be return
     *
     * @return array
     *
     * @throws \InvalidArgumentException If the column index is not a positive integer or 0
     */
    public function fetchCol($column_index = 0, callable $callable = null)
    {
        return $this->fetchColumn($column_index, $callable);
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
     * DEPRECATION WARNING! This method will be removed in the next major point release
     *
     * @deprecated deprecated since version 5.5
     */
    public function getWriter($open_mode = 'r+')
    {
        return $this->createWriter($open_mode);
    }

    /**
     * Instantiate a {@link Writer} class from the current {@link Reader}
     *
     * @param string $open_mode the file open mode flag
     *
     * @return \League\Csv\Writer
     */
    public function createWriter($open_mode = 'r+')
    {
        return $this->createFromCurrentInstance('\League\Csv\Writer', $open_mode);
    }
}
