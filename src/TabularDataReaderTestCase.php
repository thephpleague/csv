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

use DateTimeImmutable;
use DateTimeInterface;
use League\Csv\Serializer\MapCell;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Throwable;

#[Group('tabulardata')]
abstract class TabularDataReaderTestCase extends TestCase
{
    abstract protected function tabularDataWithoutHeader(): TabularDataReader;
    abstract protected function tabularDataWithHeader(): TabularDataReader;

    /***************************
     * TabularDataReader::exists
     ****************************/

    public function testExistsRecord(): void
    {
        self::assertFalse((new Statement())->process($this->tabularDataWithoutHeader())->exists(fn (array $record) => array_key_exists('foobar', $record)));
        self::assertTrue((new Statement())->process($this->tabularDataWithoutHeader())->exists(fn (array $record) => count($record) < 5));
    }

    /***************************
     * TabularDataReader::select
     ****************************/

    #[Test]
    public function testTabularSelectWithoutHeader(): void
    {
        self::assertSame([1 => 'temperature', 2 => 'place'], $this->tabularDataWithoutHeader()->select(1, 2)->first());
    }

    #[Test]
    public function testTabularSelectWithHeader(): void
    {
        self::assertSame(['temperature' => '1', 'place' => 'Galway'], $this->tabularDataWithHeader()->select(1, 2)->first());
        self::assertSame(['temperature' => '1', 'place' => 'Galway'], $this->tabularDataWithHeader()->select('temperature', 'place')->first());
        self::assertSame(['temperature' => '1', 'place' => 'Galway'], $this->tabularDataWithHeader()->select(1, 'place')->first());
        self::assertSame(['temperature' => '1', 'place' => 'Galway'], $this->tabularDataWithHeader()->select('temperature', 2)->first());
    }

    public function testTabularReaderSelectFailsWithInvalidColumn(): void
    {
        $this->expectException(InvalidArgument::class);

        $this->tabularDataWithoutHeader()
            ->select('temperature', 'place');
    }

    public function testTabularReaderSelectFailsWithInvalidColumnName(): void
    {
        $this->expectException(InvalidArgument::class);

        $this->tabularDataWithHeader()
            ->select('temperature', 'foobar');
    }

    public function testTabularReaderSelectFailsWithInvalidColumnOffset(): void
    {
        $this->expectException(InvalidArgument::class);

        $this->tabularDataWithHeader()
            ->select(0, 18);
    }

    /***************************
     * TabularDataReader::selectAllExcept
     ****************************/


    #[Test]
    public function testTabularselectAllExceptWithoutHeader(): void
    {
        self::assertSame([1 => 'temperature', 2 => 'place'], $this->tabularDataWithoutHeader()->selectAllExcept(0)->first());
    }

    #[Test]
    public function testTabularselectAllExceptWithHeader(): void
    {
        self::assertSame(['temperature' => '1', 'place' => 'Galway'], $this->tabularDataWithHeader()->selectAllExcept('date')->first());
        self::assertSame(['place' => 'Galway'], $this->tabularDataWithHeader()->selectAllExcept('temperature', 'date')->first());
        self::assertSame(['place' => 'Galway'], $this->tabularDataWithHeader()->selectAllExcept(1, 'date')->first());
        self::assertSame(['place' => 'Galway'], $this->tabularDataWithHeader()->selectAllExcept('temperature', 0)->first());
    }

    public function testTabularReaderselectAllExceptFailsWithInvalidColumn(): void
    {
        $this->expectException(InvalidArgument::class);

        $this->tabularDataWithoutHeader()
            ->selectAllExcept('temperature', 'place');
    }

    public function testTabularReaderselectAllExceptFailsWithInvalidColumnName(): void
    {
        $this->expectException(InvalidArgument::class);

        $this->tabularDataWithHeader()
            ->selectAllExcept('temperature', 'foobar');
    }

    public function testTabularReaderselectAllExceptFailsWithInvalidColumnOffset(): void
    {
        $this->expectException(InvalidArgument::class);

        $this->tabularDataWithHeader()
            ->selectAllExcept(0, 18);
    }

    /***************************
     * TabularDataReader::matching, matchingFirst, matchingFirstOrFail
     **************************/

    #[Test]
    #[DataProvider('provideValidExpressions')]
    public function it_can_select_a_specific_fragment(string $expression, ?array $expected): void
    {
        $result = $this->tabularDataWithoutHeader()->matchingFirst($expression);
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
            $this->expectException(FragmentNotFound::class);

            $this->tabularDataWithoutHeader()->matchingFirstOrFail($expression);

            return;
        }

        self::assertSame($expected, [...$this->tabularDataWithoutHeader()->matchingFirstOrFail($expression)]);
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
    public function it_will_fail_to_parse_invalid_expression(string $expression): void
    {
        $this->expectException(Throwable::class);

        $this->tabularDataWithoutHeader()->matchingFirstOrFail($expression);
    }

    public static function provideInvalidExpressions(): iterable
    {
        return [
            'expression selection is invalid for cell 1' => ['expression' => 'cell=5'],
            'expression selection is invalid for row or column 1' => ['expression' => 'row=4,3'],
            'expression selection is invalid for row or column 2' => ['expression' => 'row=four-five'],
        ];
    }

    #[Test]
    #[DataProvider('provideExpressionWithIgnoredSelections')]
    public function it_will_return_null_on_invalid_expression(string $expression): void
    {
        self::assertNull($this->tabularDataWithoutHeader()->matchingFirst($expression));
    }

    #[Test]
    #[DataProvider('provideExpressionWithIgnoredSelections')]
    public function it_will_fail_to_parse_the_expression(string $expression): void
    {
        $this->expectException(FragmentNotFound::class);

        $this->tabularDataWithoutHeader()->matchingFirstOrFail($expression);
    }

    public static function provideExpressionWithIgnoredSelections(): iterable
    {
        return [
            'missing expression selection row' => ['row='],
            'missing expression selection cell' => ['cell='],
            'missing expression selection coll' => ['col='],
            'expression selection is invalid for cell 2' => ['cell=0,3'],
            'expression selection is invalid for cell 3' => ['cell=3,0'],
            'expression selection is invalid for cell 4' => ['cell=1,3-0,4'],
            'expression selection is invalid for cell 5' => ['cell=1,3-4,0'],
            'expression selection is invalid for cell 6' => ['cell=0,3-1,4'],
            'expression selection is invalid for cell 7' => ['cell=1,0-2,3'],
            'expression selection is invalid for row or column 3' => ['row=0-3'],
            'expression selection is invalid for row or column 4' => ['row=3-0'],
        ];
    }

    #[Test]
    public function it_returns_multiple_selections_in_one_tabular_data_instance(): void
    {
        self::assertCount(1, $this->tabularDataWithoutHeader()->matching('row=1-2;5-4;2-4'));
    }

    #[Test]
    public function it_returns_no_selection(): void
    {
        self::assertCount(1, $this->tabularDataWithoutHeader()->matching('row=5-4'));
    }

    #[Test]
    public function it_fails_if_no_selection_is_found(): void
    {
        self::assertCount(1, iterator_to_array($this->tabularDataWithoutHeader()->matchingFirstOrFail('row=7-8')));
    }

    #[Test]
    public function it_fails_if_no_row_is_found(): void
    {
        $this->expectException(FragmentNotFound::class);

        $this->tabularDataWithoutHeader()->matchingFirstOrFail('row=42');
    }

    /***************************
     * TabularDataReader::map
     ****************************/

    public function testMap(): void
    {
        self::assertContains(42, $this->tabularDataWithoutHeader()->map(fn (array $record, int $offset): int => 42));
    }

    /***************************
     * TabularDataReader::reduce
     ****************************/

    public function testReduce(): void
    {
        self::assertSame(21, $this->tabularDataWithoutHeader()->reduce(fn (?int $carry, array $record): int => ($carry ?? 0) + count($record)));
    }

    /***************************
     * TabularDataReader::each
     ****************************/

    public function testEach(): void
    {
        $recordsCopy = [];
        $tabularData = $this->tabularDataWithoutHeader();
        $tabularData->each(function (array $record, string|int $offset) use (&$recordsCopy) {
            $recordsCopy[$offset] = $record;

            return true;
        });

        self::assertSame($recordsCopy, [...$tabularData]);
    }

    public function testEachStopped(): void
    {
        $recordsCopy = [];
        $tabularData = $this->tabularDataWithHeader();
        $tabularData->each(function (array $record) use (&$recordsCopy) {
            if (4 > count($recordsCopy)) {
                $recordsCopy[] = $record;

                return true;
            }

            return false;
        });

        self::assertCount(4, $recordsCopy);
    }

    /***************************
     * TabularDataReader::slice
     ****************************/


    public function testSliceThrowException(): void
    {
        $this->expectException(InvalidArgument::class);

        $this->tabularDataWithHeader()->slice(0, -2);
    }

    public function testSlice(): void
    {
        self::assertContains(
            ['2011-01-01', '1', 'Galway'],
            [...$this->tabularDataWithoutHeader()->slice(1)]
        );
    }

    public function testCountable(): void
    {
        self::assertCount(1, $this->tabularDataWithoutHeader()->slice(1, 1));
        self::assertCount(7, $this->tabularDataWithoutHeader());
        self::assertCount(6, $this->tabularDataWithHeader());
    }

    public function testValue(): void
    {
        self::assertNull($this->tabularDataWithoutHeader()->value(42));
        self::assertNull($this->tabularDataWithoutHeader()->value('place'));
        self::assertSame('place', $this->tabularDataWithoutHeader()->value(2));
        self::assertSame('2011-01-01', $this->tabularDataWithHeader()->value());
        self::assertSame('Galway', $this->tabularDataWithHeader()->value(2));
        self::assertSame('Galway', $this->tabularDataWithHeader()->value('place'));
    }

    /***************************
     * TabularDataReader::getRecordsAsOject
     ****************************/

    public function testGetObjectWithHeader(): void
    {
        $class = new class (5, Place::Galway, new DateTimeImmutable()) {
            public function __construct(
                public readonly float $temperature,
                public readonly Place $place,
                #[MapCell(
                    column: 'date',
                    options: ['format' => '!Y-m-d', 'timezone' => 'Africa/Kinshasa'],
                )]
                public readonly DateTimeInterface $observedOn
            ) {
            }
        };

        foreach ($this->tabularDataWithHeader()->getRecordsAsObject($class::class) as $object) {
            self::assertInstanceOf($class::class, $object);
        }
    }

    public function testGetObjectWithoutHeader(): void
    {
        $class = new class (5, Place::Galway, new DateTimeImmutable()) {
            public function __construct(
                #[MapCell(column: 1)]
                public readonly float $temperature,
                #[MapCell(column: 2)]
                public readonly Place $place,
                #[MapCell(column: 0)]
                public readonly DateTimeInterface $observedOn
            ) {
            }
        };

        foreach ($this->tabularDataWithHeader()->getRecordsAsObject($class::class) as $object) {
            self::assertInstanceOf($class::class, $object);
        }
    }

    public function testGetNthObjectWithHeader(): void
    {
        $class = new class (5, Place::Galway, new DateTimeImmutable()) {
            public function __construct(
                public readonly float $temperature,
                public readonly Place $place,
                #[MapCell(
                    column: 'date',
                    options: ['format' => '!Y-m-d', 'timezone' => 'Africa/Kinshasa'],
                )]
                public readonly DateTimeInterface $observedOn
            ) {
            }
        };

        self::assertInstanceOf($class::class, $this->tabularDataWithHeader()->nthAsObject(2, $class::class));
        self::assertInstanceOf($class::class, $this->tabularDataWithHeader()->firstAsObject($class::class));
        self::assertNull($this->tabularDataWithHeader()->nthAsObject(42, $class::class));
    }

    public function testGetNthObjectWithCustomHeader(): void
    {
        $class = new class (5, Place::Galway, new DateTimeImmutable()) {
            public function __construct(
                public readonly float $temperature,
                public readonly Place $place,
                public readonly DateTimeInterface $observedOn
            ) {
            }
        };

        self::assertInstanceOf($class::class, $this->tabularDataWithHeader()->firstAsObject($class::class, ['observedOn', 'temperature', 'place']));
    }

    public function testChunkingTabularDataUsingTheRangeMethod(): void
    {
        self::assertCount(2, [...$this->tabularDataWithHeader()->chunkBy(4)]);
    }

    /**************************************************************
     * TabularDataReader::last and TabularDataReader::lastAsObject
     **************************************************************/

    #[Test]
    public function it_will_create_a_datable_with_a_header(): void
    {
        $weather = new class (new DateTimeImmutable(), 6, 'Brussels') {
            public function __construct(
                public readonly DateTimeImmutable $date,
                public readonly int $temperature,
                public readonly string $place,
            ) {
            }
        };

        $tabularData = $this->tabularDataWithHeader();
        $last = $tabularData->last();
        $objLast = $tabularData->lastAsObject($weather::class);

        self::assertSame(['date' => '2011-01-03', 'temperature' => '5', 'place' => 'Berkeley'], $last);
        self::assertInstanceOf($weather::class, $objLast);
        self::assertEquals(new DateTimeImmutable('2011-01-03'), $objLast->date);
        self::assertSame(5, $objLast->temperature);
        self::assertEquals('Berkeley', $objLast->place);
    }
}

enum Place: string
{
    case Galway = 'Galway';
    case Berkeley = 'Berkeley';
}
