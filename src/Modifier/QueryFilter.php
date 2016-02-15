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
    protected $iteratorFilters = [];

    /**
     * Callables to sort the iterator
     *
     * @var callable[]
     */
    protected $iteratorSortBy = [];

    /**
     * iterator Offset
     *
     * @var int
     */
    protected $iteratorOffset = 0;

    /**
     * iterator maximum length
     *
     * @var int
     */
    protected $iteratorLimit = -1;

    /**
     * Stripping BOM status
     *
     * @var boolean
     */
    protected $stripBom = false;

    /**
     * Stripping BOM setter
     *
     * @param bool $status
     *
     * @return $this
     */
    public function stripBom($status)
    {
        $this->stripBom = (bool) $status;

        return $this;
    }

    /**
     * @inheritdoc
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
        $this->iteratorOffset = $this->validateInteger($offset, 0, 'the offset must be a positive integer or 0');

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
        $this->iteratorLimit = $this->validateInteger($limit, -1, 'the limit must an integer greater or equals to -1');

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
        $this->iteratorSortBy[] = $callable;

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
        $this->iteratorFilters[] = $callable;

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
        array_unshift($this->iteratorFilters, $normalizedCsv);
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
        if (!$this->stripBom) {
            return $iterator;
        }

        if (!$this->isBomStrippable()) {
            $this->stripBom = false;

            return $iterator;
        }

        $this->stripBom = false;

        return $this->getStripBomIterator($iterator);
    }

    /**
     * Tell whether we can strip or not the leading BOM sequence
     *
     * @return bool
     */
    protected function isBomStrippable()
    {
        return !empty($this->getInputBom()) && $this->stripBom;
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
        $bomLength = mb_strlen($this->getInputBom());
        $enclosure = $this->getEnclosure();
        $stripBom = function ($row, $index) use ($bomLength, $enclosure) {
            if (0 != $index) {
                return $row;
            }

            $row[0] = mb_substr($row[0], $bomLength);
            if ($row[0][0] === $enclosure && mb_substr($row[0], -1, 1) === $enclosure) {
                $row[0] = mb_substr($row[0], 1, -1);
            }

            return $row;
        };

        return new MapIterator($iterator, $stripBom);
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
        $iterator = array_reduce($this->iteratorFilters, $reducer, $iterator);
        $this->iteratorFilters = [];

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
        if (!$this->iteratorSortBy) {
            return $iterator;
        }

        $obj = new ArrayObject(iterator_to_array($iterator));
        $obj->uasort(function ($rowA, $rowB) {
            $res = 0;
            foreach ($this->iteratorSortBy as $compareRows) {
                if (0 !== ($res = call_user_func($compareRows, $rowA, $rowB))) {
                    break;
                }
            }

            return $res;
        });
        $this->iteratorSortBy = [];

        return $obj->getIterator();
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
        $offset = $this->iteratorOffset;
        $limit = $this->iteratorLimit;
        $this->iteratorLimit = -1;
        $this->iteratorOffset = 0;

        return new LimitIterator($iterator, $offset, $limit);
    }
}
