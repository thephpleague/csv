<?php
/**
* This file is part of the League.csv library
*
* @license http://opensource.org/licenses/MIT
* @link https://github.com/thephpleague/csv/
* @version 7.1.1
* @package League.csv
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
namespace League\Csv\Modifier;

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
trait QueryFilter
{
    /**
     * Callables to filter the iterator
     *
     * @var callable[]
     */
    protected $iterator_filters = [];

    /**
     * Callables to sort the iterator
     *
     * @var callable[]
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
     * Stripping BOM status
     *
     * @var boolean
     */
    protected $strip_bom = false;

    /**
     * Stripping BOM setter
     *
     * @param  bool $status
     *
     * @return $this
     */
    public function stripBom($status)
    {
        $this->strip_bom = (bool) $status;

        return $this;
    }

    /**
     * Tell whethe we can strip or not the leading BOM sequence
     *
     * @return bool
     */
    protected function isBomStrippable()
    {
        $bom = $this->getInputBom();

        return ! empty($bom) && $this->strip_bom;
    }

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
     * Remove the BOM sequence from the CSV
     *
     * @param  Iterator $iterator
     *
     * @return \Iterator
     */
    protected function applyBomStripping(Iterator $iterator)
    {
        if (! $this->strip_bom) {
            return $iterator;
        }

        if (! $this->isBomStrippable()) {
            $this->strip_bom = false;
            return $iterator;
        }

        $this->strip_bom = false;
        $bom = $this->getInputBom();
        return new MapIterator($iterator, function ($row, $index) use ($bom) {
            if (0 == $index) {
                $row[0] = mb_substr($row[0], mb_strlen($bom));
            }

            return $row;
        });
    }

    /**
     * Returns the BOM sequence of the given CSV
     *
     * @return string
     */
    abstract public function getInputBom();

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

    /**
    * Sort the Iterator
    *
    * @param \Iterator $iterator
    *
    * @return \Iterator
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
    * Sort the Iterator
    *
    * @param \Iterator $iterator
    *
    * @return \Iterator
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
}
