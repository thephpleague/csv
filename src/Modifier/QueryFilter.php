<?php
/**
* This file is part of the League.csv library
*
* @license http://opensource.org/licenses/MIT
* @link https://github.com/thephpleague/csv/
* @version 8.1.1
* @package League.csv
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
namespace League\Csv\Modifier;

use ArrayIterator;
use CallbackFilterIterator;
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
     * @param bool $status
     *
     * @return $this
     */
    public function stripBom($status)
    {
        $this->strip_bom = (bool) $status;

        return $this;
    }

    /**
     * @inheritdoc
     */
    abstract public function getInputBOM();

    /**
     * Set LimitIterator Offset
     *
     * @param $offset
     *
     * @return $this
     */
    public function setOffset($offset = 0)
    {
        $this->iterator_offset = $this->validateInteger($offset, 0, 'the offset must be a positive integer or 0');

        return $this;
    }

    /**
     * @inheritdoc
     */
    abstract protected function validateInteger($int, $minValue, $errorMessage);

    /**
     * Set LimitIterator Count
     *
     * @param int $limit
     *
     * @return $this
     */
    public function setLimit($limit = -1)
    {
        $this->iterator_limit = $this->validateInteger($limit, -1, 'the limit must an integer greater or equals to -1');

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
     * @inheritdoc
     */
    abstract public function getEnclosure();

    /**
     * Returns the CSV Iterator
     *
     * @return Iterator
     */
    protected function getQueryIterator()
    {
        $normalizedCsv = function ($row) {
            return is_array($row) && $row != [null];
        };
        array_unshift($this->iterator_filters, $normalizedCsv);
        $iterator = $this->getIterator();
        $iterator = $this->applyBomStripping($iterator);
        $iterator = $this->applyIteratorFilter($iterator);
        $iterator = $this->applyIteratorSortBy($iterator);
        $iterator = $this->applyIteratorInterval($iterator);

        return $iterator;
    }

    /**
     * @inheritdoc
     */
    abstract public function getIterator();

    /**
     * Remove the BOM sequence from the CSV
     *
     * @param Iterator $iterator
     *
     * @return Iterator
     */
    protected function applyBomStripping(Iterator $iterator)
    {
        if (!$this->strip_bom) {
            return $iterator;
        }

        if (!$this->isBomStrippable()) {
            $this->strip_bom = false;

            return $iterator;
        }

        $this->strip_bom = false;

        return $this->getStripBomIterator($iterator);
    }

    /**
     * Tell whether we can strip or not the leading BOM sequence
     *
     * @return bool
     */
    protected function isBomStrippable()
    {
        return !empty($this->getInputBOM()) && $this->strip_bom;
    }

    /**
     * Return the Iterator without the BOM sequence
     *
     * @param Iterator $iterator
     *
     * @return Iterator
     */
    protected function getStripBomIterator(Iterator $iterator)
    {
        $bom_length = mb_strlen($this->getInputBOM());
        $enclosure = $this->getEnclosure();
        $strip_bom = function ($row, $index) use ($bom_length, $enclosure) {
            if (0 != $index) {
                return $row;
            }

            $row[0] = mb_substr($row[0], $bom_length);
            if (mb_substr($row[0], 0, 1) === $enclosure && mb_substr($row[0], -1, 1) === $enclosure) {
                $row[0] = mb_substr($row[0], 1, -1);
            }

            return $row;
        };

        return new MapIterator($iterator, $strip_bom);
    }

    /**
    * Filter the Iterator
    *
    * @param Iterator $iterator
    *
    * @return Iterator
    */
    protected function applyIteratorFilter(Iterator $iterator)
    {
        $reducer = function ($iterator, $callable) {
            return new CallbackFilterIterator($iterator, $callable);
        };
        $iterator = array_reduce($this->iterator_filters, $reducer, $iterator);
        $this->iterator_filters = [];

        return $iterator;
    }

    /**
    * Sort the Iterator
    *
    * @param Iterator $iterator
    *
    * @return Iterator
    */
    protected function applyIteratorSortBy(Iterator $iterator)
    {
        if (!$this->iterator_sort_by) {
            return $iterator;
        }

        $obj = new ArrayIterator(iterator_to_array($iterator));
        $obj->uasort(function ($row_a, $row_b) {
            $res = 0;
            foreach ($this->iterator_sort_by as $compare) {
                if (0 !== ($res = call_user_func($compare, $row_a, $row_b))) {
                    break;
                }
            }

            return $res;
        });
        $this->iterator_sort_by = [];

        return $obj;
    }

    /**
    * Sort the Iterator
    *
    * @param Iterator $iterator
    *
    * @return Iterator
    */
    protected function applyIteratorInterval(Iterator $iterator)
    {
        $offset = $this->iterator_offset;
        $limit = $this->iterator_limit;
        $this->iterator_limit = -1;
        $this->iterator_offset = 0;

        return new LimitIterator($iterator, $offset, $limit);
    }
}
