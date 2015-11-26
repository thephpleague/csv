<?php
/**
* This file is part of the League.csv library
*
* @license http://opensource.org/licenses/MIT
* @link https://github.com/thephpleague/csv/
* @version 8.0.0
* @package League.csv
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
namespace League\Csv\Modifier;

use ArrayObject;
use CallbackFilterIterator;
use Iterator;
use League\Csv\AbstractCsv;
use LimitIterator;
use UnexpectedValueException;

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
     * Reader return type
     *
     * @var int
     */
    protected $returnType = AbstractCsv::TYPE_ARRAY;

    /**
     * Returns the return type for the next fetch call
     *
     * @return int
     */
    public function getReturnType()
    {
        return $this->returnType;
    }

    /**
     * Set the return type for the next fetch call
     *
     * @param int $type
     *
     * @throws UnexpectedValueException If the value is not one of the defined constant
     *
     * @return static
     */
    public function setReturnType($type)
    {
        $modes = [AbstractCsv::TYPE_ARRAY => 1, AbstractCsv::TYPE_ITERATOR => 1];
        if (!isset($modes[$type])) {
            throw new UnexpectedValueException('Unknown return type');
        }
        $this->returnType = $type;

        return $this;
    }

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
     * Tell whether we can strip or not the leading BOM sequence
     *
     * @return bool
     */
    protected function isBomStrippable()
    {
        $bom = $this->getInputBom();

        return ! empty($bom) && $this->strip_bom;
    }

    /**
     * {@inheritdoc}
     */
    abstract public function getInputBom();

    /**
     * Set LimitIterator Offset
     *
     * @param $offset
     *
     * @return $this
     */
    public function setOffset($offset = 0)
    {
        $this->iterator_offset = $this->filterInteger($offset, 0, 'the offset must be a positive integer or 0');

        return $this;
    }

    /**
     * @inheritdoc
     */
    abstract protected function filterInteger($int, $minValue, $errorMessage);

    /**
     * Set LimitIterator Count
     *
     * @param int $limit
     *
     * @return $this
     */
    public function setLimit($limit = -1)
    {
        $this->iterator_limit = $this->filterInteger($limit, -1, 'the limit must an integer greater or equals to -1');

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
        unset($this->iterator_sort_by[$res]);

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
        unset($this->iterator_filters[$res]);

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
     * @param Iterator $iterator
     *
     * @return Iterator
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

        return $this->getStripBomIterator($iterator);
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
        $bom = $this->getInputBom();

        return new MapIterator($iterator, function ($row, $index) use ($bom) {
            if (0 == $index) {
                $row[0] = mb_substr($row[0], mb_strlen($bom));
                $enclosure = $this->getEnclosure();
                //enclosure should be remove when a BOM sequence is stripped
                if ($row[0][0] === $enclosure && mb_substr($row[0], -1, 1) == $enclosure) {
                    $row[0] = mb_substr($row[0], 1, -1);
                }
            }

            return $row;
        });
    }

    /**
     * {@inheritdoc}
     */
    abstract public function getEnclosure();

    /**
    * Filter the Iterator
    *
    * @param Iterator $iterator
    *
    * @return Iterator
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
    * @param Iterator $iterator
    *
    * @return Iterator
    */
    protected function applyIteratorInterval(Iterator $iterator)
    {
        if (0 == $this->iterator_offset && -1 == $this->iterator_limit) {
            return $iterator;
        }
        $offset = $this->iterator_offset;
        $limit  = $this->iterator_limit;
        $this->iterator_limit  = -1;
        $this->iterator_offset = 0;

        return new LimitIterator($iterator, $offset, $limit);
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
        if (! $this->iterator_sort_by) {
            return $iterator;
        }
        $obj = new ArrayObject(iterator_to_array($iterator, false));
        $obj->uasort(function ($rowA, $rowB) {
            $sortRes = 0;
            foreach ($this->iterator_sort_by as $callable) {
                if (0 !== ($sortRes = call_user_func($callable, $rowA, $rowB))) {
                    break;
                }
            }

            return $sortRes;
        });
        $this->clearSortBy();

        return $obj->getIterator();
    }

    /**
     * Convert the Iterator into an array depending on the selected return type
     *
     * @param int      $type
     * @param Iterator $iterator
     * @param bool     $use_keys Whether to use the iterator element keys as index
     *
     * @return Iterator|array
     */
    protected function applyReturnType($type, Iterator $iterator, $use_keys = true)
    {
        if (AbstractCsv::TYPE_ARRAY == $type) {
            return iterator_to_array($iterator, $use_keys);
        }

        return $iterator;
    }
}
