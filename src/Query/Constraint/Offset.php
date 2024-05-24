<?php

declare(strict_types=1);

namespace League\Csv\Query\Constraint;

use ArrayIterator;
use CallbackFilterIterator;
use Closure;
use Iterator;
use IteratorIterator;
use League\Csv\Query;
use Traversable;

/**
 * Enable filtering a record based on its offset.
 *
 * When used with PHP's array_filter with the ARRAY_FILTER_USE_BOTH flag
 * the record value WILL NOT BE taken into account
 */
final class Offset implements Query\Predicate
{
    /**
     * @throws Query\QueryException
     */
    private function __construct(
        public readonly Comparison|Closure $operator,
        public readonly mixed $value,
    ) {
        if (!$this->operator instanceof Closure) {
            $this->operator->accept($this->value);
        }
    }

    /**
     * @throws Query\QueryException
     */
    public static function filterOn(
        Comparison|Closure|string $operator,
        mixed $value = null,
    ): self {
        if ($operator instanceof Closure) {
            return new self($operator, null);
        }

        return new self(
            is_string($operator) ? Comparison::fromOperator($operator) : $operator,
            $value
        );
    }

    /**
     * @throws Query\QueryException
     */
    public function __invoke(mixed $value, int|string $key): bool
    {
        if ($this->operator instanceof Closure) {
            return ($this->operator)($key);
        }

        return $this->operator->compare($key, $this->value);
    }

    public function filter(iterable $value): Iterator
    {
        return new CallbackFilterIterator(match (true) {
            $value instanceof Iterator => $value,
            $value instanceof Traversable => new IteratorIterator($value),
            default => new ArrayIterator($value),
        }, $this);
    }
}
