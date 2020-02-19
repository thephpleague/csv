<?php

/**
 * League.Csv (https://csv.thephpleague.com)
 *
 * (c) Ignace Nyamagana Butera <nyamsprod@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace League\Csv;

use ArrayIterator;
use CallbackFilterIterator;
use Iterator;
use LimitIterator;
use function array_reduce;
use function iterator_to_array;

/**
 * Criteria to filter a {@link Reader} object.
 */
class Statement
{
    /**
     * Callables to filter the iterator.
     *
     * @var callable[]
     */
    protected $where = [];

    /**
     * Callables to sort the iterator.
     *
     * @var callable[]
     */
    protected $order_by = [];

    /**
     * iterator Offset.
     *
     * @var int
     */
    protected $offset = 0;

    /**
     * iterator maximum length.
     *
     * @var int
     */
    protected $limit = -1;

    /**
     * Set the Iterator filter method.
     */
    public function where(callable $callable): self
    {
        $clone = clone $this;
        $clone->where[] = $callable;

        return $clone;
    }

    /**
     * Set an Iterator sorting callable function.
     */
    public function orderBy(callable $callable): self
    {
        $clone = clone $this;
        $clone->order_by[] = $callable;

        return $clone;
    }

    /**
     * Set LimitIterator Offset.
     *
     * @throws Exception if the offset is lesser than 0
     */
    public function offset(int $offset): self
    {
        if (0 > $offset) {
            throw new InvalidArgument(sprintf('%s() expects the offset to be a positive integer or 0, %s given', __METHOD__, $offset));
        }

        if ($offset === $this->offset) {
            return $this;
        }

        $clone = clone $this;
        $clone->offset = $offset;

        return $clone;
    }

    /**
     * Set LimitIterator Count.
     *
     * @throws Exception if the limit is lesser than -1
     */
    public function limit(int $limit): self
    {
        if (-1 > $limit) {
            throw new InvalidArgument(sprintf('%s() expects the limit to be greater or equal to -1, %s given', __METHOD__, $limit));
        }

        if ($limit === $this->limit) {
            return $this;
        }

        $clone = clone $this;
        $clone->limit = $limit;

        return $clone;
    }

    /**
     * Execute the prepared Statement on the {@link Reader} object.
     *
     * @param string[] $header an optional header to use instead of the CSV document header
     */
    public function process(Reader $csv, array $header = []): ResultSet
    {
        if ([] === $header) {
            $header = $csv->getHeader();
        }

        $iterator = array_reduce($this->where, [$this, 'filter'], $csv->getRecords($header));
        $iterator = $this->buildOrderBy($iterator);

        return new ResultSet(new LimitIterator($iterator, $this->offset, $this->limit), $header);
    }

    /**
     * Filters elements of an Iterator using a callback function.
     */
    protected function filter(Iterator $iterator, callable $callable): CallbackFilterIterator
    {
        return new CallbackFilterIterator($iterator, $callable);
    }

    /**
     * Sort the Iterator.
     */
    protected function buildOrderBy(Iterator $iterator): Iterator
    {
        if ([] === $this->order_by) {
            return $iterator;
        }

        $compare = function (array $record_a, array $record_b): int {
            foreach ($this->order_by as $callable) {
                if (0 !== ($cmp = $callable($record_a, $record_b))) {
                    return $cmp;
                }
            }

            return $cmp ?? 0;
        };

        $iterator = new ArrayIterator(iterator_to_array($iterator));
        $iterator->uasort($compare);

        return $iterator;
    }
}
