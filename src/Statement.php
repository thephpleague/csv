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
use Closure;
use Iterator;
use OutOfBoundsException;
use ReflectionException;
use ReflectionFunction;

use function array_key_exists;
use function array_reduce;
use function array_search;
use function array_values;
use function is_string;

/**
 * Criteria to filter a {@link TabularDataReader} object.
 *
 * @phpstan-import-type ConditionExtended from Query\PredicateCombinator
 * @phpstan-import-type OrderingExtended from Query\SortCombinator
 */
class Statement
{
    /** @var array<ConditionExtended> Callables to filter the iterator. */
    protected array $where = [];
    /** @var array<OrderingExtended> Callables to sort the iterator. */
    protected array $order_by = [];
    /** iterator Offset. */
    protected int $offset = 0;
    /** iterator maximum length. */
    protected int $limit = -1;
    /** @var array<string|int> */
    protected array $select = [];

    /**
     * @param ?callable(array, array-key): bool $where, Deprecated argument use Statement::where instead
     * @param int $offset, Deprecated argument use Statement::offset instead
     * @param int $limit, Deprecated argument use Statement::limit instead
     *
     * @throws Exception
     * @throws InvalidArgument
     * @throws ReflectionException
     */
    public static function create(?callable $where = null, int $offset = 0, int $limit = -1): self
    {
        $stmt = new self();
        if (null !== $where) {
            $stmt = $stmt->where($where);
        }

        if (0 !== $offset) {
            $stmt = $stmt->offset($offset);
        }

        if (-1 !== $limit) {
            $stmt = $stmt->limit($limit);
        }

        return $stmt;
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
     *
     * @param callable(array, array-key): bool $where
     *
     * @throws ReflectionException
     * @throws InvalidArgument
     */
    public function where(callable $where): self
    {
        $where = self::wrapSingleArgumentCallable($where);

        $clone = clone $this;
        $clone->where[] = $where;

        return $clone;
    }

    /**
     * Sanitize the number of required parameters for a predicate.
     *
     * To avoid BC break in 9.16+ version the predicate should have
     * at least 1 required argument.
     *
     * @throws InvalidArgument
     * @throws ReflectionException
     *
     * @return ConditionExtended
     */
    final protected static function wrapSingleArgumentCallable(callable $where): callable
    {
        if ($where instanceof Query\Predicate) {
            return $where;
        }

        $reflection = new ReflectionFunction($where instanceof Closure ? $where : $where(...));

        return match ($reflection->getNumberOfRequiredParameters()) {
            0 => throw new InvalidArgument('The where condition must be a callable with 2 required parameters.'),
            1 => fn (mixed $record, int $key) => $where($record),
            default => $where,
        };
    }

    public function andWhere(string|int $column, Query\Constraint\Comparison|Closure|string $operator, mixed $value = null): self
    {
        return $this->appendWhere('and', Query\Constraint\Column::filterOn($column, $operator, $value));
    }

    public function orWhere(string|int $column, Query\Constraint\Comparison|Closure|string $operator, mixed $value = null): self
    {
        return $this->appendWhere('or', Query\Constraint\Column::filterOn($column, $operator, $value));
    }

    public function whereNot(string|int $column, Query\Constraint\Comparison|Closure|string $operator, mixed $value = null): self
    {
        return $this->appendWhere('not', Query\Constraint\Column::filterOn($column, $operator, $value));
    }

    public function xorWhere(string|int $column, Query\Constraint\Comparison|Closure|string $operator, mixed $value = null): self
    {
        return $this->appendWhere('xor', Query\Constraint\Column::filterOn($column, $operator, $value));
    }

    public function andWhereColumn(string|int $first, Query\Constraint\Comparison|string $operator, array|int|string $second): self
    {
        return $this->appendWhere('and', Query\Constraint\TwoColumns::filterOn($first, $operator, $second));
    }

    public function orWhereColumn(string|int $first, Query\Constraint\Comparison|string $operator, array|int|string $second): self
    {
        return $this->appendWhere('or', Query\Constraint\TwoColumns::filterOn($first, $operator, $second));
    }

    public function xorWhereColumn(string|int $first, Query\Constraint\Comparison|string $operator, array|int|string $second): self
    {
        return $this->appendWhere('xor', Query\Constraint\TwoColumns::filterOn($first, $operator, $second));
    }

    public function whereNotColumn(string|int $first, Query\Constraint\Comparison|string $operator, array|int|string $second): self
    {
        return $this->appendWhere('not', Query\Constraint\TwoColumns::filterOn($first, $operator, $second));
    }

    public function andWhereOffset(Query\Constraint\Comparison|Closure|string $operator, mixed $value = null): self
    {
        return $this->appendWhere('and', Query\Constraint\Offset::filterOn($operator, $value));
    }

    public function orWhereOffset(Query\Constraint\Comparison|Closure|string $operator, mixed $value = null): self
    {
        return $this->appendWhere('or', Query\Constraint\Offset::filterOn($operator, $value));
    }

    public function xorWhereOffset(Query\Constraint\Comparison|Closure|string $operator, mixed $value = null): self
    {
        return $this->appendWhere('xor', Query\Constraint\Offset::filterOn($operator, $value));
    }

    public function whereNotOffset(Query\Constraint\Comparison|Closure|string $operator, mixed $value = null): self
    {
        return $this->appendWhere('not', Query\Constraint\Offset::filterOn($operator, $value));
    }

    /**
     * @param 'and'|'not'|'or'|'xor' $joiner
     */
    final protected function appendWhere(string $joiner, Query\Predicate $predicate): self
    {
        if ([] === $this->where) {
            return $this->where(match ($joiner) {
                'and' => $predicate,
                'not' => Query\Constraint\Criteria::none($predicate),
                'or' => Query\Constraint\Criteria::any($predicate),
                'xor' => Query\Constraint\Criteria::xany($predicate),
            });
        }

        $predicates = Query\Constraint\Criteria::all(...$this->where);

        $clone = clone $this;
        $clone->where = [match ($joiner) {
            'and' => $predicates->and($predicate),
            'not' => $predicates->not($predicate),
            'or' => $predicates->or($predicate),
            'xor' => $predicates->xor($predicate),
        }];

        return $clone;
    }

    /**
     * Sets an Iterator sorting callable function.
     *
     * @param OrderingExtended $order_by
     */
    public function orderBy(callable|Query\Sort|Closure $order_by): self
    {
        $clone = clone $this;
        $clone->order_by[] = $order_by;

        return $clone;
    }

    /**
     * Ascending ordering of the tabular data according to a column value.
     *
     * The column value can be modified using the callback before ordering.
     */
    public function orderByAsc(string|int $column, ?Closure $callback = null): self
    {
        return $this->orderBy(Query\Ordering\Column::sortOn($column, 'asc', $callback));
    }

    /**
     * Descending ordering of the tabular data according to a column value.
     *
     * The column value can be modified using the callback before ordering.
     */
    public function orderByDesc(string|int $column, ?Closure $callback = null): self
    {
        return $this->orderBy(Query\Ordering\Column::sortOn($column, 'desc', $callback));
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
        $limit >= -1 || throw InvalidArgument::dueToInvalidLimit($limit, __METHOD__);
        if ($limit === $this->limit) {
            return $this;
        }

        $clone = clone $this;
        $clone->limit = $limit;

        return $clone;
    }

    /**
     * Executes the prepared Statement on the {@link TabularDataReader} object.
     *
     * @param array<string> $header an optional header to use instead of the tabular data header
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
        if ([] !== $this->where) {
            $iterator = Query\Constraint\Criteria::all(...$this->where)->filter($iterator);
        }

        if ([] !== $this->order_by) {
            $iterator = Query\Ordering\MultiSort::all(...$this->order_by)->sort($iterator);
        }

        if (0 !== $this->offset || -1 !== $this->limit) {
            $iterator = Query\Limit::new($this->offset, $this->limit)->slice($iterator);
        }

        return (new ResultSet($iterator, $header))->select(...$this->select);
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @throws InvalidArgument
     *
     * @throws SyntaxError
     * @see Statement::process()
     * @deprecated Since version 9.16.0
     */
    protected function applySelect(Iterator $records, array $recordsHeader, array $select): TabularDataReader
    {
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
        $header = array_reduce($select, $selectColumn, []);
        $callback = function (array $record) use ($header): array {
            $element = [];
            $row = array_values($record);
            foreach ($header as $offset => $headerName) {
                $element[$headerName] = $row[$offset] ?? null;
            }

            return $element;
        };

        return new ResultSet(new MapIterator($records, $callback), $hasHeader ? $header : []);
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

    /**
     * Filters elements of an Iterator using a callback function.
     *
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @see Statement::process()
     * @deprecated Since version 9.16.0
     * @codeCoverageIgnore
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
     *
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @see Statement::process()
     * @deprecated Since version 9.16.0
     * @codeCoverageIgnore
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
}
