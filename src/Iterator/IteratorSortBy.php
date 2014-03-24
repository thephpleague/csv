<?php
/**
* League.csv - A lightweight CSV Coder/Decoder library
*
* @author Ignace Nyamagana Butera <nyamsprod@gmail.com>
* @copyright 2014 Ignace Nyamagana Butera
* @link https://github.com/nyamsprod/League.csv
* @license http://opensource.org/licenses/MIT
* @version 5.4.0
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
namespace League\Csv\Iterator;

use Iterator;
use ArrayIterator;

/**
 *  A Trait to sort an Iterator
 *
 * @package League.csv
 * @since  4.2.1
 *
 */
trait IteratorSortBy
{
    /**
     * Callable function to sort the ArrayObject
     *
     * @var callable
     */
    private $sortBy = [];

    /**
     * Set the Iterator SortBy method
     *
     * DEPRECATION WARNING! This method will be removed in the next major point release
     *
     * @deprecated deprecated since version 5.2
     *
     * @param callable $callable
     *
     * @return self
     */
    public function setSortBy(callable $callable)
    {
        return $this->addSortBy($callable);
    }

    /**
     * Set an Iterator sortBy method
     *
     * @param callable $filter
     *
     * @return self
     */
    public function addSortBy(callable $callable)
    {
        $this->sortBy[] = $callable;

        return $this;
    }

    /**
     * Remove a callable from the collection
     *
     * @param callable $filter
     *
     * @return self
     */
    public function removeSortBy(callable $callable)
    {
        $res = array_search($callable, $this->sortBy, true);
        if (false !== $res) {
            unset($this->sortBy[$res]);
        }

        return $this;
    }

    /**
     * Detect if the callable is already registered
     *
     * @param callable $filter
     *
     * @return boolean
     */
    public function hasSortBy(callable $callable)
    {
        return false !== array_search($callable, $this->sortBy, true);
    }

    /**
     * Remove all registered callable
     *
     * @return self
     */
    public function clearSortBy()
    {
        $this->sortBy = [];

        return $this;
    }

    /**
    * Sort the Iterator
    *
    * @param \Iterator $iterator
    *
    * @return \ArrayIterator
    */
    protected function applySortBy(Iterator $iterator)
    {
        if (! $this->sortBy) {
            return $iterator;
        }
        $res = iterator_to_array($iterator, false);

        uasort($res, function ($rowA, $rowB) {
            foreach ($this->sortBy as $callable) {
                $res = $callable($rowA, $rowB);
                if (0 !== $res) {
                    break;
                }
            }

            return $res;
        });

        $this->clearSortBy();

        return new ArrayIterator($res);
    }
}
