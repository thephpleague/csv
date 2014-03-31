<?php
/**
* League.csv - A CSV data manipulation library
*
* @author Ignace Nyamagana Butera <nyamsprod@gmail.com>
* @copyright 2014 Ignace Nyamagana Butera
* @link https://github.com/nyamsprod/League.csv
* @license http://opensource.org/licenses/MIT
* @version 5.3.0
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

use CallbackFilterIterator;
use Iterator;

/**
 *  A Trait to filter Iterators
 *
 * @package League.csv
 * @since  4.2.1
 *
 */
trait IteratorFilter
{
    /**
     * Callable function to filter the iterator
     *
     * @var array
     */
    private $filter = [];

    /**
     * Set the Iterator filter method
     *
     * DEPRECATION WARNING! This method will be removed in the next major point release
     *
     * @deprecated deprecated since version 5.1
     *
     * @param callable $callable
     *
     * @return self
     */
    public function setFilter(callable $callable)
    {
        return $this->addFilter($callable);
    }

    /**
     * Set the Iterator filter method
     *
     * @param callable $filter
     *
     * @return self
     */
    public function addFilter(callable $callable)
    {
        $this->filter[] = $callable;

        return $this;
    }

    /**
     * Remove a filter from the callable collection
     *
     * @param callable $callable
     *
     * @return self
     */
    public function removeFilter(callable $callable)
    {
        $res = array_search($callable, $this->filter, true);
        if (false !== $res) {
            unset($this->filter[$res]);
        }

        return $this;
    }

    /**
     * Detect if the callable filter is already registered
     *
     * @param callable $callable
     *
     * @return boolean
     */
    public function hasFilter(callable $callable)
    {
        return false !== array_search($callable, $this->filter, true);
    }

    /**
     * Remove all registered callable filter
     *
     * @return self
     */
    public function clearFilter()
    {
        $this->filter = [];

        return $this;
    }

    /**
    * Filter the Iterator
    *
    * @param \Iterator $iterator
    *
    * @return \Iterator
    */
    protected function applyFilter(Iterator $iterator)
    {
        foreach ($this->filter as $callable) {
            $iterator = new CallbackFilterIterator($iterator, $callable);
        }
        $this->clearFilter();

        return $iterator;
    }
}
