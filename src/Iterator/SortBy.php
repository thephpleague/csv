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
namespace League\Csv\Iterator;

use Iterator;
use ArrayIterator;

/**
 *  A Trait to sort an Iterator against
 *  a collection of Sort functions
 *
 * @package League.csv
 * @since  4.2.1
 *
 */
trait SortBy
{
    /**
     * Callable function to sort the ArrayObject
     *
     * @var callable
     */
    protected $iterator_sort_by = [];

    /**
     * Set an Iterator sorting callable function
     *
     * @param callable $callable
     *
     * @return self
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
     * @return self
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
     * @return boolean
     */
    public function hasSortBy(callable $callable)
    {
        return false !== array_search($callable, $this->iterator_sort_by, true);
    }

    /**
     * Remove all registered callable
     *
     * @return self
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
        $res = iterator_to_array($iterator, false);

        uasort($res, function ($rowA, $rowB) {
            foreach ($this->iterator_sort_by as $callable) {
                $res = $callable($rowA, $rowB);
                if (0 !== $res) {
                    return $res;
                }
            }

            return 0;
        });

        $this->clearSortBy();

        return new ArrayIterator($res);
    }
}
