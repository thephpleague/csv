<?php
/**
* Bakame.csv - A lightweight CSV Coder/Decoder library
*
* @author Ignace Nyamagana Butera <nyamsprod@gmail.com>
* @copyright 2014 Ignace Nyamagana Butera
* @link https://github.com/nyamsprod/Bakame.csv
* @license http://opensource.org/licenses/MIT
* @version 3.3.0
* @package Bakame.csv
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
namespace Bakame\Csv\Iterator;

use ArrayObject;
use CallbackFilterIterator;
use InvalidArgumentException;
use LimitIterator;

/**
 *  A Reader to ease CSV parsing in PHP 5.4+
 *
 * @package Bakame.csv
 * @since  3.3.0
 *
 */
abstract class AbstractIteratorFilter
{

    /**
     * iterator Offset
     *
     * @var integer
     */
    protected $offset = 0;

    /**
     * iterator maximum length
     *
     * @var integer
     */
    protected $limit = -1;

    /**
     * Callable function to filter the iterator
     *
     * @var callable
     */
    protected $filter;

    /**
     * Callable function to sort the ArrayObject
     *
     * @var callable
     */
    protected $sortBy;

    /**
     * Set the Iterator filter method
     *
     * @param callable $filter
     *
     * @return self
     */
    public function setFilter(callable $filter)
    {
        $this->filter = $filter;

        return $this;
    }

    /**
     * Set the ArrayObject sort method
     *
     * @param callable $sort
     *
     * @return self
     */
    public function setSortBy(callable $sortBy)
    {
        $this->sortBy = $sortBy;

        return $this;
    }

    /**
     * Set LimitIterator Offset
     *
     * @param $offset
     *
     * @return self
     */
    public function setOffset($offset)
    {
        if (false === filter_var($offset, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]])) {
            throw new InvalidArgumentException('the offset must be a positive integer or 0');
        }
        $this->offset = $offset;

        return $this;
    }

    /**
     * Set LimitInterator Count
     *
     * @param integer $limit
     *
     * @return self
     */
    public function setLimit($limit)
    {
        if (false === filter_var($limit, FILTER_VALIDATE_INT, ['options' => ['min_range' => -1]])) {
            throw new InvalidArgumentException('the limit must an integer greater or equals to -1');
        }
        $this->limit = $limit;

        return $this;
    }

    /**
     * Method that will return the Iterator to filter
     *
     * @return Iterator
     */
    abstract protected function prepare();

    /**
     * result from fetching data from the CSV file
     *
     * @return Iterator
     */
    public function query(callable $callable = null)
    {
        $iterator = $this->prepare();
        if ($this->filter) {
            $iterator = new CallbackFilterIterator($iterator, $this->filter);
            $this->filter = null;
        }

        if ($this->sortBy) {
            $res = new ArrayObject(iterator_to_array($iterator));
            $res->uasort($this->sortBy);
            $iterator = $res->getIterator();
            unset($res);
            $this->sortBy = null;
        }

        $offset = $this->offset;
        $limit = -1;
        if ($this->limit > 0) {
            $limit = $this->limit;
        }
        $this->limit = -1;
        $this->offset = 0;

        $iterator = new LimitIterator($iterator, $offset, $limit);
        if (! is_null($callable)) {
            $iterator = new MapIterator($iterator, $callable);
        }

        return $iterator;
    }
}
