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

namespace League\Csv\Constraint;

use Closure;
use League\Csv\InvalidArgument;
use League\Csv\StatementError;

use function array_is_list;
use function array_key_exists;
use function array_values;
use function count;
use function is_int;

/**
 * Enable filtering a record based on the value of a one of its cell.
 *
 * When used with PHP's array_filter with the ARRAY_FILTER_USE_BOTH flag
 * the record offset WILL NOT BE taken into account
 */
final class ColumnValue implements Predicate
{
    public readonly mixed $value;

    /**
     * @throws InvalidArgument
     */
    private function __construct(
        public readonly string|int $column,
        public readonly Comparison $operator,
        mixed $value
    ) {
        $this->value = match (true) {
            $value instanceof Closure || (is_object($value) && is_callable($value)),
            $this->operator->accept($value) => $value,
            default => throw new InvalidArgument('The value used for comparison with the `'.$this->operator->name.'` operator is not valid.'),
        };
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
     * @throws StatementError
     */
    public function __invoke(array $record, int|string $key): bool
    {
        $first = self::value($record, $this->column);
        $second = $this->value;
        if ($second instanceof Closure || (is_object($second) && is_callable($second))) {
            $second = ($second)($record, $key);
        }

        return $this->operator->compare($first, $second);
    }

    /**
     * @throws StatementError
     */
    private static function value(array $array, string|int $key): mixed
    {
        $offset = $key;
        if (is_int($offset)) {
            if (!array_is_list($array)) {
                $array = array_values($array);
            }

            if ($offset < 0) {
                $offset += count($array);
            }
        }

        return array_key_exists($offset, $array) ? $array[$offset] : throw StatementError::dueToUnknownColumn($key);
    }
}
