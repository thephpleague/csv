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

namespace League\Csv\Query\Constraint;

use CallbackFilterIterator;
use Closure;
use Iterator;
use League\Csv\MapIterator;
use League\Csv\Query;

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
        Comparison|Closure|callable|string $operator,
        mixed $value = null,
    ): self {
        if ($operator instanceof Closure) {
            return new self($operator, null);
        }

        if (is_callable($operator)) {
            return new self(Closure::fromCallable($operator), $value);
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
        return new CallbackFilterIterator(MapIterator::toIterator($value), $this);
    }
}
