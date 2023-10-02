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

    public readonly int $fallbackOffset;

    /**
     * @throws InvalidArgument
     */
    public function __construct(int $fallbackOffset = 0)
    {
        if (0 > $fallbackOffset) {
            throw new InvalidArgument('The fallback offset must be greater or equals to 0.');
        }

        $this->fallbackOffset = $fallbackOffset;
    }

    /**
     * @throws InvalidArgument
     */
    public function fallbackOffset(int $fallbackOffset = 0): self
    {
        return match (true) {
            $fallbackOffset === $this->fallbackOffset => $this,
            default => new self($fallbackOffset),
        };
    }

    /**
     * @throws SyntaxError if the expression can not be parsed
     *
     * @return iterable<TabularDataReader>
     */
    public function all(string $expression, TabularDataReader $tabularDataReader): iterable
    {
        foreach ($this->parseExpression($expression, $tabularDataReader) as $selection) {
            if (-1 < $selection['start']) {
                yield $tabularDataReader
                    ->slice($selection['start'], $selection['length'])
                    ->select(...$selection['columns']);
            }
        }
    }

    /**
     * @throws SyntaxError if the expression can not be parsed
     * @throws FragmentNotFound if no fragment are found
     *
     * @return iterable<TabularDataReader>
     */
    public function allOrFail(string $expression, TabularDataReader $tabularDataReader): iterable
    {
        $selections = $this->parseExpression($expression, $tabularDataReader);
        foreach ($selections as $selection) {
            yield match (true) {
                0 > $selection['start'] => throw new FragmentNotFound('The expression `'.$selection['selection'].'` contains invalid selection.'),
                default => $tabularDataReader
                    ->slice($selection['start'], $selection['length'])
                    ->select(...$selection['columns']),
            };
        }
    }

    /**
     * @throws SyntaxError if the expression can not be parsed
     * @throws FragmentNotFound if no fragment are found
     */
    public function first(string $expression, TabularDataReader $tabularDataReader): ?TabularDataReader
    {
        foreach ($this->all($expression, $tabularDataReader) as $fragment) {
            return $fragment;
        }

        return null;
    }

    /**
     * @throws FragmentNotFound When the expression can not be use
     * @throws SyntaxError if the expression can not be parsed
     */
    public function firstOrFail(string $expression, TabularDataReader $tabularDataReader): TabularDataReader
    {
        foreach ($this->allOrFail($expression, $tabularDataReader) as $fragment) {
            return $fragment;
        }

        //@codeCoverageIgnoreStart
        throw new FragmentNotFound('No fragment was found for the expression `'.$expression.'`.');
        //@codeCoverageIgnoreEnd
    }

    /**
     * @throws SyntaxError if the expression can not be parsed
     *
     * @return non-empty-array<array{selection:string, start:int<-1, max>, length:int<-1, max>, columns:array<int>}>
     */
    private function parseExpression(string $expression, TabularDataReader $tabularDataReader): array
    {
        if (1 !== preg_match(self::REGEXP_URI_FRAGMENT, $expression, $matches)) {
            throw new SyntaxError('The query expression `'.$expression.'` is invalid.');
        }

        $type = strtolower($matches['type']);

        /** @var non-empty-array<array{selection:string, start:int<-1, max>, length:int<-1, max>, columns:array<int>}> $res */
        $res = array_reduce(
            explode(';', $matches['selections']),
            fn (array $selections, string $selection): array => [...$selections, match ($type) {
                'row' => $this->parseRowSelection($selection),
                'col' => $this->parseColumnSelection($selection, $tabularDataReader),
                default => $this->parseCellSelection($selection, $tabularDataReader),
            }],
            []
        );

        return $res;
    }

    /**
     * @throws SyntaxError
     *
     * @return non-empty-array{selection:string, start:int, length:int, columns:array<int>}
     */
    private function parseRowSelection(string $selection): array
    {
        [$start, $end] = $this->parseRowColumnSelection($selection);

        return match (true) {
            -1 === $start,
            null === $end => [
                'selection' => $selection,
                'start' => $start,
                'length' => 1,
                'columns' => [],
            ],
            '*' === $end => [
                'selection' => $selection,
                'start' => $start,
                'length' => -1,
                'columns' => [],
            ],
            default => [
                'selection' => $selection,
                'start' => $start,
                'length' => $end - $start + 1,
                'columns' => [],
            ],
        };
    }

    /**
     * @throws SyntaxError
     *
     * @return non-empty-array{selection:string, start:int, length:int, columns:array<int>}
     */
    private function parseColumnSelection(string $selection, TabularDataReader $tabularDataReader): array
    {
        [$start, $end] = $this->parseRowColumnSelection($selection);
        $header = $tabularDataReader->getHeader();
        if ([] === $header) {
            $header = $tabularDataReader->nth($this->fallbackOffset);
        }

        $nbColumns = count($header);

        return match (true) {
            -1 === $start,
            $start >= $nbColumns => [
                'selection' => $selection,
                'start' => -1,
                'length' => -1,
                'columns' => [],
            ],
            null === $end => [
                'selection' => $selection,
                'start' => 0,
                'length' => -1,
                'columns' => [$start],
            ],
            '*' === $end,
            $end > ($nbColumns - 1) => [
                'selection' => $selection,
                'start' => 0,
                'length' => -1,
                'columns' => range($start, $nbColumns),
            ],
            default => [
                'selection' => $selection,
                'start' => 0,
                'length' => -1,
                'columns' => range($start, $end),
            ],
        };
    }

    /**
     * @throws SyntaxError
     *
     * @return array{int<-1, max>, int|null|'*'}
     */
    private function parseRowColumnSelection(string $selection): array
    {
        if (1 !== preg_match(self::REGEXP_ROWS_COLUMNS_SELECTION, $selection, $found)) {
            throw new SyntaxError('The selection `'.$selection.'` is invalid.');
        }

        $start = $found['start'];
        $end = $found['end'] ?? null;
        $start = filter_var($start, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (false === $start) {
            throw new SyntaxError('The selection `'.$selection.'` is invalid.');
        }
        --$start;

        if (null === $end || '*' === $end) {
            return [$start, $end];
        }

        $end = filter_var($end, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (false === $end) {
            throw new SyntaxError('The selection `'.$selection.'` is invalid.');
        }
        --$end;

        if ($end <= $start) {
            return [-1, 0];
        }

        return [$start, $end];
    }

    /**
     * @throws SyntaxError
     *
     * @return non-empty-array{selection:string, start:int, length:int, columns:array<int>}
     */
    private function parseCellSelection(string $selection, TabularDataReader $tabularDataReader): array
    {
        if (1 !== preg_match(self::REGEXP_CELLS_SELECTION, $selection, $found)) {
            throw new SyntaxError('The selection `'.$selection.'` is invalid.');
        }

        $cellStartRow = filter_var($found['csr'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (false === $cellStartRow) {
            throw new SyntaxError('The selection `'.$selection.'` is invalid.');
        }

        $cellStartCol = filter_var($found['csc'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (false === $cellStartCol) {
            throw new SyntaxError('The selection `'.$selection.'` is invalid.');
        }
        --$cellStartRow;
        --$cellStartCol;

        $header = $tabularDataReader->getHeader();
        if ([] === $header) {
            $header = $tabularDataReader->nth($this->fallbackOffset);
        }

        $nbColumns = count($header);

        if ($cellStartCol > $nbColumns - 1) {
            return [
                'selection' => $selection,
                'start' => -1,
                'length' => 1,
                'columns' => [],
            ];
        }

        $cellEnd = $found['end'] ?? null;
        if (null === $cellEnd) {
            return [
                'selection' => $selection,
                'start' => $cellStartRow,
                'length' => 1,
                'columns' => [$cellStartCol],
            ];
        }

        if ('*' === $cellEnd) {
            return [
                'selection' => $selection,
                'start' => $cellStartRow,
                'length' => -1,
                'columns' => range($cellStartCol, $nbColumns - 1),
            ];
        }

        $cellEndRow = filter_var($found['cer'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (false === $cellEndRow) {
            throw new SyntaxError('The selection `'.$selection.'` is invalid.');
        }

        $cellEndCol = filter_var($found['cec'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (false === $cellEndCol) {
            throw new SyntaxError('The selection `'.$selection.'` is invalid.');
        }

        --$cellEndRow;
        --$cellEndCol;

        if ($cellEndRow < $cellStartRow || $cellEndCol < $cellStartCol) {
            return [
                'selection' => $selection,
                'start' => -1,
                'length' => 1,
                'columns' => [],
            ];
        }

        return [
            'selection' => $selection,
            'start' => $cellStartRow,
            'length' => $cellEndRow - $cellStartRow + 1,
            'columns' => range($cellStartCol, ($cellEndCol > $nbColumns - 1) ? $nbColumns - 1 : $cellEndCol),
        ];
    }
}
