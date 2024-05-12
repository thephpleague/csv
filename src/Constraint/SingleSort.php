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
 * Enable sorting a record based on the value of a one of its cell.
 *
 * The class can be used with PHP's usort and uasort functions.
 */
final class SingleSort implements Sort
{
    private const ASCENDING = 'ASC';
    private const DESCENDING = 'DESC';

    /**
     * @param Closure(mixed, mixed): int<-1, 1> $callback
     */
    private function __construct(
        public readonly string $direction,
        public readonly string|int $column,
        public readonly Closure $callback,
    ) {
    }

    /**
     * @param ?Closure(mixed, mixed): int<-1, 1> $callback
     */
    public static function new(
        string|int $column,
        string $direction,
        ?Closure $callback = null
    ): self {

        $operator = match (strtoupper(trim($direction))) {
            'ASC', 'ASCENDING', 'UP' => self::ASCENDING,
            'DESC', 'DESCENDING', 'DOWN' => self::DESCENDING,
            default => throw new InvalidArgument('Unknown operator or unsupported operator: '.$direction),
        };

        return new self(
            $operator,
            $column,
            $callback ?? static fn (mixed $first, mixed $second): int => $first <=> $second
        );
    }

    public function __invoke(array $row1, array $row2): int
    {
        $first = self::fieldValue($row1, $this->column);
        $second = self::fieldValue($row2, $this->column);

        return match ($this->direction) {
            self::ASCENDING => ($this->callback)($first, $second),
            default => ($this->callback)($second, $first),
        };
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
}
