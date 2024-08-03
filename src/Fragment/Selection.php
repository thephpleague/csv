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

namespace League\Csv\Fragment;

use League\Csv\FragmentNotFound;
use const FILTER_VALIDATE_INT;

final class Selection
{
    private const REGEXP_ROWS_COLUMNS_SELECTION = '/^(?<start>\d+)(-(?<end>\d+|\*))?$/';
    private const REGEXP_CELLS_SELECTION = '/^
        (?<csr>\d+), #row start
        (?<csc>\d+)  #col start
        (-(?<end>(
            (?<cer>\d+), #row end
            (?<cec>\d+)  #col end
        )|\*)  #star alternative to end part
        )?
    $/x';

    public static function fromUnknown(): self
    {
        return new self(-1, null, -1, null);
    }

    private function __construct(
        public readonly int $rowStart,
        public readonly ?int $rowEnd,
        public readonly int $columnStart,
        public readonly ?int $columnEnd,
    ) {
    }

    public function rowCount(): ?int
    {
        return match (true) {
            -1 === $this->rowStart => null,
            null === $this->rowEnd => -1,
            default => $this->rowEnd - $this->rowStart + 1,
        };
    }

    public function columnRange(): ?array
    {
        return match (true) {
            -1 === $this->columnStart => [],
            null === $this->columnEnd => null,
            default => range($this->columnStart, $this->columnEnd),
        };
    }

    public static function fromRow(string $selection): self
    {
        [$start, $end] = self::parseRowColumnSelection($selection);

        return match (true) {
            -1 === $start => throw new FragmentNotFound('The submitted selection `'.$selection.'` is invalid.'),
            null === $end => new self($start, $start, -1, null),
            '*' === $end => new self($start, null, -1, null),
            default => new self($start, $end,-1, null),
        };
    }

    public static function fromColumn(string $selection): self
    {
        [$start, $end] = self::parseRowColumnSelection($selection);

        return match (true) {
            -1 === $start => throw new FragmentNotFound('The submitted selection `'.$selection.'` is invalid.'),
            null === $end => new self(-1, null, $start, $start),
            '*' === $end => new self(-1, null, $start, -1),
            default => new self(-1, null, $start, $end),
        };
    }

    /**
     * @return array{int<-1, max>, int|null|'*'}
     */
    private static function parseRowColumnSelection(string $selection): array
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

    public static function fromCell(string $selection): self
    {
        if (1 !== preg_match(self::REGEXP_CELLS_SELECTION, $selection, $found)) {
            throw new FragmentNotFound('The submitted selection `'.$selection.'` is invalid.');
        }

        $cellStartRow = filter_var($found['csr'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $cellStartCol = filter_var($found['csc'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (false === $cellStartRow || false === $cellStartCol) {
            throw new FragmentNotFound('The submitted selection `'.$selection.'` is invalid.');
        }

        --$cellStartRow;
        --$cellStartCol;

        $cellEnd = $found['end'] ?? null;
        if (null === $cellEnd) {
            return new self($cellStartRow, $cellStartRow, $cellStartCol, $cellStartCol);
        }

        if ('*' === $cellEnd) {
            return new self($cellStartRow, null, $cellStartCol, -1);
        }

        $cellEndRow = filter_var($found['cer'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $cellEndCol = filter_var($found['cec'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (false === $cellEndRow || false === $cellEndCol) {
            throw new FragmentNotFound('The submitted selection `'.$selection.'` is invalid.');
        }

        --$cellEndRow;
        --$cellEndCol;

        if ($cellEndRow < $cellStartRow || $cellEndCol < $cellStartCol) {
            throw new FragmentNotFound('The submitted selection `'.$selection.'` is invalid.');
        }

        return new self($cellStartRow, $cellEndRow, $cellStartCol, $cellEndCol,);
    }
}
