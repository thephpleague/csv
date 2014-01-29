<?php

namespace Bakame\Csv\Traits;

use ArrayObject;
use CallbackFilterIterator;
use InvalidArgumentException;
use LimitIterator;
use Bakame\Csv\Iterator\MapIterator;

trait IteratorQuery
{
    /**
     * iterator Offset
     *
     * @var integer
     */
    private $offset = 0;

    /**
     * iterator maximum length
     *
     * @var integer
     */
    private $limit = -1;

    /**
     * Callable function to filter the iterator
     *
     * @var callable
     */
    private $filter;

    /**
     * Callable function to sort the ArrayObject
     *
     * @var callable
     */
    private $sortBy;

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
