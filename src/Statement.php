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
use Deprecated;
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
 * Criteria to filter a {@link TabularData} object.
 *
 * @phpstan-import-type ConditionExtended from Query\PredicateCombinator
 * @phpstan-import-type OrderingExtended from Query\SortCombinator
 */
class Statement
{
    final protected const COLUMN_ALL = 0;
    final protected const COLUMN_INCLUDE = 1;
    final protected const COLUMN_EXCLUDE = 2;

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
    /** @var self::COLUMN_* */
    protected int $select_mode = self::COLUMN_ALL;

    /**
     * Select all the columns from the tabular data that MUST BE present in the ResultSet.
     */
    public function select(string|int ...$columns): self
    {
        if ($columns === $this->select && self::COLUMN_INCLUDE === $this->select_mode) {
            return $this;
        }

        $clone = clone $this;
        $clone->select = $columns;
        $clone->select_mode = [] === $columns ? self::COLUMN_ALL : self::COLUMN_INCLUDE;

        return $clone;
    }

    /**
     * Select all the columns from the tabular data that MUST NOT BE present in the ResultSet.
     */
    public function selectAllExcept(string|int ...$columns): self
    {
        if ($columns === $this->select && self::COLUMN_EXCLUDE === $this->select_mode) {
            return $this;
        }

        $clone = clone $this;
        $clone->select = $columns;
        $clone->select_mode = [] === $columns ? self::COLUMN_ALL : self::COLUMN_EXCLUDE;

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
            0 => throw new InvalidArgument('The where condition must be callable with 2 required parameters.'),
            1 => fn (mixed $record, int $key) => $where($record),
            default => $where,
        };
    }

    public function andWhere(string|int $column, Query\Constraint\Comparison|Closure|callable|string $operator, mixed $value = null): self
    {
        return $this->appendWhere('and', Query\Constraint\Column::filterOn($column, $operator, $value));
    }

    public function orWhere(string|int $column, Query\Constraint\Comparison|Closure|callable|string $operator, mixed $value = null): self
    {
        return $this->appendWhere('or', Query\Constraint\Column::filterOn($column, $operator, $value));
    }

    public function whereNot(string|int $column, Query\Constraint\Comparison|Closure|callable|string $operator, mixed $value = null): self
    {
        return $this->appendWhere('not', Query\Constraint\Column::filterOn($column, $operator, $value));
    }

    public function xorWhere(string|int $column, Query\Constraint\Comparison|Closure|callable|string $operator, mixed $value = null): self
    {
        return $this->appendWhere('xor', Query\Constraint\Column::filterOn($column, $operator, $value));
    }

    public function andWhereColumn(string|int $first, Query\Constraint\Comparison|callable|string $operator, array|int|string $second): self
    {
        return $this->appendWhere('and', Query\Constraint\TwoColumns::filterOn($first, $operator, $second));
    }

    public function orWhereColumn(string|int $first, Query\Constraint\Comparison|callable|string $operator, array|int|string $second): self
    {
        return $this->appendWhere('or', Query\Constraint\TwoColumns::filterOn($first, $operator, $second));
    }

    public function xorWhereColumn(string|int $first, Query\Constraint\Comparison|callable|string $operator, array|int|string $second): self
    {
        return $this->appendWhere('xor', Query\Constraint\TwoColumns::filterOn($first, $operator, $second));
    }

    public function whereNotColumn(string|int $first, Query\Constraint\Comparison|callable|string $operator, array|int|string $second): self
    {
        return $this->appendWhere('not', Query\Constraint\TwoColumns::filterOn($first, $operator, $second));
    }

    public function andWhereOffset(Query\Constraint\Comparison|Closure|callable|string $operator, mixed $value = null): self
    {
        return $this->appendWhere('and', Query\Constraint\Offset::filterOn($operator, $value));
    }

    public function orWhereOffset(Query\Constraint\Comparison|Closure|callable|string $operator, mixed $value = null): self
    {
        return $this->appendWhere('or', Query\Constraint\Offset::filterOn($operator, $value));
    }

    public function xorWhereOffset(Query\Constraint\Comparison|Closure|callable|string $operator, mixed $value = null): self
    {
        return $this->appendWhere('xor', Query\Constraint\Offset::filterOn($operator, $value));
    }

    public function whereNotOffset(Query\Constraint\Comparison|Closure|callable|string $operator, mixed $value = null): self
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
    public function orderByAsc(string|int $column, callable|Closure|null $callback = null): self
    {
        return $this->orderBy(Query\Ordering\Column::sortOn($column, 'asc', $callback));
    }

    /**
     * Descending ordering of the tabular data according to a column value.
     *
     * The column value can be modified using the callback before ordering.
     */
    public function orderByDesc(string|int $column, callable|Closure|null $callback = null): self
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
     * Apply the callback if the given "condition" is (or resolves to) true.
     *
     * @param (callable($this): bool)|bool $condition
     * @param callable($this): (self|null) $onSuccess
     * @param ?callable($this): (self|null) $onFail
     */
    public function when(callable|bool $condition, callable $onSuccess, ?callable $onFail = null): self
    {
        if (!is_bool($condition)) {
            $condition = $condition($this);
        }

        return match (true) {
            $condition => $onSuccess($this),
            null !== $onFail => $onFail($this),
            default => $this,
        } ?? $this;
    }

    /**
     * Executes the prepared Statement on the {@link TabularData} object.
     *
     * @param array<string> $header an optional header to use instead of the tabular data header
     *
     * @throws InvalidArgument
     * @throws SyntaxError
     */
    public function process(TabularData $tabular_data, array $header = []): TabularDataReader
    {
        if (!$tabular_data instanceof TabularDataReader) {
            $tabular_data = ResultSet::from($tabular_data);
        }

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

        $iterator = new ResultSet($iterator, $header);

        return match ($this->select_mode) {
            self::COLUMN_EXCLUDE => $iterator->selectAllExcept(...$this->select),
            self::COLUMN_INCLUDE => $iterator->select(...$this->select),
            default => $iterator,
        };
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
    #[Deprecated(message:'this method no longer affects on the outcome of the class, use League\Csv\Statement::process() instead', since:'league/csv:9.16.0')]
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
    #[Deprecated(message:'this method no longer affects on the outcome of the class, use League\Csv\Statement::applyFilter() instead', since:'league/csv:9.15.0')]
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
    #[Deprecated(message:'this method no longer affects on the outcome of the class, use League\Csv\Statement::process() instead', since:'league/csv:9.16.0')]
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
    #[Deprecated(message:'this method no longer affects on the outcome of the class, use League\Csv\Statement::process() instead', since:'league/csv:9.16.0')]
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
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @param ?callable(array, array-key): bool $where , Deprecated argument use Statement::where instead
     * @param int $offset, Deprecated argument use Statement::offset instead
     * @param int $limit, Deprecated argument use Statement::limit instead
     *
     * @throws Exception
     * @throws InvalidArgument
     * @throws ReflectionException
     *
     * @see Statement::__construct()
     * @deprecated Since version 9.22.0
     * @codeCoverageIgnore
     */
    #[Deprecated(message:'use League\Csv\Statement::__construct() instead', since:'league/csv:9.22.0')]
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
}
