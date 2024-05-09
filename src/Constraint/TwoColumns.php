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

use function array_filter;
use function array_is_list;
use function array_key_exists;
use function array_map;
use function array_values;
use function count;
use function is_array;
use function is_int;
use function is_string;

/**
 * Enable filtering a record by comparing the values of two of its column.
 */
final class TwoColumns implements Predicate
{
    /**
     * @throws StatementError
     */
    private function __construct(
        public readonly string|int $first,
        public readonly Comparison $operator,
        public readonly array|string|int $second,
    ) {
        if (is_array($this->second)) {
            $res = array_filter($this->second, fn (mixed $value): bool => !is_string($value) && !is_int($value));
            if ([] !== $res) {
                throw new StatementError('The second column must be a string, an integer or a list of strings and/or integer.');
            }
        }
    }

    /**
     * @throws InvalidArgument
     * @throws StatementError
     */
    public static function filterOn(
        string|int $firstColumn,
        Comparison|string $operator,
        array|string|int $secondColumn
    ): self {
        if (!$operator instanceof Comparison) {
            $operator = Comparison::fromOperator($operator);
        }

        return new self($firstColumn, $operator, $secondColumn);
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
     */
    public function __invoke(array $record, string|int $key): bool
    {
        $value = match (true) {
            is_array($this->second) => array_map(fn (string|int $column) => self::fieldValue($record, $column), $this->second),
            default => self::fieldValue($record, $this->second),
        };

        return ColumnValue::filterOn($this->first, $this->operator, $value)($record, $key);
    }
}
