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

use ArrayIterator;
use Iterator;
use mysqli_result;
use OutOfBoundsException;
use PDO;
use PDOStatement;
use PgSql\Result;
use RuntimeException;
use SeekableIterator;
use SQLite3Result;
use Throwable;
use ValueError;

use function array_column;
use function array_map;
use function pg_fetch_assoc;
use function pg_field_name;
use function pg_num_fields;
use function pg_result_seek;
use function range;

use const SQLITE3_ASSOC;

final class RdbmsResult implements TabularData
{
    /**
     * @param Iterator<array-key, array<array-key, mixed>> $rows
     * @param array<string>|array{} $header
     */
    private function __construct(
        private readonly Iterator $rows,
        private readonly array $header
    ) {
    }

    /**
     * @return array<string>|array{}
     */
    public function getHeader(): array
    {
        return $this->header;
    }

    /**
     * @return Iterator<array-key, array<array-key, mixed>>
     */
    public function getIterator(): Iterator
    {
        return $this->rows;
    }

    public static function tryFrom(object $result): ?self
    {
        try {
            return self::from($result);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @throws RuntimeException If the DB result is unknown or unsupported
     */
    public static function from(object $result): self
    {
        return new self(self::rows($result), self::columnNames($result));
    }

    /**
     * @throws RuntimeException If no column names information is found.
     *
     * @return array<string>
     */
    public static function columnNames(object $result): array
    {
        return match (true) {
            $result instanceof PDOStatement => array_map(
                function (int $index) use ($result): string {
                    $metadata = $result->getColumnMeta($index);
                    false !== $metadata || throw new RuntimeException('Unable to get metadata for column '.$index);

                    return $metadata['name'];
                },
                range(0, $result->columnCount() - 1)
            ),
            $result instanceof Result => array_map(fn (int $index) => pg_field_name($result, $index), range(0, pg_num_fields($result) - 1)),
            $result instanceof mysqli_result => array_column($result->fetch_fields(), 'name'),
            $result instanceof SQLite3Result => array_map($result->columnName(...), range(0, $result->numColumns() - 1)),
            default => throw new ValueError('Unknown or unsupported RDBMS result object '.$result::class),
        };
    }

    /**
     * @return Iterator<array-key, array<array-key, mixed>>
     */
    public static function rows(object $result): Iterator
    {
        return match (true) {
            $result instanceof SQLite3Result => new class ($result) implements Iterator {
                private array|false $current;
                private int $key = 0;

                public function __construct(private readonly SQLite3Result $result)
                {
                }

                public function rewind(): void
                {
                    $this->result->reset();
                    $this->current = $this->result->fetchArray(SQLITE3_ASSOC);
                    $this->key = 0;
                }

                public function current(): array|false
                {
                    return $this->current;
                }

                public function key(): string|int|null
                {
                    return $this->key;
                }

                public function next(): void
                {
                    $this->current = $this->result->fetchArray(SQLITE3_ASSOC);
                    $this->key++;
                }

                public function valid(): bool
                {
                    return false !== $this->current;
                }
            },
            $result instanceof mysqli_result => new class ($result) implements SeekableIterator {
                private array|false|null $current;
                private int $key = 0;

                public function __construct(private readonly mysqli_result $result)
                {
                }

                public function seek(int $offset): void
                {
                    if (!$this->result->data_seek($offset)) {
                        throw new OutOfBoundsException('Unable to seek to offset '.$offset);
                    }
                }

                public function rewind(): void
                {
                    $this->seek(0);
                    $this->current = $this->result->fetch_assoc();
                    $this->key = 0;
                }

                public function current(): array|false|null
                {
                    return $this->current;
                }

                public function key(): string|int|null
                {
                    return $this->key;
                }

                public function next(): void
                {
                    $this->current = $this->result->fetch_assoc();
                    $this->key++;
                }

                public function valid(): bool
                {
                    return false !== $this->current
                        && null !== $this->current;
                }
            },
            $result instanceof Result => new class ($result) implements SeekableIterator {
                private array|false|null $current;
                private int $key = 0;

                public function __construct(private readonly Result $result)
                {
                }

                public function seek(int $offset): void
                {
                    if (!pg_result_seek($this->result, $offset)) {
                        throw new OutOfBoundsException('Unable to seek to offset '.$offset);
                    }
                }

                public function rewind(): void
                {
                    $this->seek(0);
                    $this->current = pg_fetch_assoc($this->result);
                    $this->key = 0;
                }

                public function current(): array|false|null
                {
                    return $this->current;
                }

                public function key(): string|int|null
                {
                    return $this->key;
                }

                public function next(): void
                {
                    $this->current = pg_fetch_assoc($this->result);
                    $this->key++;
                }

                public function valid(): bool
                {
                    return false !== $this->current
                        && null !== $this->current;
                }
            },
            $result instanceof PDOStatement => new class ($result) implements SeekableIterator {
                private ?ArrayIterator $cacheIterator;

                public function __construct(private readonly PDOStatement $result)
                {
                }

                public function seek(int $offset): void
                {
                    $this->cacheIterator ??= new ArrayIterator($this->result->fetchAll(PDO::FETCH_ASSOC));
                    $this->cacheIterator->seek($offset);
                }

                public function rewind(): void
                {
                    $this->cacheIterator ??= new ArrayIterator($this->result->fetchAll(PDO::FETCH_ASSOC));
                    $this->cacheIterator->rewind();
                }

                public function current(): mixed
                {
                    return $this->cacheIterator?->current() ?? false;
                }

                public function key(): string|int|null
                {
                    return $this->cacheIterator?->key() ?? null;
                }

                public function next(): void
                {
                    $this->cacheIterator?->next();
                }

                public function valid(): bool
                {
                    return $this->cacheIterator?->valid() ?? false;
                }
            },
            default => throw new ValueError('Unknown or unsupported RDBMS result object '.$result::class),
        };
    }
}
