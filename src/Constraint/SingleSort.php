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
use League\Csv\StatementError;

final class SingleSort implements Sort
{
    private function __construct(
        public readonly Order $direction,
        public readonly string|int $column,
        public readonly Closure $callback,
    ) {
    }

    public static function new(
        string|int $column,
        Order|string $direction,
        ?Closure $callback = null
    ): self {
        if (!$direction instanceof Order) {
            $direction = Order::fromOperator($direction);
        }

        return new self($direction, $column, $callback ?? static fn (mixed $value): mixed => $value);
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

    public function __invoke(array $row1, array $row2): int
    {
        return match ($this->direction) {
            Order::Ascending => ($this->callback)(self::fieldValue($row1, $this->column)) <=> ($this->callback)(self::fieldValue($row2, $this->column)),
            Order::Descending => ($this->callback)(self::fieldValue($row2, $this->column)) <=> ($this->callback)(self::fieldValue($row1, $this->column)),
        };
    }

    public function constraint(): Closure
    {
        return $this(...);
    }
}
