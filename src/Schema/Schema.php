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

namespace League\Csv\Schema;

use Countable;
use Iterator;
use IteratorAggregate;
use League\Csv\MapIterator;
use League\Csv\TabularData;
use ValueError;

use function array_diff_key;
use function array_flip;
use function array_key_exists;
use function array_keys;
use function array_map;
use function count;

/**
 * @implements IteratorAggregate<array-key, Field>
 */
final class Schema implements Countable, IteratorAggregate
{
    /** @var array<array-key, Field> */
    private readonly array $fields;

    public function __construct(iterable $fields = [])
    {
        $newFields = [];
        foreach ($fields as $key => $value) {
            self::assertNoDuplicate($newFields, $key);
            $newFields[$key] = $value;
        }

        $this->fields = $newFields;
    }

    public function append(int|string $name, Field $field): self
    {
        self::assertNoDuplicate($this->fields, $name);

        return new self([...$this->fields, ...[$name => $field]]);
    }

    public function replace(int|string $name, Field $field): self
    {
        $this->has($name) || throw new ValueError('Field "'.$name.'" does not exist.');

        $fields = $this->fields;
        $fields[$name] = $field;

        return new self($fields);
    }

    public function remove(int|string ...$names): self
    {
        return [] === $names
            ? $this
            : new self(array_diff_key($this->fields, array_flip($names)));
    }

    private static function assertNoDuplicate(array $data, string|int $key): void
    {
        ! array_key_exists($key, $data) || throw new ValueError('The key already exists: '.$key);
    }

    public function count(): int
    {
        return count($this->fields);
    }

    /**
     * @return Iterator<array-key, Field>
     */
    public function getIterator(): Iterator
    {
        yield from $this->fields;
    }

    /**
     * @return array<array-key, Field>
     */
    public function all(): array
    {
        return $this->fields;
    }

    public function isEmpty(): bool
    {
        return [] === $this->fields;
    }

    /**
     * @return array<array-key, non-empty-string>
     */
    public function types(): array
    {
        return array_map(fn (Field $field) => $field->name(), $this->fields);
    }

    /**
     * @return list<array-key>
     */
    public function names(): array
    {
        return array_keys($this->fields);
    }

    public function has(int|string $offset): bool
    {
        return array_key_exists($offset, $this->fields);
    }

    public function get(int|string $offset): Field
    {
        return $this->has($offset) ? $this->fields[$offset] : throw new ValueError('The key does not exist: '.$offset);
    }

    /**
     * @template TValue
     *
     * @param callable(Field, array-key): TValue $callback
     *
     * @return Iterator<array-key, TValue>
     */
    public function map(callable $callback): Iterator
    {
        foreach ($this->fields as $name => $field) {
            yield $name => $callback($field, $name);
        }
    }

    /**
     * @return Iterator<int, array<mixed>>
     */
    public function parse(TabularData $tabularData): Iterator
    {
        return MapIterator::fromIterable($tabularData->getRecords($this->names()), $this->format(...));
    }

    public function format(array $row): array
    {
        $result = [];
        foreach ($this->fields as $column => $field) {
            $result[$column] = $field->parse($row[$column] ?? null);
        }

        return $result;
    }
}
