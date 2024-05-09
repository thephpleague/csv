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

use function array_map;

/**
 * Enable sorting a record based on multiple column.
 *
 * The class can be used with PHP's usort and uasort functions.
 */
final class MultiSort implements SortCombinator
{
    /** @var array<Sort|Closure(array, array): int> */
    private readonly array $sorts;

    /**
     * @param Sort|Closure(array, array): int|callable(array, array): int ...$sorts
     */
    private function __construct(Sort|Closure|callable ...$sorts)
    {
        $this->sorts = array_map(self::callableToClosure(...), $sorts);
    }

    /**
     * @param Sort|Closure(array, array): int|callable(array, array): int ...$sorts
     */
    public static function new(Sort|Closure|callable ...$sorts): self
    {
        return new self(...$sorts);
    }

    private static function callableToClosure(Sort|Closure|callable $sort): Sort|Closure
    {
        if ($sort instanceof Closure || $sort instanceof Sort) {
            return $sort;
        }

        return $sort(...);
    }

    /**
     * @param Sort|Closure(array, array): int|callable(array, array): int ...$sorts
     */
    public function append(Sort|Closure|callable ...$sorts): self
    {
        if ([] === $sorts) {
            return $this;
        }

        return new self(...$this->sorts, ...$sorts);
    }

    /**
     * @param Sort|Closure(array, array): int|callable(array, array): int ...$sorts
     */
    public function prepend(Sort|Closure|callable ...$sorts): self
    {
        if ([] === $sorts) {
            return $this;
        }

        return (new self(...$sorts))->append(...$this->sorts);
    }

    public function __invoke(array $row1, array $row2): int
    {
        foreach ($this->sorts as $sort) {
            if (0 !== ($result = $sort($row1, $row2))) {
                return $result;
            }
        }

        return $result ?? 0;
    }
}
