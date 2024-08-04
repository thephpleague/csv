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

use Countable;
use IteratorAggregate;
use League\Csv\Exception;
use League\Csv\FragmentNotFound;
use League\Csv\InvalidArgument;
use League\Csv\Statement;
use League\Csv\SyntaxError;
use League\Csv\TabularDataReader;
use ReflectionException;
use Stringable;
use Traversable;

use function array_map;
use function explode;
use function implode;
use function preg_match;

/**
 * @implements IteratorAggregate<int, string>
 */
final class Expression implements Stringable, Countable, IteratorAggregate
{
    private const REGEXP_URI_FRAGMENT = ',^(?<type>row|cell|col)=(?<selections>.*)$,i';

    private readonly Type $type;
    /** @param array<Selection> $selections */
    private readonly array $selections;

    /**
     * @param array<Selection> $selections
     */
    private function __construct(Type $type, array $selections)
    {
        $this->type = $type;
        $this->selections = self::removeDuplicates($selections);
    }

    /**
     * @param array<Selection> $selections
     *
     * @return array<Selection>
     */
    private static function removeDuplicates(array $selections): array
    {
        $sorted = [];
        foreach ($selections as $selection) {
            $key = $selection->toString();
            if (array_key_exists($key, $sorted)) {
                continue;
            }
            $sorted[$key] = $selection;
        }
        ksort($sorted);

        return array_values($sorted);
    }

    public static function from(Stringable|string $expression): self
    {
        if ($expression instanceof self) {
            return $expression;
        }

        if (1 !== preg_match(self::REGEXP_URI_FRAGMENT, (string) $expression, $matches)) {
            throw new FragmentNotFound('The expression "' . $expression . '" does not match the CSV fragment Identifier specification.');
        }

        $selections = explode(';', $matches['selections']);

        return match (Type::from(strtolower($matches['type']))) {
            Type::Row => self::fromRow(...$selections),
            Type::Column => self::fromColumn(...$selections),
            Type::Cell => self::fromCell(...$selections),
        };
    }

    public static function fromCell(string ...$selections): self
    {
        return new self(Type::Cell, array_filter(array_map(Selection::tryFromCell(...), $selections)));
    }

    public static function fromColumn(string ...$selections): self
    {
        return new self(Type::Column, array_filter(array_map(Selection::tryFromColumn(...), $selections)));
    }

    public static function fromRow(string ...$selections): self
    {
        return new self(Type::Row, array_filter(array_map(Selection::tryFromRow(...), $selections)));
    }

    public function isEmpty(): bool
    {
        return [] === $this->selections;
    }

    public function isNotEmpty(): bool
    {
        return ! $this->isEmpty();
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function toString(): string
    {
        return $this->type->value .'='.implode(
            ';',
            array_map(fn (Selection $selection): string => $selection->toString(), $this->selections)
        );
    }

    public function count(): int
    {
        return count($this->selections);
    }

    public function getIterator(): Traversable
    {
        foreach ($this->selections as $selection) {
            yield $selection->toString();
        }
    }

    public function get(int $key): string
    {
        return $this->selections[
            $this->filterIndex($key) ?? throw new FragmentNotFound('No selection found for the given key `'.$key.'`.')
        ]->toString();
    }

    public function hasKey(int ...$keys): bool
    {
        $max = count($this->selections);
        foreach ($keys as $offset) {
            if (null === $this->filterIndex($offset, $max)) {
                return false;
            }
        }

        return [] !== $keys;
    }

    public function has(string $selection): bool
    {
        return null !== $this->contains($selection);
    }

    public function contains(string $selection): ?int
    {
        if ([] === $this->selections) {
            return null;
        }

        try {
            $selection = (match ($this->type) {
                Type::Row => Selection::fromRow($selection),
                Type::Column => Selection::fromColumn($selection),
                Type::Cell => Selection::fromCell($selection),
            })->toString();
        } catch (FragmentNotFound) {
            return null;
        }

        foreach ($this->selections as $offset => $innerSelection) {
            if ($selection === $innerSelection->toString()) {
                return $offset;
            }
        }

        return null;
    }

    private function filterIndex(int $index, ?int $max = null): ?int
    {
        $max ??= count($this->selections);

        return match (true) {
            [] === $this->selections, 0 > $max + $index, 0 > $max - $index - 1 => null,
            0 > $index => $max + $index,
            default => $index,
        };
    }

    public function push(string ...$selections): self
    {
        if ([] === $selections) {
            return $this;
        }

        $selections = array_filter(match ($this->type) {
            Type::Row => array_map(Selection::tryFromRow(...), $selections),
            Type::Column => array_map(Selection::tryFromColumn(...), $selections),
            Type::Cell => array_map(Selection::tryFromCell(...), $selections),
        });

        $selections = self::removeDuplicates($selections);
        if ([] === $selections || $this->selections == $selections) {
            return $this;
        }

        return new self($this->type, [...$this->selections, ...$selections]);
    }

    public function unshift(string ...$selections): self
    {
        if ([] === $selections) {
            return $this;
        }

        $selections = array_filter(match ($this->type) {
            Type::Row => array_map(Selection::tryFromRow(...), $selections),
            Type::Column => array_map(Selection::tryFromColumn(...), $selections),
            Type::Cell => array_map(Selection::tryFromCell(...), $selections),
        });

        $selections = self::removeDuplicates($selections);
        if ([] === $selections || $this->selections == $selections) {
            return $this;
        }

        return new self($this->type, [...$selections, ...$this->selections]);
    }

    public function replace(string $oldSelection, string $newSelection): self
    {
        $offset = $this->contains($oldSelection);
        if (null === $offset) {
            throw new FragmentNotFound('The selection `'.$oldSelection.'` used for replace is not valid');
        }

        $newSelection = match ($this->type) {
            Type::Row => Selection::fromRow($newSelection),
            Type::Column => Selection::fromColumn($newSelection),
            Type::Cell => Selection::fromCell($newSelection),
        };

        if (null === $this->contains($newSelection->toString())) {
            return $this;
        }

        return match ($newSelection->toString()) {
            $oldSelection => $this,
            default => new self($this->type, array_replace($this->selections, [$offset => $newSelection])),
        };
    }

    public function remove(string ...$selections): self
    {
        if (in_array([], [$this->selections, $selections], true)) {
            return $this;
        }

        $keys = array_filter(array_map($this->contains(...), $selections), fn (int|null $key): bool => null !== $key);

        return match (true) {
            [] === $keys => $this,
            count($keys) === count($this->selections) => new self($this->type, []),
            default => new self($this->type, array_values(
                array_filter(
                    $this->selections,
                    fn (int $key): bool => !in_array($key, $keys, true),
                    ARRAY_FILTER_USE_KEY
                )
            )),
        };
    }

    /**
     * @throws Exception
     * @throws InvalidArgument
     * @throws ReflectionException
     * @throws SyntaxError
     *
     * @return iterable<string, Statement>
     */
    public function query(TabularDataReader $tabularDataReader): iterable
    {
        return [] === $this->selections ? [] : match ($this->type) {
            Type::Row => $this->queryByRows(),
            Type::Column => $this->queryByColumns($tabularDataReader),
            Type::Cell => $this->queryByCells($tabularDataReader),
        };
    }

    /**
     * @throws Exception
     * @throws InvalidArgument
     * @throws SyntaxError
     * @throws ReflectionException
     *
     * @return iterable<string, Statement>
     */
    private function queryByRows(): iterable
    {
        $predicate = fn (array $record, int $offset): bool => [] !== array_filter(
            $this->selections,
            fn (Selection $selection): bool => $offset >= $selection->rowStart &&
                (null === $selection->rowEnd || $offset <= $selection->rowEnd)
        );

        yield $this->toString() => Statement::create()->where($predicate);
    }

    /**
     * @throws Exception
     * @throws InvalidArgument
     * @throws ReflectionException
     * @throws SyntaxError
     *
     * @return iterable<string, Statement>
     */
    private function queryByColumns(TabularDataReader $tabularDataReader): iterable
    {
        $nbColumns = $this->getTabularDataColumnCount($tabularDataReader);
        $columns = array_reduce(
            $this->selections,
            fn (array $columns, Selection $selection): array => [
                ...$columns,
                ...match (($columnRange = $selection->columnRange())) {
                    null => range($selection->columnStart, $nbColumns - 1),
                    default => $selection->columnEnd > $nbColumns || $selection->columnEnd === -1 ? range($selection->columnStart, $nbColumns - 1) : $columnRange,
                }
            ],
            []
        );

        if ([] !== $columns) {
            yield $this->toString() => Statement::create()->select(...$columns);
        }
    }

    /**
     * @throws Exception
     * @throws InvalidArgument
     * @throws ReflectionException
     * @throws SyntaxError
     *
     * @return iterable<string, Statement>
     */
    private function queryByCells(TabularDataReader $tabularDataReader): iterable
    {
        $nbColumns = $this->getTabularDataColumnCount($tabularDataReader);
        $mapper = fn (Selection $selection): Statement => Statement::create()
            ->where(
                fn (array $record, int $offset): bool => $offset >= $selection->rowStart &&
                    (null === $selection->rowEnd || $offset <= $selection->rowEnd)
            )
            ->select(
                ...match (($columnRange = $selection->columnRange())) {
                    null => range($selection->columnStart, $nbColumns - 1),
                    default => $selection->columnEnd > $nbColumns || $selection->columnEnd === -1 ? range($selection->columnStart, $nbColumns - 1) : $columnRange,
                }
            );

        foreach ($this->selections as $selection) {
            yield Type::Cell->value.'='.$selection->toString() => $mapper($selection);
        }
    }

    private function getTabularDataColumnCount(TabularDataReader $tabularDataReader): int
    {
        $header = $tabularDataReader->getHeader();

        return count(match ($header) {
            [] => $tabularDataReader->first(),
            default => $header,
        });
    }
}
