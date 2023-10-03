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

use function array_reduce;
use function count;
use function explode;
use function filter_var;
use function preg_match;
use function range;

use const FILTER_VALIDATE_INT;

final class FragmentFinder
{
    private const REGEXP_URI_FRAGMENT = ',^(?<type>row|cell|col)=(?<selections>.*)$,i';
    private const REGEXP_ROWS_COLUMNS_SELECTION = '/^(?<start>\d+)(-(?<end>\d+|\*))?$/';
    private const REGEXP_CELLS_SELECTION = '/^(?<csr>\d+),(?<csc>\d+)(-(?<end>((?<cer>\d+),(?<cec>\d+))|\*))?$/';

    /**
     * @return Iterator<TabularDataReader>
     */
    public function all(string $expression, TabularDataReader $tabularDataReader): Iterator
    {
        foreach ($this->parseExpression($expression, $tabularDataReader) as $selection) {
            if (-1 < $selection['start']) {
                yield $tabularDataReader
                    ->slice($selection['start'], $selection['length'])
                    ->select(...$selection['columns']);
            }
        }
    }

    public function first(string $expression, TabularDataReader $tabularDataReader): ?TabularDataReader
    {
        foreach ($this->all($expression, $tabularDataReader) as $fragment) {
            return $fragment;
        }

        return null;
    }

    /**
     * @throws SyntaxError if the expression can not be parsed
     */
    public function firstOrFail(string $expression, TabularDataReader $tabularDataReader): TabularDataReader
    {
        foreach ($this->parseExpression($expression, $tabularDataReader) as $selection) {
            return match ($selection['start']) {
                -1 => throw new SyntaxError('The '.$selection['type'].' selection `'.$selection['selection'].'` is invalid.'),
                default => $tabularDataReader
                    ->slice($selection['start'], $selection['length'])
                    ->select(...$selection['columns']),
            };
        }
    }

    /**
     * @return non-empty-array<array{type:string, selection:string, start:int<-1, max>, length:int<-1, max>, columns:array<int>}>
     */
    private function parseExpression(string $expression, TabularDataReader $tabularDataReader): array
    {
        if (1 !== preg_match(self::REGEXP_URI_FRAGMENT, $expression, $matches)) {
            return [[
                'type' => 'unknown',
                'selection' => $expression,
                'start' => -1,
                'length' => -1,
                'columns' => [],
            ]];
        }

        $type = strtolower($matches['type']);
        if ('col' === $type) {
            $type = 'column';
        }

        /** @var non-empty-array<array{type:string, selection:string, start:int<-1, max>, length:int<-1, max>, columns:array<int>}> $res */
        $res = array_reduce(
            explode(';', $matches['selections']),
            fn (array $selections, string $selection): array => [...$selections, match ($type) {
                'row' => $this->parseRowSelection($type, $selection),
                'column' => $this->parseColumnSelection($type, $selection, $tabularDataReader),
                default => $this->parseCellSelection($type, $selection, $tabularDataReader),
            }],
            []
        );

        return $res;
    }

    /**
     * @return array{type:string, selection:string, start:int, length:int, columns:array<int>}
     */
    private function parseRowSelection(string $type, string $selection): array
    {
        [$start, $end] = $this->parseRowColumnSelection($selection);

        return match (true) {
            -1 === $start,
            null === $end => [
                'type' => $type,
                'selection' => $selection,
                'start' => $start,
                'length' => 1,
                'columns' => [],
            ],
            '*' === $end => [
                'type' => $type,
                'selection' => $selection,
                'start' => $start,
                'length' => -1,
                'columns' => [],
            ],
            default => [
                'type' => $type,
                'selection' => $selection,
                'start' => $start,
                'length' => $end - $start + 1,
                'columns' => [],
            ],
        };
    }

    /**
     * @return array{type:string, selection:string, start:int, length:int, columns:array<int>}
     */
    private function parseColumnSelection(string $type, string $selection, TabularDataReader $tabularDataReader): array
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
                'type' => $type,
                'selection' => $selection,
                'start' => -1,
                'length' => -1,
                'columns' => [],
            ],
            null === $end => [
                'type' => $type,
                'selection' => $selection,
                'start' => 0,
                'length' => -1,
                'columns' => [$start],
            ],
            '*' === $end,
            $end > ($nbColumns - 1) => [
                'type' => $type,
                'selection' => $selection,
                'start' => 0,
                'length' => -1,
                'columns' => range($start, $nbColumns),
            ],
            default => [
                'type' => $type,
                'selection' => $selection,
                'start' => 0,
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
     * @return array{type:string, selection:string, start:int, length:int, columns:array<int>}
     */
    private function parseCellSelection(string $type, string $selection, TabularDataReader $tabularDataReader): array
    {
        if (1 !== preg_match(self::REGEXP_CELLS_SELECTION, $selection, $found)) {
            return [
                'type' => $type,
                'selection' => $selection,
                'start' => -1,
                'length' => 1,
                'columns' => [],
            ];
        }

        $cellStartRow = filter_var($found['csr'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (false === $cellStartRow) {
            return [
                'type' => $type,
                'selection' => $selection,
                'start' => -1,
                'length' => 1,
                'columns' => [],
            ];
        }

        $cellStartCol = filter_var($found['csc'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (false === $cellStartCol) {
            return [
                'type' => $type,
                'selection' => $selection,
                'start' => -1,
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
                'type' => $type,
                'selection' => $selection,
                'start' => -1,
                'length' => 1,
                'columns' => [],
            ];
        }

        $cellEnd = $found['end'] ?? null;
        if (null === $cellEnd) {
            return [
                'type' => $type,
                'selection' => $selection,
                'start' => $cellStartRow,
                'length' => 1,
                'columns' => [$cellStartCol],
            ];
        }

        if ('*' === $cellEnd) {
            return [
                'type' => $type,
                'selection' => $selection,
                'start' => $cellStartRow,
                'length' => -1,
                'columns' => range($cellStartCol, $nbColumns - 1),
            ];
        }

        $cellEndRow = filter_var($found['cer'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (false === $cellEndRow) {
            return [
                'type' => $type,
                'selection' => $selection,
                'start' => -1,
                'length' => 1,
                'columns' => [],
            ];
        }

        $cellEndCol = filter_var($found['cec'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (false === $cellEndCol) {
            return [
                'type' => $type,
                'selection' => $selection,
                'start' => -1,
                'length' => 1,
                'columns' => [],
            ];
        }

        --$cellEndRow;
        --$cellEndCol;

        if ($cellEndRow < $cellStartRow || $cellEndCol < $cellStartCol) {
            return [
                'type' => $type,
                'selection' => $selection,
                'start' => -1,
                'length' => 1,
                'columns' => [],
            ];
        }

        return [
            'type' => $type,
            'selection' => $selection,
            'start' => $cellStartRow,
            'length' => $cellEndRow - $cellStartRow + 1,
            'columns' => range($cellStartCol, ($cellEndCol > $nbColumns - 1) ? $nbColumns - 1 : $cellEndCol),
        ];
    }
}
