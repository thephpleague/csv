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
use ValueError;

use function array_filter;
use function array_flip;
use function array_key_exists;
use function array_values;
use function count;

/**
 * @implements IteratorAggregate<Field>
 */
final class FieldList implements Countable, IteratorAggregate
{
    /** @var list<Field> */
    private array $fields;

    public function __construct(Field ...$fields)
    {
        $this->fields = array_values($fields);
    }

    public static function default(): self
    {
        return new self(
            new BooleanField(),
            new NumericField(),
            new JsonField(),
        );
    }

    public function isEmpty(): bool
    {
        return [] === $this->fields;
    }

    public function count(): int
    {
        return count($this->fields);
    }

    /**
     * @return Iterator<Field>
     */
    public function getIterator(): Iterator
    {
        yield from $this->fields;
    }

    /**
     * @return list<Field>
     */
    public function all(): array
    {
        return $this->fields;
    }

    public function first(): ?Field
    {
        return $this->nth(0);
    }

    public function last(): ?Field
    {
        return $this->nth(-1);
    }

    public function nth(int $offset): ?Field
    {
        return $this->fields[$this->offset($offset)] ?? null;
    }

    public function get(int $offset): Field
    {
        return $this->nth($offset) ?? throw new ValueError('Invalid field offset: '.$offset);
    }

    private function offset(int $offset): ?int
    {
        if ($offset < 0) {
            $offset += count($this->fields);
        }

        return array_key_exists($offset, $this->fields) ? $offset : null;
    }

    public function append(Field|self ...$items): self
    {
        $fields = self::flatten(...$items);

        return [] === $fields ? $this : new self(...$this->fields, ...$fields);
    }

    public function prepend(Field|self ...$items): self
    {
        $fields = self::flatten(...$items);

        return [] === $fields ? $this : new self(...$fields, ...$this->fields);
    }

    /**
     * @return list<Field>
     */
    private static function flatten(Field|self ...$items): array
    {
        $fields = [];
        foreach ($items as $item) {
            if ($item instanceof Field) {
                $fields[] = $item;
                continue;
            }

            foreach ($item->fields as $field) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    public function replace(int $offset, Field $field): self
    {
        $found = $this->offset($offset);
        null !== $found || throw new ValueError('the offset: '.$offset.' does not exist.');

        $fields = $this->fields;
        $fields[$found] = $field;

        return new self(...$fields);
    }

    public function removeByOffset(int ...$offsets): self
    {
        $validOffsets = [];
        foreach ($offsets as $offset) {
            $index = $this->offset($offset);
            if (null !== $index) {
                $validOffsets[] = $index;
            }
        }

        if ([] === $validOffsets) {
            return $this;
        }

        $validOffsets = array_flip($validOffsets);
        $fields = [];
        foreach ($this->fields as $offset => $field) {
            if (!isset($validOffsets[$offset])) {
                $fields[] = $field;
            }
        }

        return [] === $fields ? $this : new self(...$fields);
    }

    public function removeByType(FieldType $fieldType): self
    {
        $fields = array_filter(
            $this->fields,
            fn (Field $field): bool => $field->type() !== $fieldType
        );

        return $this->fields === $fields ? $this : new self(...$fields);
    }
}
