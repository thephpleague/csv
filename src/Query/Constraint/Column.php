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

use ArrayIterator;
use CallbackFilterIterator;
use Iterator;
use IteratorIterator;
use League\Csv\Query\Predicate;
use League\Csv\Query\Select;
use League\Csv\InvalidArgument;
use League\Csv\StatementError;
use ReflectionException;

use function is_array;
use function array_filter;

use const ARRAY_FILTER_USE_BOTH;

/**
 * Enable filtering a record based on the value of a one of its cell.
 *
 * When used with PHP's array_filter with the ARRAY_FILTER_USE_BOTH flag
 * the record offset WILL NOT BE taken into account
 */
final class Column implements Predicate
{
    /**
     * @throws InvalidArgument
     */
    private function __construct(
        public readonly string|int $column,
        public readonly Comparison $operator,
        public readonly mixed $value,
    ) {
        if (!$this->operator->accept($this->value)) {
            throw new InvalidArgument('The value used for comparison with the `'.$this->operator->name.'` operator is not valid.');
        }
    }

    public static function filterOn(
        string|int $column,
        Comparison|string $operator,
        mixed $value,
    ): self {
        return new self(
            $column,
            !$operator instanceof Comparison ? Comparison::fromOperator($operator) : $operator,
            $value
        );
    }

    /**
     * @throws InvalidArgument
     * @throws ReflectionException
     * @throws StatementError
     */
    public function __invoke(mixed $value, int|string $key): bool
    {
        return $this->operator->compare(Select::one($value, $this->column), $this->value);
    }

    public function filter(iterable $value): Iterator
    {
        $value = match (true) {
            is_array($value) => new ArrayIterator($value),
            $value instanceof Iterator => $value,
            default => new IteratorIterator($value),
        };

        return new CallbackFilterIterator($value, $this);
    }

    public function filterArray(array $values): array
    {
        return array_filter($values, $this,ARRAY_FILTER_USE_BOTH);
    }
}
