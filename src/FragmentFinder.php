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

use Iterator;

use function array_filter;
use function array_reduce;
use function count;
use function explode;
use function filter_var;
use function preg_match;
use function range;

use const FILTER_VALIDATE_INT;

/**
 * @phpstan-type selection array{selection:string, start:int<-1, max>, end:?int, length:int, columns:array<int>}
 */
final class FragmentFinder
{
    private const REGEXP_URI_FRAGMENT = ',^(?<type>row|cell|col)=(?<selections>.*)$,i';
    private const REGEXP_ROWS_COLUMNS_SELECTION = '/^(?<start>\d+)(-(?<end>\d+|\*))?$/';
    private const REGEXP_CELLS_SELECTION = '/^(?<csr>\d+),(?<csc>\d+)(-(?<end>((?<cer>\d+),(?<cec>\d+))|\*))?$/';
    private const TYPE_ROW = 'row';
    private const TYPE_COLUMN = 'col';
    private const TYPE_CELL = 'cell';
    private const TYPE_UNKNOWN = 'unknown';

    public static function create(): self
    {
        return new self();
    }

    /**
     * @throws SyntaxError
     *
     * @return Iterator<TabularDataReader>
     */
    public function findAll(string $expression, TabularDataReader $tabularDataReader): Iterator
    {
        $parsedExpression = $this->parseExpression($expression, $tabularDataReader);

        return $this->searchAll($parsedExpression, $tabularDataReader);
    }

    /**
     * @throws SyntaxError
     */
    public function findFirst(string $expression, TabularDataReader $tabularDataReader): ?TabularDataReader
    {
        $parsedExpression = $this->parseExpression($expression, $tabularDataReader);

        foreach ($this->searchAll($parsedExpression, $tabularDataReader) as $fragment) {
            return match ([]) {
                $fragment->first() => null,
                default => $fragment,
            };
        }

        return null;
    }

    /**
     * @throws SyntaxError
     * @throws FragmentNotFound if the expression can not be parsed
     */
    public function findFirstOrFail(string $expression, TabularDataReader $tabularDataReader): TabularDataReader
    {
        $parsedExpression = $this->parseExpression($expression, $tabularDataReader);
        ['type' => $type, 'selections' => $selections] = $parsedExpression;
        if ([] !== array_filter($selections, fn (array $selection) => -1 === $selection['start'])) {
            throw new FragmentNotFound('The expression '.$expression.' contains an invalid or an unsupported selection for the current tabular data.');
        }

        foreach ($this->searchAll($parsedExpression, $tabularDataReader) as $fragment) {
            return match ([]) {
                $fragment->first() => throw new FragmentNotFound('No record, column or cell could be found with the expression '.$expression.'.'),
                default => $fragment,
            };
        }

        throw new FragmentNotFound('No record, column or cell could be found with the expression '.$expression.'.');
    }

    /**
     * @param array{type:string, selections:non-empty-array<selection>} $parsedExpression
     *
     * @throws SyntaxError
     *
     * @return Iterator<TabularDataReader>
     */
    private function searchAll(array $parsedExpression, TabularDataReader $tabularDataReader): Iterator
    {
        ['type' => $type, 'selections' => $selections] = $parsedExpression;

        if (self::TYPE_ROW === $type) {
            $rowFilter = fn (array $record, int $offset): bool => [] !== array_filter(
                $selections,
                fn (array $selection) =>
                        -1 !== $selection['start'] &&
                        $offset >= $selection['start'] &&
                        (null === $selection['end'] || $offset <= $selection['end'])
            );

            yield from [$tabularDataReader->filter($rowFilter)];

        } elseif (self::TYPE_COLUMN === $type) {
            $columns = array_reduce($selections, fn (array $columns, array $selection) => match (-1) {
                $selection['start'] => $columns,
                default => [...$columns, ...$selection['columns']],
            }, []);

            yield from match ([]) {
                $columns => [ResultSet::createFromRecords()],
                default => [$tabularDataReader->select(...$columns)],
            };

        } elseif (self::TYPE_CELL === $type) {
            foreach ($selections as $selection) {
                if (-1 < $selection['start']) {
                    yield $tabularDataReader
                        ->slice($selection['start'], $selection['length'])
                        ->select(...$selection['columns']);
                }
            }
        } elseif (self::TYPE_UNKNOWN === $type) {
            return yield from [ResultSet::createFromRecords()];
        }
    }

    /**
     * @return array{type:string, selections:non-empty-array<selection>}
     */
    private function parseExpression(string $expression, TabularDataReader $tabularDataReader): array
    {
        if (1 !== preg_match(self::REGEXP_URI_FRAGMENT, $expression, $matches)) {
            return [
                'type' => self::TYPE_UNKNOWN,
                'selections' => [
                    [
                        'selection' => $expression,
                        'start' => -1,
                        'end' => null,
                        'length' => -1,
                        'columns' => [],
                    ],
                ],
            ];
        }

        $type = strtolower($matches['type']);

        /** @var non-empty-array<selection> $res */
        $res = array_reduce(
            explode(';', $matches['selections']),
            fn (array $selections, string $selection): array => [...$selections, match ($type) {
                self::TYPE_ROW => $this->parseRowSelection($selection),
                self::TYPE_COLUMN => $this->parseColumnSelection($selection, $tabularDataReader),
                default => $this->parseCellSelection($selection, $tabularDataReader),
            }],
            []
        );

        return [
            'type' => $type,
            'selections' => $res,
        ];
    }

    /**
     * @return selection
     */
    private function parseRowSelection(string $selection): array
    {
        [$start, $end] = $this->parseRowColumnSelection($selection);

        return match (true) {
            -1 === $start,
            null === $end => [
                'selection' => $selection,
                'start' => $start,
                'end' => $start,
                'length' => 1,
                'columns' => [],
            ],
            '*' === $end => [
                'selection' => $selection,
                'start' => $start,
                'end' => null,
                'length' => -1,
                'columns' => [],
            ],
            default => [
                'selection' => $selection,
                'start' => $start,
                'end' => $end,
                'length' => $end - $start + 1,
                'columns' => [],
            ],
        };
    }

    /**
     * @return selection
     */
    private function parseColumnSelection(string $selection, TabularDataReader $tabularDataReader): array
    {
        [$start, $end] = $this->parseRowColumnSelection($selection);
        $header = $tabularDataReader->getHeader();
        if ([] === $header) {
            $header = $tabularDataReader->first();
        }

        $nbColumns = count($header);

        return match (true) {
            -1 === $start,
            $start >= $nbColumns => [
                'selection' => $selection,
                'start' => -1,
                'end' => null,
                'length' => -1,
                'columns' => [],
            ],
            null === $end => [
                'selection' => $selection,
                'start' => 0,
                'end' => null,
                'length' => -1,
                'columns' => [$start],
            ],
            '*' === $end,
            $end > ($nbColumns - 1) => [
                'selection' => $selection,
                'start' => 0,
                'end' => null,
                'length' => -1,
                'columns' => range($start, $nbColumns - 1),
            ],
            default => [
                'selection' => $selection,
                'start' => 0,
                'end' => $end,
                'length' => -1,
                'columns' => range($start, $end),
            ],
        };
    }

    /**
     * @return array{int<-1, max>, int|null|'*'}
     */
    private function parseRowColumnSelection(string $selection): array
    {
        if (1 !== preg_match(self::REGEXP_ROWS_COLUMNS_SELECTION, $selection, $found)) {
            return [-1, 0];
        }

        $start = $found['start'];
        $end = $found['end'] ?? null;
        $start = filter_var($start, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (false === $start) {
            return [-1, 0];
        }
        --$start;

        if (null === $end || '*' === $end) {
            return [$start, $end];
        }

        $end = filter_var($end, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (false === $end) {
            return [-1, 0];
        }
        --$end;

        if ($end <= $start) {
            return [-1, 0];
        }

        return [$start, $end];
    }

    /**
     * @return selection
     */
    private function parseCellSelection(string $selection, TabularDataReader $tabularDataReader): array
    {
        if (1 !== preg_match(self::REGEXP_CELLS_SELECTION, $selection, $found)) {
            return [
                'selection' => $selection,
                'start' => -1,
                'end' => null,
                'length' => 1,
                'columns' => [],
            ];
        }

        $cellStartRow = filter_var($found['csr'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (false === $cellStartRow) {
            return [
                'selection' => $selection,
                'start' => -1,
                'end' => null,
                'length' => 1,
                'columns' => [],
            ];
        }

        $cellStartCol = filter_var($found['csc'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (false === $cellStartCol) {
            return [
                'selection' => $selection,
                'start' => -1,
                'end' => null,
                'length' => 1,
                'columns' => [],
            ];
        }
        --$cellStartRow;
        --$cellStartCol;

        $header = $tabularDataReader->getHeader();
        if ([] === $header) {
            $header = $tabularDataReader->first();
        }

        $nbColumns = count($header);

        if ($cellStartCol > $nbColumns - 1) {
            return [
                'selection' => $selection,
                'start' => -1,
                'end' => null,
                'length' => 1,
                'columns' => [],
            ];
        }

        $cellEnd = $found['end'] ?? null;
        if (null === $cellEnd) {
            return [
                'selection' => $selection,
                'start' => $cellStartRow,
                'end' => null,
                'length' => 1,
                'columns' => [$cellStartCol],
            ];
        }

        if ('*' === $cellEnd) {
            return [
                'selection' => $selection,
                'start' => $cellStartRow,
                'end' => null,
                'length' => -1,
                'columns' => range($cellStartCol, $nbColumns - 1),
            ];
        }

        $cellEndRow = filter_var($found['cer'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (false === $cellEndRow) {
            return [
                'selection' => $selection,
                'start' => -1,
                'end' => null,
                'length' => 1,
                'columns' => [],
            ];
        }

        $cellEndCol = filter_var($found['cec'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (false === $cellEndCol) {
            return [
                'selection' => $selection,
                'start' => -1,
                'end' => null,
                'length' => 1,
                'columns' => [],
            ];
        }

        --$cellEndRow;
        --$cellEndCol;

        if ($cellEndRow < $cellStartRow || $cellEndCol < $cellStartCol) {
            return [
                'selection' => $selection,
                'start' => -1,
                'end' => null,
                'length' => 1,
                'columns' => [],
            ];
        }

        return [
            'selection' => $selection,
            'start' => $cellStartRow,
            'end' => $cellEndRow,
            'length' => $cellEndRow - $cellStartRow + 1,
            'columns' => range($cellStartCol, ($cellEndCol > $nbColumns - 1) ? $nbColumns - 1 : $cellEndCol),
        ];
    }
}
