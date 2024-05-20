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

namespace League\Csv\Query\Constraint;

use League\Csv\Query\QueryException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ComparisonTest extends TestCase
{
    #[Test]
    public function it_will_throw_if_the_comparison_operator_is_unknown(): void
    {
        $this->expectException(QueryException::class);

        Comparison::fromOperator('foobar');
    }

    #[Test]
    #[DataProvider('provideValidComparisons')]
    public function it_can_compare_two_values(mixed $needle, mixed $haystack, string $operator, bool $expected): void
    {
        self::assertSame($expected, Comparison::fromOperator($operator)->compare($needle, $haystack));
    }

    #[Test]
    #[DataProvider('provideInvalidComparisons')]
    public function it_fails_to_compare_two_values(mixed $needle, mixed $haystack, string $operator): void
    {
        $this->expectException(QueryException::class);

        Comparison::fromOperator($operator)->compare($needle, $haystack);
    }

    public static function provideInvalidComparisons(): iterable
    {
        yield 'between with not an array' => [
            'needle' => 4,
            'haystack' => '3,5',
            'operator' => 'between',
        ];

        yield 'between without a list' => [
            'needle' => 4,
            'haystack' =>  [3 => 3, 4 => 5],
            'operator' => 'between',
        ];

        yield 'between with a list without 2 members' => [
            'needle' => 4,
            'haystack' =>  [3, 4, 5],
            'operator' => 'between',
        ];
        yield 'not between with not an array' => [
            'needle' => 4,
            'haystack' => '3,5',
            'operator' => 'nbetween',
        ];

        yield 'not between without a list' => [
            'needle' => 4,
            'haystack' =>  [3 => 3, 4 => 5],
            'operator' => 'nbetween',
        ];

        yield 'not between with a list without 2 members' => [
            'needle' => 4,
            'haystack' =>  [3, 4, 5],
            'operator' => 'nbetween',
        ];

        yield 'regexp does not work with anything else but string' => [
            'needle' => 'foobar',
            'haystack' =>  [3, 4, 5],
            'operator' => 'regexp',
        ];

        yield 'nregexp does not work with anything else but string' => [
            'needle' => 'foobar',
            'haystack' =>  [3, 4, 5],
            'operator' => 'nregexp',
        ];

        yield 'contains does not work with anything else but string' => [
            'needle' => 'foobar',
            'haystack' =>  [3, 4, 5],
            'operator' => 'contains',
        ];

        yield 'ends with does not work with anything else but string' => [
            'needle' => 'foobar',
            'haystack' =>  [3, 4, 5],
            'operator' => 'ends with',
        ];

        yield 'starts with does not work with anything else but string' => [
            'needle' => 'foobar',
            'haystack' =>  [3, 4, 5],
            'operator' => 'starts with',
        ];
    }

    public static function provideValidComparisons(): iterable
    {
        yield 'eq' => [
            'needle' => 3,
            'haystack' => 3,
            'operator' => 'equals',
            'expected' => true,
        ];

        yield 'neq' => [
            'needle' => 3,
            'haystack' => 4,
            'operator' => 'not equal',
            'expected' => true,
        ];

        yield 'lt' => [
            'needle' => 3,
            'haystack' => 4,
            'operator' => 'lesser than',
            'expected' => true,
        ];

        yield 'gt' => [
            'needle' => 4,
            'haystack' => 3,
            'operator' => 'greater than',
            'expected' => true,
        ];

        yield 'gte' => [
            'needle' => 4,
            'haystack' => 3,
            'operator' => 'greater than or equal',
            'expected' => true,
        ];

        yield 'between' => [
            'needle' => 4,
            'haystack' => [3, 5],
            'operator' => 'between',
            'expected' => true,
        ];

        yield 'not between' => [
            'needle' => 7,
            'haystack' => [3, 5],
            'operator' => 'nbetween',
            'expected' => true,
        ];

        yield 'regexp' => [
            'needle' => 'fOobar',
            'haystack' => '/oob/i',
            'operator' => 'regexp',
            'expected' => true,
        ];

        yield 'not regexp' => [
            'needle' => 'fOobar',
            'haystack' => '/oob/',
            'operator' => 'nregexp',
            'expected' => true,
        ];

        yield 'in' => [
            'needle' => 'toto',
            'haystack' => ['foo', 'toto', 'bar'],
            'operator' => 'in',
            'expected' => true,
        ];

        yield 'not in' => [
            'needle' => 'toto',
            'haystack' => ['foo', 'bar', 'baz'],
            'operator' => 'not in',
            'expected' => true,
        ];

        yield 'contains' => [
            'needle' => 'foObar',
            'haystack' => 'oOb',
            'operator' => 'contains',
            'expected' => true,
        ];

        yield 'not contains' => [
            'needle' => 'foObar',
            'haystack' => 'oob',
            'operator' => 'not contain',
            'expected' => true,
        ];

        yield 'starts with' => [
            'needle' => 'foObar',
            'haystack' => 'foO',
            'operator' => 'starts with',
            'expected' => true,
        ];

        yield 'starts with false' => [
            'needle' => 'foObar',
            'haystack' => 'foo',
            'operator' => 'starts with',
            'expected' => false,
        ];

        yield 'ends with' => [
            'needle' => 'foObar',
            'haystack' => 'Obar',
            'operator' => 'ends with',
            'expected' => true,
        ];

        yield 'ends with false' => [
            'needle' => 'foObar',
            'haystack' => 'obar',
            'operator' => 'ends with',
            'expected' => false,
        ];
    }
}
