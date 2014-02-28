<?php
/**
* League.csv - A lightweight CSV Coder/Decoder library
*
* @author Ignace Nyamagana Butera <nyamsprod@gmail.com>
* @copyright 2014 Ignace Nyamagana Butera
* @link https://github.com/nyamsprod/League.csv
* @license http://opensource.org/licenses/MIT
* @version 5.0.0
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
    private $sortBy;

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
        uasort($res, $this->sortBy);
        $this->sortBy  = null;

        return new ArrayIterator($res);
    }
}
