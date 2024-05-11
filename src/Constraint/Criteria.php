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

use function array_reduce;

final class Criteria implements PredicateCombinator
{
    /**
     * @param Predicate|Closure(array, array-key): bool $predicate
     */
    private function __construct(private readonly Predicate|Closure $predicate)
    {
    }

    /**
     * Create a new instance with a single predicate.
     *
     * @param Predicate|Closure(array, array-key): bool|callable(array, array-key): bool $predicate
     */
    public static function one(Predicate|Closure|callable $predicate): self
    {
        return new self(
            $predicate instanceof Closure || $predicate instanceof Predicate ? $predicate : $predicate(...)
        );
    }

    /**
     * Creates a new instance with predicates join using the logical AND operator.
     *
     * @param Predicate|Closure(array, array-key): bool|callable(array, array-key): bool ...$predicates
     */
    public static function all(Predicate|Closure|callable ...$predicates): self
    {
        return new self(function (array $record, int|string $key) use ($predicates): bool {
            foreach ($predicates as $predicate) {
                if (true !== $predicate($record, $key)) {
                    return false;
                }
            }

            return true;
        });
    }

    /**
     * Creates a new instance with predicates join using the logical XOR operator.
     *
     * @param Predicate|Closure(array, array-key): bool|callable(array, array-key): bool ...$predicates
     */
    public static function some(Predicate|Closure|callable ...$predicates): self
    {
        return new self(fn (array $record, int|string $key): bool => array_reduce(
            $predicates,
            fn (bool $bool, Predicate|Closure|callable $predicate) => $predicate($record, $key) xor $bool,
            false
        ));
    }

    /**
     * Creates a new instance with predicates join using the logical OR operator.
     *
     * @param Predicate|Closure(array, array-key): bool|callable(array, array-key): bool ...$predicates
     */
    public static function any(Predicate|Closure|callable ...$predicates): self
    {
        return new self(function (array $record, int|string $key) use ($predicates): bool {
            foreach ($predicates as $predicate) {
                if (true === $predicate($record, $key)) {
                    return true;
                }
            }

            return [] === $predicates;
        });
    }

    /**
     * Creates a new instance with predicates join using the logical NOT operator.
     *
     * @param Predicate|Closure(array, array-key): bool|callable(array, array-key): bool ...$predicates
     */
    public static function none(Predicate|Closure|callable ...$predicates): self
    {
        return new self(function (array $record, int|string $key) use ($predicates): bool {
            foreach ($predicates as $predicate) {
                if (true === $predicate($record, $key)) {
                    return false;
                }
            }

            return true;
        });
    }

    public function __invoke(array $record, int|string $key): bool
    {
        return ($this->predicate)($record, $key);
    }

    /**
     * @param Predicate|Closure(array, array-key): bool|callable(array, array-key): bool ...$predicates
     */
    public function and(Predicate|Closure|callable ...$predicates): self
    {
        return self::all($this->predicate, ...$predicates);
    }

    /**
     * @param Predicate|Closure(array, array-key): bool|callable(array, array-key): bool ...$predicates
     */
    public function not(Predicate|Closure|callable ...$predicates): self
    {
        return self::none($this->predicate, ...$predicates);
    }

    /**
     * @param Predicate|Closure(array, array-key): bool|callable(array, array-key): bool ...$predicates
     */
    public function or(Predicate|Closure|callable ...$predicates): self
    {
        return self::any($this->predicate, ...$predicates);
    }

    /**
     * @param Predicate|Closure(array, array-key): bool|callable(array, array-key): bool ...$predicates
     */
    public function xor(Predicate|Closure|callable ...$predicates): self
    {
        return self::some($this->predicate, ...$predicates);
    }
}
