<?php
/**
* This file is part of the League.csv library
*
* @license http://opensource.org/licenses/MIT
* @link https://github.com/thephpleague/csv/
* @version 6.0.1
* @package League.csv
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
namespace League\Csv\Iterator;

use ArrayIterator;
use CallbackFilterIterator;
use InvalidArgumentException;
use Iterator;
use LimitIterator;

/**
 *  A Trait to Query rows against a SplFileObject
 *
 * @package League.csv
 * @since  4.2.1
 *
 */
trait Query
{
    /**
     * Callable function to filter the iterator
     *
     * @var array
     */
    protected $iterator_filters = [];

    /**
     * Callable function to sort the ArrayObject
     *
     * @var callable
     */
    protected $iterator_sort_by = [];

    /**
     * iterator Offset
     *
     * @var int
     */
    protected $iterator_offset = 0;

    /**
     * iterator maximum length
     *
     * @var int
     */
    protected $iterator_limit = -1;

    /**
     * Set LimitIterator Offset
     *
     * @param $offset
     *
     * @return $this
     */
    public function setOffset($offset = 0)
    {
        if (false === filter_var($offset, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]])) {
            throw new InvalidArgumentException('the offset must be a positive integer or 0');
        }
        $this->iterator_offset = $offset;

        return $this;
    }

    /**
     * Set LimitInterator Count
     *
     * @param int $limit
     *
     * @return $this
     */
    public function setLimit($limit = -1)
    {
        if (false === filter_var($limit, FILTER_VALIDATE_INT, ['options' => ['min_range' => -1]])) {
            throw new InvalidArgumentException('the limit must an integer greater or equals to -1');
        }
        $this->iterator_limit = $limit;

        return $this;
    }

    /**
    * Sort the Iterator
    *
    * @param \Iterator $iterator
    *
    * @return \LimitIterator
    */
    protected function applyIteratorInterval(Iterator $iterator)
    {
        if (0 == $this->iterator_offset && -1 == $this->iterator_limit) {
            return $iterator;
        }
        $offset = $this->iterator_offset;
        $limit = $this->iterator_limit;

        $this->iterator_limit = -1;
        $this->iterator_offset = 0;

        return new LimitIterator($iterator, $offset, $limit);
    }

    /**
     * Set an Iterator sorting callable function
     *
     * @param callable $callable
     *
     * @return $this
     */
    public function addSortBy(callable $callable)
    {
        $this->iterator_sort_by[] = $callable;

        return $this;
    }

    /**
     * Remove a callable from the collection
     *
     * @param callable $callable
     *
     * @return $this
     */
    public function removeSortBy(callable $callable)
    {
        $res = array_search($callable, $this->iterator_sort_by, true);
        if (false !== $res) {
            unset($this->iterator_sort_by[$res]);
        }

        return $this;
    }

    /**
     * Detect if the callable is already registered
     *
     * @param callable $callable
     *
     * @return bool
     */
    public function hasSortBy(callable $callable)
    {
        return false !== array_search($callable, $this->iterator_sort_by, true);
    }

    /**
     * Remove all registered callable
     *
     * @return $this
     */
    public function clearSortBy()
    {
        $this->iterator_sort_by = [];

        return $this;
    }

    /**
    * Sort the Iterator
    *
    * @param \Iterator $iterator
    *
    * @return \ArrayIterator
    */
    protected function applyIteratorSortBy(Iterator $iterator)
    {
        if (! $this->iterator_sort_by) {
            return $iterator;
        }
        $nb_callbacks = count($this->iterator_sort_by);
        $this->iterator_sort_by = array_values($this->iterator_sort_by);
        $res = iterator_to_array($iterator, false);
        uasort($res, function ($rowA, $rowB) use ($nb_callbacks) {
            $res   = 0;
            $index = 0;
            while ($index < $nb_callbacks && 0 === $res) {
                $res = $this->iterator_sort_by[$index]($rowA, $rowB);
                ++$index;
            }

            return $res;
        });
        $this->clearSortBy();

        return new ArrayIterator($res);
    }

    /**
     * Set the Iterator filter method
     *
     * @param callable $callable
     *
     * @return $this
     */
    public function addFilter(callable $callable)
    {
        $this->iterator_filters[] = $callable;

        return $this;
    }

    /**
     * Remove a filter from the callable collection
     *
     * @param callable $callable
     *
     * @return $this
     */
    public function removeFilter(callable $callable)
    {
        $res = array_search($callable, $this->iterator_filters, true);
        if (false !== $res) {
            unset($this->iterator_filters[$res]);
        }

        return $this;
    }

    /**
     * Detect if the callable filter is already registered
     *
     * @param callable $callable
     *
     * @return bool
     */
    public function hasFilter(callable $callable)
    {
        return false !== array_search($callable, $this->iterator_filters, true);
    }

    /**
     * Remove all registered callable filter
     *
     * @return $this
     */
    public function clearFilter()
    {
        $this->iterator_filters = [];

        return $this;
    }

    /**
    * Filter the Iterator
    *
    * @param \Iterator $iterator
    *
    * @return \Iterator
    */
    protected function applyIteratorFilter(Iterator $iterator)
    {
        foreach ($this->iterator_filters as $callable) {
            $iterator = new CallbackFilterIterator($iterator, $callable);
        }
        $this->clearFilter();

        return $iterator;
    }
}
