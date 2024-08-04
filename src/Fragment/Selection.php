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

use League\Csv\Exception;
use League\Csv\FragmentNotFound;
use League\Csv\InvalidArgument;
use League\Csv\Statement;
use ReflectionException;
use const FILTER_VALIDATE_INT;

/**
 * @internal Internal representation of an Expression Selection.
 */
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

    private function __construct(
        public readonly int $rowStart,
        public readonly ?int $rowEnd,
        public readonly int $columnStart,
        public readonly ?int $columnEnd,
    ) {}

    public static function fromRow(string $selection): ?self
    {
        [$start, $end] = self::parseRowColumnSelection($selection);

        return match (true) {
            -1 === $start => null,
            null === $end => new self($start, $start, -1, null),
            '*' === $end => new self($start, null, -1, null),
            default => new self($start, $end,-1, null),
        };
    }

    public static function fromColumn(string $selection): ?self
    {
        [$start, $end] = self::parseRowColumnSelection($selection);

        return match (true) {
            -1 === $start => null,
            null === $end => new self(-1, null, $start, $start),
            '*' === $end => new self(-1, null, $start, -1),
            default => new self(-1, null, $start, $end),
        };
    }

    public static function fromCell(string $selection): ?self
    {
        if ('' === $selection) {
            return new self(-1, -1, -1, -1);
        }

        if (1 !== preg_match(self::REGEXP_CELLS_SELECTION, $selection, $found)) {
            return throw new FragmentNotFound('The fragment selection "'.$selection.'" is invalid.');
        }

        $cellStartRow = filter_var($found['csr'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $cellStartCol = filter_var($found['csc'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (false === $cellStartRow || false === $cellStartCol) {
            return null;
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
            return null;
        }

        --$cellEndRow;
        --$cellEndCol;

        if ($cellEndRow < $cellStartRow || $cellEndCol < $cellStartCol) {
            return null;
        }

        return new self($cellStartRow, $cellEndRow, $cellStartCol, $cellEndCol,);
    }

    /**
     * @return array{int<-1, max>, int|null|'*'}
     */
    private static function parseRowColumnSelection(string $selection): array
    {
        if ('' === $selection) {
            return [-1, 0];
        }

        if (1 !== preg_match(self::REGEXP_ROWS_COLUMNS_SELECTION, $selection, $found)) {
            throw new FragmentNotFound('The fragment selection "'.$selection.'" is invalid.');
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

    public function toString(): string
    {
        if (-1 === $this->columnStart && -1 === $this->rowStart) {
            return '';
        }

        if (-1 === $this->columnStart) {
            return (string) match ($this->rowEnd) {
                null => ($this->rowStart + 1).'-*',
                $this->rowStart => ($this->rowStart + 1),
                default => ($this->rowStart + 1).'-'.($this->rowEnd + 1),
            };
        }

        if (-1 === $this->rowStart) {
            return (string) match ($this->columnEnd) {
                -1 => ($this->columnStart + 1).'-*',
                null, $this->columnStart => ($this->columnStart + 1),
                default => ($this->columnStart + 1).'-'.($this->columnEnd + 1),
            };
        }

        $selection = ($this->rowStart + 1).','.($this->columnStart + 1);

        return match (true) {
            $this->columnEnd === -1 => $selection.'-*',
            $this->rowStart === $this->rowEnd && $this->columnStart === $this->columnEnd => $selection,
            default => $selection.'-'.(($this->rowEnd ?? 0) + 1).','.(($this->columnEnd ?? 0) + 1),
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

    /**
     * @throws Exception
     * @throws InvalidArgument
     * @throws ReflectionException
     */
    public function query(int $nbColumns = 0): Statement
    {
        if (-1 === $this->columnStart && -1 === $this->rowStart) {
            return Statement::create()->where(fn (array $record, int $offset): bool => false);
        }

        if (-1 === $this->columnStart) {
            return Statement::create()
                ->where(
                    fn (array $record, int $offset): bool => $offset >= $this->rowStart
                        && (null === $this->rowEnd || $offset <= $this->rowEnd)
                );
        }

        $columnRange = $this->columnRange();
        if (-1 === $this->rowStart) {
            return Statement::create()
                ->select(
                    ...match ($columnRange) {
                    null => range($this->columnStart, $nbColumns - 1),
                    default => $this->columnEnd > $nbColumns || $this->columnEnd === -1 ? range($this->columnStart, $nbColumns - 1) : $columnRange,
                });
        }

        return Statement::create()
            ->where(
                fn (array $record, int $offset): bool => $offset >= $this->rowStart &&
                    (null === $this->rowEnd || $offset <= $this->rowEnd)
            )
            ->select(
                ...match ($columnRange) {
                    null => range($this->columnStart, $nbColumns - 1),
                    default => $this->columnEnd > $nbColumns || $this->columnEnd === -1 ? range($this->columnStart, $nbColumns - 1) : $columnRange,
                }
            );
    }
}
