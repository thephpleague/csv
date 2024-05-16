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

namespace League\Csv\Ordering;

use Closure;
use League\Csv\Extract;
use League\Csv\InvalidArgument;
use League\Csv\StatementError;
use ReflectionException;

use function strtoupper;
use function trim;

/**
 * Enable sorting a record based on the value of a one of its cell.
 */
final class Column implements Sort
{
    private const ASCENDING = 'ASC';
    private const DESCENDING = 'DESC';

    /**
     * @param Closure(mixed, mixed): int $callback
     */
    private function __construct(
        public readonly string $direction,
        public readonly string|int $column,
        public readonly Closure $callback,
    ) {
    }

    /**
     * @param ?Closure(mixed, mixed): int $callback
     */
    public static function sortBy(
        string|int $column,
        string|int $direction,
        ?Closure $callback = null
    ): self {

        $operator = match (true) {
            SORT_ASC === $direction => self::ASCENDING,
            SORT_DESC === $direction => self::DESCENDING,
            is_string($direction) => match (strtoupper(trim($direction))) {
                'ASC', 'ASCENDING', 'UP' => self::ASCENDING,
                'DESC', 'DESCENDING', 'DOWN' => self::DESCENDING,
                default => throw new InvalidArgument('Unknown operator or unsupported operator: '.$direction),
            },
            default => throw new InvalidArgument('Unknown operator or unsupported operator: '.$direction),
        };

        return new self(
            $operator,
            $column,
            $callback ?? static fn (mixed $first, mixed $second): int => $first <=> $second
        );
    }

    /**
     * @throws ReflectionException
     * @throws StatementError
     */
    public function __invoke(mixed $valueA, mixed $valueB): int
    {
        $first = Extract::value($valueA, $this->column);
        $second = Extract::value($valueB, $this->column);

        return match ($this->direction) {
            self::ASCENDING => ($this->callback)($first, $second),
            default => ($this->callback)($second, $first),
        };
    }
}
