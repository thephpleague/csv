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

use OutOfBoundsException;

use function array_key_exists;
use function array_reduce;
use function array_search;
use function array_values;
use function is_string;

/**
 * Criteria to filter a {@link TabularDataReader} object.
 */
class Statement
{
    /** @var array<callable> Callables to filter the iterator. */
    protected array $where = [];
    /** @var array<callable> Callables to sort the iterator. */
    protected array $order_by = [];
    /** iterator Offset. */
    protected int $offset = 0;
    /** iterator maximum length. */
    protected int $limit = -1;
    /** @var array<string|int> */
    protected array $select = [];

    /**
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
     * Sets the Iterator element columns.
     */
    public function select(string|int ...$columns): self
    {
        if ($columns === $this->select) {
            return $this;
        }

        $clone = clone $this;
        $clone->select = $columns;

        return $clone;
    }

    /**
     * Sets the Iterator filter method.
     */
    public function where(callable $where): self
    {
        $clone = clone $this;
        $clone->where[] = $where;

        return $clone;
    }

    /**
     * Sets an Iterator sorting callable function.
     */
    public function orderBy(callable $order_by): self
    {
        $clone = clone $this;
        $clone->order_by[] = $order_by;

        return $clone;
    }

    /**
     * Sets LimitIterator Offset.
     *
     * @throws Exception if the offset is less than 0
     */
    public function offset(int $offset): self
    {
        if (0 > $offset) {
            throw InvalidArgument::dueToInvalidRecordOffset($offset, __METHOD__);
        }

        if ($offset === $this->offset) {
            return $this;
        }

        $clone = clone $this;
        $clone->offset = $offset;

        return $clone;
    }

    /**
     * Sets LimitIterator Count.
     *
     * @throws Exception if the limit is less than -1
     */
    public function limit(int $limit): self
    {
        if (-1 > $limit) {
            throw InvalidArgument::dueToInvalidLimit($limit, __METHOD__);
        }

        if ($limit === $this->limit) {
            return $this;
        }

        $clone = clone $this;
        $clone->limit = $limit;

        return $clone;
    }

    /**
     * Executes the prepared Statement on the {@link Reader} object.
     *
     * @param array<string> $header an optional header to use instead of the CSV document header
     *
     * @throws InvalidArgument
     * @throws SyntaxError
     */
    public function process(TabularDataReader $tabular_data, array $header = []): TabularDataReader
    {
        if ([] === $header) {
            $header = $tabular_data->getHeader();
        }

        $iterator = $tabular_data->getRecords($header);
        $iterator = $this->applyFilter($iterator);
        $iterator = $this->buildOrderBy($iterator);
        $iterator = new LimitIterator($iterator, $this->offset, $this->limit);

        return $this->applySelect($iterator, $header);
    }

    /**
     * Filters elements of an Iterator using a callback function.
     */
    protected function applyFilter(Iterator $iterator): Iterator
    {
        $filter = function (array $record, string|int $key): bool {
            foreach ($this->where as $where) {
                if (true !== $where($record, $key)) {
                    return false;
                }
            }

            return true;
        };

        return new CallbackFilterIterator($iterator, $filter);
    }

    /**
     * Sorts the Iterator.
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


        $class = new class () extends ArrayIterator {
            public function seek(int $offset): void
            {
                try {
                    parent::seek($offset);
                } catch (OutOfBoundsException) {
                    return;
                }
            }
        };

        /** @var ArrayIterator<array-key, array<string|null>> $it */
        $it = new $class([...$iterator]);
        $it->uasort($compare);

        return $it;
    }

    /**
     *
     * @throws InvalidArgument
     * @throws SyntaxError
     */
    protected function applySelect(Iterator $records, array $recordsHeader): TabularDataReader
    {
        if ([] === $this->select) {
            return new ResultSet($records, $recordsHeader);
        }

        $hasHeader = [] !== $recordsHeader;
        $selectColumn = function (array $header, string|int $field) use ($recordsHeader, $hasHeader): array {
            if (is_string($field)) {
                $index = array_search($field, $recordsHeader, true);
                if (false === $index) {
                    throw InvalidArgument::dueToInvalidColumnIndex($field, 'offset', __METHOD__);
                }

                $header[$index] = $field;

                return $header;
            }

            if ($hasHeader && !array_key_exists($field, $recordsHeader)) {
                throw InvalidArgument::dueToInvalidColumnIndex($field, 'offset', __METHOD__);
            }

            $header[$field] = $recordsHeader[$field] ?? $field;

            return $header;
        };

        /** @var array<string> $header */
        $header = array_reduce($this->select, $selectColumn, []);
        $records = new MapIterator($records, function (array $record) use ($header): array {
            $element = [];
            $row = array_values($record);
            foreach ($header as $offset => $headerName) {
                $element[$headerName] = $row[$offset] ?? null;
            }

            return $element;
        });

        return new ResultSet($records, $hasHeader ? $header : []);
    }

    /**
     * Filters elements of an Iterator using a callback function.
     *
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @see Statement::applyFilter()
     * @deprecated Since version 9.15.0
     * @codeCoverageIgnore
     */
    protected function filter(Iterator $iterator, callable $callable): CallbackFilterIterator
    {
        return new CallbackFilterIterator($iterator, $callable);
    }
}
