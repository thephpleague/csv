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
use function preg_match;
use function str_contains;
use function str_ends_with;
use function str_starts_with;

/**
 * Enable filtering a record based on the value of a one of its cell.
 *
 * When used with PHP's array_filter with the ARRAY_FILTER_USE_BOTH flag
 * the record offset WILL NOT BE taken into account
 */
final class ColumnValue implements Predicate
{
    /**
     * @throws InvalidArgument
     */
    private function __construct(
        public readonly string|int $column,
        public readonly Comparison $operator,
        public readonly mixed $value
    ) {
        match (true) {
            !is_string($this->value) && in_array($this->operator, [Comparison::Regexp, Comparison::StartsWith, Comparison::EndsWith, Comparison::Contains], true) => throw new InvalidArgument('The value used for comparison with the `'.$operator->name.'` operator must be a string.'),
            !is_array($this->value) && in_array($this->operator, [Comparison::In, Comparison::NotIn], true) => throw new InvalidArgument('The value used for comparison with the `'.$operator->name.'` operator must be an array.'),
            (!is_array($this->value) || !array_is_list($this->value) || 2 !== count($this->value)) && in_array($this->operator, [Comparison::Between, Comparison::NotBetween], true) => throw new InvalidArgument('The value used for comparison with the `'.$operator->name.'` operator must be an list containing 2 values, the range minimun and maximum values.'),
            default => null,
        };
    }

    public static function filterOn(
        string|int $column,
        Comparison|string $operator,
        mixed $value,
    ): self {
        if (!$operator instanceof Comparison) {
            $operator = Comparison::fromOperator($operator);
        }

        return new self($column, $operator, $value);
    }

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

    /**
     * @throws InvalidArgument
     * @throws StatementError
     */
    public function __invoke(array $record, int|string $key): bool
    {
        $filter = match ($this->operator) {
            Comparison::Equals => fn (array $record): bool => self::fieldValue($record, $this->column) === $this->value,
            Comparison::NotEquals => fn (array $record): bool => self::fieldValue($record, $this->column) !== $this->value,
            Comparison::GreaterThan => fn (array $record): bool => self::fieldValue($record, $this->column) > $this->value,
            Comparison::GreaterThanOrEqual => fn (array $record): bool => self::fieldValue($record, $this->column) >= $this->value,
            Comparison::LesserThan => fn (array $record): bool => self::fieldValue($record, $this->column) < $this->value,
            Comparison::LesserThanOrEqual => fn (array $record): bool => self::fieldValue($record, $this->column) <= $this->value,
            Comparison::Between => function (array $record): bool {
                $fieldValue = self::fieldValue($record, $this->column);

                return $fieldValue >= $this->value[0] && $fieldValue <= $this->value[1]; /* @phpstan-ignore-line */
            },
            Comparison::NotBetween => function (array $record): bool {
                $fieldValue = self::fieldValue($record, $this->column);

                return $fieldValue < $this->value[0] || $fieldValue > $this->value[1]; /* @phpstan-ignore-line */
            },
            Comparison::Regexp => function (array $record): bool {
                $fieldValue = self::fieldValue($record, $this->column);

                return is_string($fieldValue) && 1 === preg_match($this->value, $fieldValue); /* @phpstan-ignore-line */
            },
            Comparison::NotRegexp => function (array $record): bool {
                $fieldValue = self::fieldValue($record, $this->column);

                return is_string($fieldValue) && 1 !== preg_match($this->value, $fieldValue); /* @phpstan-ignore-line */
            },
            Comparison::In => function (array $record): bool {
                $fieldValue = self::fieldValue($record, $this->column);

                return in_array($fieldValue, $this->value, is_scalar($fieldValue)); /* @phpstan-ignore-line */
            },
            Comparison::NotIn => function (array $record): bool {
                $fieldValue = self::fieldValue($record, $this->column);

                return !in_array($fieldValue, $this->value, is_scalar($fieldValue));  /* @phpstan-ignore-line */
            },
            Comparison::Contains => function (array $record): bool {
                $fieldValue = self::fieldValue($record, $this->column);

                return is_string($fieldValue) && str_contains(self::fieldValue($record, $this->column), $this->value); /* @phpstan-ignore-line */
            },
            Comparison::NotContain => function (array $record): bool {
                $fieldValue = self::fieldValue($record, $this->column);

                return is_string($fieldValue) && !str_contains(self::fieldValue($record, $this->column), $this->value); /* @phpstan-ignore-line */
            },
            Comparison::StartsWith => function (array $record): bool {
                $fieldValue = self::fieldValue($record, $this->column);

                return is_string($fieldValue) && str_starts_with(self::fieldValue($record, $this->column), $this->value); /* @phpstan-ignore-line */
            },
            Comparison::EndsWith => function (array $record): bool {
                $fieldValue = self::fieldValue($record, $this->column);

                return is_string($fieldValue) && str_ends_with(self::fieldValue($record, $this->column), $this->value); /* @phpstan-ignore-line */
            },
        };

        return $filter($record);
    }
}
