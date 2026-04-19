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

use function array_key_exists;
use function array_keys;
use function count;

final class FieldMetadata implements Countable, IteratorAggregate
{
    private readonly array $data;

    public function __construct(iterable $data = [])
    {
        $newData = [];
        foreach ($data as $key => $value) {
            self::assertNoDuplicate($newData, $key);
            $newData[$key] = $value;
        }

        $this->data = $newData;
    }

    private static function assertNoDuplicate(array $data, string|int $key): void
    {
        ! array_key_exists($key, $data) || throw new ValueError('The key already exists: '.$key);
    }

    public function count(): int
    {
        return count($this->data);
    }

    /**
     * @return Iterator<array-key, mixed>
     */
    public function getIterator(): Iterator
    {
        yield from $this->data;
    }

    public function all(): array
    {
        return $this->data;
    }

    public function isEmpty(): bool
    {
        return [] === $this->data;
    }

    /**
     * @return list<array-key>
     */
    public function keys(): array
    {
        return array_keys($this->data);
    }

    public function has(int|string $offset): bool
    {
        return array_key_exists($offset, $this->data);
    }

    public function get(int|string $offset): mixed
    {
        return $this->has($offset) ? $this->data[$offset] : throw new ValueError('The key does not exist: '.$offset);
    }

    public function union(FieldMetadata ...$metadatas): self
    {
        if ([] === $metadatas) {
            return $this;
        }

        $newData = $this->data;
        foreach ($metadatas as $metadata) {
            foreach ($metadata->data as $key => $value) {
                self::assertNoDuplicate($newData, $key);
                $newData[$key] = $value;
            }
        }

        return new self($newData);
    }
}
