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
use League\Csv\Query\Predicate;
use League\Csv\Query\QueryException;
use League\Csv\Query\Row;
use ReflectionException;

use function array_filter;
use function is_array;
use function is_int;
use function is_string;

use const ARRAY_FILTER_USE_BOTH;

/**
 * Enable filtering a record by comparing the values of two of its column.
 *
 * When used with PHP's array_filter with the ARRAY_FILTER_USE_BOTH flag
 * the record offset WILL NOT BE taken into account
 */
final class TwoColumns implements Predicate
{
    /**
     * @throws QueryException
     */
    private function __construct(
        public readonly string|int $first,
        public readonly Comparison|Closure $operator,
        public readonly array|string|int $second,
    ) {
        !$this->operator instanceof Closure || !is_array($this->second) || throw new QueryException('The second column must be a string if the operator is a callback.');

        if (is_array($this->second)) {
            $res = array_filter($this->second, fn (mixed $value): bool => !is_string($value) && !is_int($value));
            if ([] !== $res) {
                throw new QueryException('The second column must be a string, an integer or a list of strings and/or integer when the operator is not a callback.');
            }
        }
    }

    /**
     * @throws QueryException
     */
    public static function filterOn(
        string|int $firstColumn,
        Comparison|Closure|string $operator,
        array|string|int $secondColumn
    ): self {
        if (is_string($operator)) {
            $operator = Comparison::fromOperator($operator);
        }

        return new self($firstColumn, $operator, $secondColumn);
    }

    /**
     * @throws QueryException
     * @throws ReflectionException
     */
    public function __invoke(mixed $value, int|string $key): bool
    {
        $val = match (true) {
            is_array($this->second) => array_values(Row::from($value)->select(...$this->second)),
            default => Row::from($value)->value($this->second),
        };

        if ($this->operator instanceof Closure) {
            return ($this->operator)(Row::from($value)->value($this->first), $val);
        }

        return Column::filterOn($this->first, $this->operator, $val)($value, $key);
    }

    public function filter(iterable $value): Iterator
    {
        return new CallbackFilterIterator(MapIterator::toIterator($value), $this);
    }
}
