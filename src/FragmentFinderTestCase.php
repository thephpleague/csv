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

namespace League\Csv;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('tabulardata')]
abstract class FragmentFinderTestCase extends TestCase
{
    abstract protected function getFragmentIdentifierTabularData(): TabularDataReader;

    #[Test]
    #[DataProvider('provideValidExpressions')]
    public function it_can_select_a_specific_fragment(string $expression, ?array $expected): void
    {
        $result = $this->getFragmentIdentifierTabularData()->firstMatching($expression);
        if (null === $expected) {
            self::assertNull($result);

            return;
        }

        self::assertSame($expected, [...$result]); /* @phpstan-ignore-line */
    }

    #[Test]
    #[DataProvider('provideValidExpressions')]
    public function it_can_select_a_specific_fragment_or_fail(string $expression, ?array $expected): void
    {
        if (null === $expected) {
            $this->expectException(SyntaxError::class);

            $this->getFragmentIdentifierTabularData()->firstOrFailMatching($expression);

            return;
        }

        self::assertSame($expected, [...$this->getFragmentIdentifierTabularData()->firstOrFailMatching($expression)]);
    }

    public static function provideValidExpressions(): iterable
    {
        yield 'single row' => [
            'expression' => 'row=4',
            'expected' => [
                0 => ['2011-01-03', '0', 'Galway'],
            ],
        ];

        yield 'single row is case insensitive' => [
            'expression' => 'RoW=4',
            'expected' => [
                0 => ['2011-01-03', '0', 'Galway'],
            ],
        ];

        yield 'row range' =>  [
            'expression' => 'row=5-7',
            'expected' => [
                0 => ['2011-01-01', '6', 'Berkeley'],
                1 => ['2011-01-02', '8', 'Berkeley'],
                2 => ['2011-01-03', '5', 'Berkeley'],
            ],
        ];

        yield 'all remaining rows' =>  [
            'expression' => 'row=5-*',
            'expected' => [
                0 => ['2011-01-01', '6', 'Berkeley'],
                1 => ['2011-01-02', '8', 'Berkeley'],
                2 => ['2011-01-03', '5', 'Berkeley'],
            ],
        ];

        yield 'single column' => [
            'expression' => 'col=2',
            'expected' => [
                0 => [1 => 'temperature'],
                1 => [1 => '1'],
                2 => [1 => '-1'],
                3 => [1 => '0'],
                4 => [1 => '6'],
                5 => [1 => '8'],
                6 => [1 => '5'],
            ],
        ];

        yield 'column range' =>  [
            'expression' => 'col=1-2',
            'expected' => [
                0 => ['date', 'temperature'],
                1 => ['2011-01-01', '1'],
                2 => ['2011-01-02', '-1'],
                3 => ['2011-01-03', '0'],
                4 => ['2011-01-01', '6'],
                5 => ['2011-01-02', '8'],
                6 => ['2011-01-03', '5'],
            ],
        ];

        yield 'single cell selection' =>  [
            'expression' => 'cell=4,1',
            'expected' => [
                0 => ['2011-01-03'],
            ],
        ];

        yield 'single range selection' =>  [
            'expression' => 'cell=4,1-6,2',
            'expected' => [
                0 => ['2011-01-03', '0'],
                1 => ['2011-01-01', '6'],
                2 => ['2011-01-02', '8'],
            ],
        ];

        yield 'single range selection without end limit' =>  [
            'expression' => 'cell=5,2-*',
            'expected' => [
                0 => [1 => '6', 2 => 'Berkeley'],
                1 => [1 => '8', 2 => 'Berkeley'],
                2 => [1 => '5', 2 => 'Berkeley'],
            ],
        ];

        yield 'row range is inverted' => [
            'expression' => 'row=4-2',
            'expected' => null,
        ];

        yield 'column range is inverted' => [
            'expression' => 'col=4-2',
            'expected' => null,
        ];

        yield 'cell range is inverted' => [
            'expression' => 'cell=3,3-2,2',
            'expected' => null,
        ];

        yield 'cell range is out of range for the tabular reader data' => [
            'expression' => 'cell=3,3-30,56',
            'expected' => [
                0 => [2 => 'Galway'],
                1 => [2 => 'Galway'],
                2 => [2 => 'Berkeley'],
                3 => [2 => 'Berkeley'],
                4 => [2 => 'Berkeley'],
            ],
        ];

        yield 'single cell out of the tabular data' => [
            'expression' => 'cell=48,12',
            'expected' => null,
        ];
    }

    #[Test]
    #[DataProvider('provideInvalidExpressions')]
    public function it_will_return_null_on_invalid_expression(string $expression): void
    {
        self::assertNull($this->getFragmentIdentifierTabularData()->firstMatching($expression));
    }

    #[Test]
    #[DataProvider('provideInvalidExpressions')]
    public function it_will_fail_to_parse_the_expression(string $expression): void
    {
        $this->expectException(SyntaxError::class);

        $this->getFragmentIdentifierTabularData()->firstOrFailMatching($expression);
    }

    public static function provideInvalidExpressions(): iterable
    {
        return [
            'missing expression type' => ['2-4'],
            'missing expression selection row' => ['row='],
            'missing expression selection cell' => ['cell='],
            'missing expression selection coll' => ['col='],
            'expression selection is invalid for cell 1' => ['cell=5'],
            'expression selection is invalid for cell 2' => ['cell=0,3'],
            'expression selection is invalid for cell 3' => ['cell=3,0'],
            'expression selection is invalid for cell 4' => ['cell=1,3-0,4'],
            'expression selection is invalid for cell 5' => ['cell=1,3-4,0'],
            'expression selection is invalid for cell 6' => ['cell=0,3-1,4'],
            'expression selection is invalid for cell 7' => ['cell=1,0-2,3'],
            'expression selection is invalid for row or column 1' => ['row=4,3'],
            'expression selection is invalid for row or column 2' => ['row=four-five'],
            'expression selection is invalid for row or column 3' => ['row=0-3'],
            'expression selection is invalid for row or column 4' => ['row=3-0'],
        ];
    }

    #[Test]
    public function it_returns_multiple_selections(): void
    {
        self::assertCount(2, iterator_to_array($this->getFragmentIdentifierTabularData()->matching('row=1-2;5-4;2-4')));
    }

    #[Test]
    public function it_returns_no_selection(): void
    {
        self::assertCount(0, iterator_to_array($this->getFragmentIdentifierTabularData()->matching('row=5-4')));
    }

    #[Test]
    public function it_fails_if_no_selection_is_found(): void
    {
        self::assertCount(1, iterator_to_array($this->getFragmentIdentifierTabularData()->firstOrFailMatching('row=7-8')));
    }
}
