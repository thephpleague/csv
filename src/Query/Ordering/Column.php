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

namespace League\Csv\Query\Ordering;

use ArrayIterator;
use Closure;
use Iterator;
use League\Csv\Query\QueryException;
use League\Csv\Query\Row;
use League\Csv\Query\Sort;
use OutOfBoundsException;
use ReflectionException;
use SortDirection;

use function is_array;
use function is_string;
use function iterator_to_array;
use function strtoupper;
use function trim;

/**
 * Enable sorting a record based on the value of a one of its cell.
 */
final readonly class Column implements Sort
{
    public string $direction;

    /**
     * @param Closure(mixed, mixed): int $callback
     */
    private function __construct(
        SortDirection $direction,
        public string|int $column,
        public Closure $callback,
    ) {
        $this->direction = self::stringifyDirection($direction);
    }

    /**
     * @param (callable(mixed, mixed): int)|(Closure(mixed, mixed): int)|null $callback
     *
     * @throws QueryException
     */
    public static function sortOn(
        string|int $column,
        SortDirection|string|int $direction,
        Closure|callable|null $callback = null
    ): self {
        return new self(
            self::normalizeDirection($direction),
            $column,
            match (true) {
                null === $callback => static fn (mixed $first, mixed $second): int => $first <=> $second,
                $callback instanceof Closure => $callback,
                default => $callback(...),
            }
        );
    }

    /**
     * @throws QueryException
     */
    private static function normalizeDirection(SortDirection|string|int $direction): SortDirection
    {
        return match (true) {
            $direction instanceof SortDirection => $direction,
            SORT_ASC === $direction => SortDirection::Ascending,
            SORT_DESC === $direction => SortDirection::Descending,
            is_string($direction) => match (strtoupper(trim($direction))) {
                'ASC', 'ASCENDING', 'UP' => SortDirection::Ascending,
                'DESC', 'DESCENDING', 'DOWN' => SortDirection::Descending,
                default => throw new QueryException('Unknown or unsupported ordering operator value: '.$direction),
            },
            default => throw new QueryException('Unknown or unsupported ordering operator value: '.$direction),
        };
    }

    private static function stringifyDirection(SortDirection $direction): string
    {
        return match ($direction) {
            SortDirection::Ascending => 'ASC',
            SortDirection::Descending => 'DESC',
        };
    }

    /**
     * @throws ReflectionException
     * @throws QueryException
     */
    public function __invoke(mixed $valueA, mixed $valueB): int
    {
        $first = Row::from($valueA)->value($this->column);
        $second = Row::from($valueB)->value($this->column);

        return match ($this->direction) {
            'ASC' => ($this->callback)($first, $second),
            default => ($this->callback)($second, $first),
        };
    }

    public function sort(iterable $value): Iterator
    {
        $class = new class () extends ArrayIterator {
            public function seek(int $offset): void
            {
                try {
                    parent::seek($offset);
                } catch (OutOfBoundsException) {
                    return;
                }
            }
        };

        $it = new $class(!is_array($value) ? iterator_to_array($value) : $value);
        $it->uasort($this);

        return $it;
    }
}
