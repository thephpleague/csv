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
use TypeError;
use function array_reduce;

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
     * Named Constructor to ease Statement instantiation.
     *
     * @throws Exception
     */
    public static function create(callable $where = null, int $offset = 0, int $limit = -1): self
    {
        $stmt = new self();
        if (null !== $where) {
            $stmt = $stmt->where($where);
        }

        return $stmt->offset($offset)->limit($limit);
    }

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
     * @param Reader|ResultSet $records
     * @param string[]         $header  an optional header to use instead of the CSV document header
     */
    public function process($records, array $header = []): ResultSet
    {
        if (!($records instanceof Reader) && !($records instanceof ResultSet)) {
            throw new TypeError(sprintf(
                '%s::parse expects parameter 1 to be a %s or a %s object, %s given',
                Statement::class,
                ResultSet::class,
                Reader::class,
                get_class($records)
            ));
        }

        if ([] === $header) {
            $header = $records->getHeader();
        }

        $iterator = $this->combineHeader($records, $header);
        $iterator = array_reduce($this->where, [$this, 'filter'], $iterator);
        $iterator = $this->buildOrderBy($iterator);

        return new ResultSet(new LimitIterator($iterator, $this->offset, $this->limit), $header);
    }

    /**
     * Combine the CSV header to each record if present.
     *
     * @param Reader|ResultSet $iterator
     * @param string[]         $header
     */
    protected function combineHeader($iterator, array $header): Iterator
    {
        if ($iterator instanceof Reader) {
            return $iterator->getRecords($header);
        }

        if ($header === $iterator->getHeader()) {
            return $iterator->getRecords();
        }

        $field_count = count($header);
        $mapper = static function (array $record) use ($header, $field_count): array {
            if (count($record) != $field_count) {
                $record = array_slice(array_pad($record, $field_count, null), 0, $field_count);
            }

            /** @var array<string|null> $assocRecord */
            $assocRecord = array_combine($header, $record);

            return $assocRecord;
        };

        return new MapIterator($iterator, $mapper);
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

        $it = new ArrayIterator();
        foreach ($iterator as $offset => $value) {
            $it[$offset] = $value;
        }
        $it->uasort($compare);

        return $it;
    }
}
