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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ComparisonTest extends TestCase
{
    #[Test]
    public function it_will_throw_if_the_comparison_operator_is_unknown(): void
    {
        $this->expectException(InvalidArgument::class);

        Comparison::fromOperator('foobar');
    }

    #[Test]
    #[DataProvider('provideValidComparisons')]
    public function it_can_compare_two_values(mixed $first, mixed $second, string $operator, bool $expected): void
    {
        self::assertSame($expected, Comparison::fromOperator($operator)->compare($first, $second));
    }

    #[Test]
    #[DataProvider('provideInvalidComparisons')]
    public function it_fails_to_compare_two_values(mixed $first, mixed $second, string $operator): void
    {
        $this->expectException(InvalidArgument::class);

        Comparison::fromOperator($operator)->compare($first, $second);
    }

    public static function provideInvalidComparisons(): iterable
    {
        yield 'between with not an array' => [
            'first' => 4,
            'second' => '3,5',
            'operator' => 'between',
        ];

        yield 'between without a list' => [
            'first' => 4,
            'second' =>  [3 => 3, 4 => 5],
            'operator' => 'between',
        ];

        yield 'between with a list without 2 members' => [
            'first' => 4,
            'second' =>  [3, 4, 5],
            'operator' => 'between',
        ];
        yield 'not between with not an array' => [
            'first' => 4,
            'second' => '3,5',
            'operator' => 'nbetween',
        ];

        yield 'not between without a list' => [
            'first' => 4,
            'second' =>  [3 => 3, 4 => 5],
            'operator' => 'nbetween',
        ];

        yield 'not between with a list without 2 members' => [
            'first' => 4,
            'second' =>  [3, 4, 5],
            'operator' => 'nbetween',
        ];

        yield 'regexp does not work with anything else but string' => [
            'first' => 'foobar',
            'second' =>  [3, 4, 5],
            'operator' => 'regexp',
        ];

        yield 'nregexp does not work with anything else but string' => [
            'first' => 'foobar',
            'second' =>  [3, 4, 5],
            'operator' => 'nregexp',
        ];

        yield 'contains does not work with anything else but string' => [
            'first' => 'foobar',
            'second' =>  [3, 4, 5],
            'operator' => 'contains',
        ];

        yield 'ends with does not work with anything else but string' => [
            'first' => 'foobar',
            'second' =>  [3, 4, 5],
            'operator' => 'ends with',
        ];

        yield 'starts with does not work with anything else but string' => [
            'first' => 'foobar',
            'second' =>  [3, 4, 5],
            'operator' => 'starts with',
        ];
    }

    public static function provideValidComparisons(): iterable
    {
        yield 'eq' => [
            'first' => 3,
            'second' => 3,
            'operator' => 'equals',
            'expected' => true,
        ];

        yield 'neq' => [
            'first' => 3,
            'second' => 4,
            'operator' => 'not equal',
            'expected' => true,
        ];

        yield 'lt' => [
            'first' => 3,
            'second' => 4,
            'operator' => 'lesser than',
            'expected' => true,
        ];

        yield 'gt' => [
            'first' => 4,
            'second' => 3,
            'operator' => 'greater than',
            'expected' => true,
        ];

        yield 'gte' => [
            'first' => 4,
            'second' => 3,
            'operator' => 'greater than or equal',
            'expected' => true,
        ];

        yield 'between' => [
            'first' => 4,
            'second' => [3, 5],
            'operator' => 'between',
            'expected' => true,
        ];

        yield 'not between' => [
            'first' => 7,
            'second' => [3, 5],
            'operator' => 'nbetween',
            'expected' => true,
        ];

        yield 'regexp' => [
            'first' => 'fOobar',
            'second' => '/oob/i',
            'operator' => 'regexp',
            'expected' => true,
        ];

        yield 'not regexp' => [
            'first' => 'fOobar',
            'second' => '/oob/',
            'operator' => 'nregexp',
            'expected' => true,
        ];

        yield 'in' => [
            'first' => 'toto',
            'second' => ['foo', 'toto', 'bar'],
            'operator' => 'in',
            'expected' => true,
        ];

        yield 'not in' => [
            'first' => 'toto',
            'second' => ['foo', 'bar', 'baz'],
            'operator' => 'not in',
            'expected' => true,
        ];

        yield 'contains' => [
            'first' => 'foObar',
            'second' => 'oOb',
            'operator' => 'contains',
            'expected' => true,
        ];

        yield 'not contains' => [
            'first' => 'foObar',
            'second' => 'oob',
            'operator' => 'not contain',
            'expected' => true,
        ];

        yield 'starts with' => [
            'first' => 'foObar',
            'second' => 'foO',
            'operator' => 'starts with',
            'expected' => true,
        ];

        yield 'starts with false' => [
            'first' => 'foObar',
            'second' => 'foo',
            'operator' => 'starts with',
            'expected' => false,
        ];

        yield 'ends with' => [
            'first' => 'foObar',
            'second' => 'Obar',
            'operator' => 'ends with',
            'expected' => true,
        ];

        yield 'ends with false' => [
            'first' => 'foObar',
            'second' => 'obar',
            'operator' => 'ends with',
            'expected' => false,
        ];
    }
}
