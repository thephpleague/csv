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
use function in_array;
use function is_array;
use function is_int;
use function is_string;

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
            self::isCallback($value) => $value,
            default => $this->validateValue($value),
        };
    }

    private static function isCallback(mixed $value): bool
    {
        return $value instanceof Closure
            || (is_object($value) && is_callable($value));
    }

    private function validateValue(mixed $value): mixed
    {
        return match (true) {
            !is_string($value) && in_array($this->operator, [Comparison::Regexp, Comparison::StartsWith, Comparison::EndsWith, Comparison::Contains], true) => throw new InvalidArgument('The value used for comparison with the `'.$this->operator->name.'` operator must be a string.'),
            !is_array($value) && in_array($this->operator, [Comparison::In, Comparison::NotIn], true) => throw new InvalidArgument('The value used for comparison with the `'.$this->operator->name.'` operator must be an array.'),
            (!is_array($value) || !array_is_list($value) || 2 !== count($value)) && in_array($this->operator, [Comparison::Between, Comparison::NotBetween], true) => throw new InvalidArgument('The value used for comparison with the `'.$this->operator->name.'` operator must be an list containing 2 values, the minimun and maximum values.'),
            default => $value,
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
        return $this->operator->compare(self::fieldValue($record, $this->column), $this->getValue($record, $key));
    }

    private function getValue(array $record, int|string $key): mixed
    {
        return self::isCallback($this->value) ?
            $this->validateValue(($this->value)($record, $key))  /* @phpstan-ignore-line */
            : $this->value;
    }

    /**
     * @throws StatementError
     */
    private static function fieldValue(array $array, string|int $key): mixed
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
