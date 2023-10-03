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

use CallbackFilterIterator;
use Closure;
use Generator;
use Iterator;
use JsonSerializable;
use LimitIterator;

use function array_filter;
use function array_flip;
use function array_search;
use function is_string;
use function iterator_count;

/**
 * Represents the result set of a {@link Reader} processed by a {@link Statement}.
 */
class ResultSet implements TabularDataReader, JsonSerializable
{
    /** @var array<string> */
    protected array $header;

    /**
     * @param Iterator<array-key, array<array-key, string|null>> $records
     * @param array<string> $header
     *
     * @throws SyntaxError
     */
    public function __construct(protected Iterator $records, array $header = [])
    {
        if ($header !== array_filter($header, is_string(...))) {
            throw SyntaxError::dueToInvalidHeaderColumnNames();
        }

        $this->header = array_values($this->validateHeader($header));
    }

    /**
     * @throws SyntaxError if the header syntax is invalid
     */
    protected function validateHeader(array $header): array
    {
        return match (true) {
            $header !== array_unique($header) => throw SyntaxError::dueToDuplicateHeaderColumnNames($header),
            [] !== array_filter(array_keys($header), fn (string|int $value) => !is_int($value) || $value < 0) => throw new SyntaxError('The header mapper indexes should only contain positive integer or 0.'),
            default => $header,
        };
    }

    public function __destruct()
    {
        unset($this->records);
    }

    /**
     * Returns a new instance from an object implementing the TabularDataReader interface.
     *
     * @throws SyntaxError
     */
    public static function createFromTabularDataReader(TabularDataReader $reader): self
    {
        return new self($reader->getRecords(), $reader->getHeader());
    }

    /**
     * Returns the header associated with the result set.
     *
     * @return array<string>
     */
    public function getHeader(): array
    {
        return $this->header;
    }

    /**
     * @throws SyntaxError
     */
    public function getIterator(): Iterator
    {
        return $this->getRecords();
    }

    /**
     * @param Closure(array<string|null>, array-key=): mixed $closure
     */
    public function each(Closure $closure): bool
    {
        foreach ($this as $offset => $record) {
            if (false === $closure($record, $offset)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param Closure(array<string|null>, array-key=): bool $closure
     */
    public function exists(Closure $closure): bool
    {
        foreach ($this as $offset => $record) {
            if (true === $closure($record, $offset)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param Closure(TInitial|null, array<string|null>, array-key=): TInitial $closure
     * @param TInitial|null $initial
     *
     * @template TInitial
     *
     * @return TInitial|null
     */
    public function reduce(Closure $closure, mixed $initial = null): mixed
    {
        foreach ($this as $offset => $record) {
            $initial = $closure($initial, $record, $offset);
        }

        return $initial;
    }

    public function filter(Closure $closure): TabularDataReader
    {
        return Statement::create()->where($closure)->process($this);
    }

    public function slice(int $offset, int $length = null): TabularDataReader
    {
        return Statement::create()->offset($offset)->limit($length ?? -1)->process($this);
    }

    public function sorted(Closure $orderBy): TabularDataReader
    {
        return Statement::create()->orderBy($orderBy)->process($this);
    }

    public function select(string|int ...$columns): TabularDataReader
    {
        $header = [];
        $documentHeader = $this->getHeader();
        $hasNoHeader = [] === $documentHeader;
        foreach ($columns as $field) {
            if (is_string($field)) {
                if ($hasNoHeader) {
                    throw new InvalidArgument(__METHOD__.' can only use named column if the tabular data has a non-empty header.');
                }

                $index = array_search($field, $this->header, true);
                if (false === $index) {
                    throw InvalidArgument::dueToInvalidColumnIndex($field, 'offset', __METHOD__);
                }

                $header[$index] = $field;
                continue;
            }

            if (!$hasNoHeader && !array_key_exists($field, $documentHeader)) {
                throw InvalidArgument::dueToInvalidColumnIndex($field, 'offset', __METHOD__);
            }

            $header[$field] = $documentHeader[$field] ?? $field;
        }

        return new self($this->combineHeader($header), $documentHeader);
    }

    public function matching(string $expression): iterable
    {
        return (new FragmentFinder())->all($expression, $this);
    }

    public function firstMatching(string $expression): ?TabularDataReader
    {
        return (new FragmentFinder())->first($expression, $this);
    }

    /**
     * @throws SyntaxError
     * @throws FragmentNotFound
     */
    public function firstOrFailMatching(string $expression): TabularDataReader
    {
        return (new FragmentFinder())->firstOrFail($expression, $this);
    }

    /**
     * @param array<string> $header
     *
     * @throws SyntaxError
     *
     * @return Iterator<array-key, array<array-key, string|null>>
     */
    public function getRecords(array $header = []): Iterator
    {
        if ($header !== array_filter($header, is_string(...))) {
            throw SyntaxError::dueToInvalidHeaderColumnNames();
        }

        yield from $this->combineHeader($header);
    }

    /**
     * Combines the header to each record if present.
     *
     * @param array<array-key, string|int> $header
     *
     * @return Iterator<array-key, array<array-key, string|null>>
     */
    protected function combineHeader(array $header): Iterator
    {
        $header = $this->validateHeader($header);
        if ([] === $header) {
            $header = $this->header;
        }

        return match (true) {
            $header === $this->header, [] === $header => $this->records,
            default => new MapIterator($this->records, function (array $record) use ($header): array {
                $assocRecord = [];
                $row = array_values($record);
                foreach ($header as $offset => $headerName) {
                    $assocRecord[$headerName] = $row[$offset] ?? null;
                }

                return $assocRecord;
            }),
        };
    }

    public function count(): int
    {
        return iterator_count($this->records);
    }

    public function jsonSerialize(): array
    {
        return array_values([...$this->records]);
    }

    public function first(): array
    {
        return $this->nth(0);
    }

    public function nth(int $nth_record): array
    {
        if ($nth_record < 0) {
            throw InvalidArgument::dueToInvalidRecordOffset($nth_record, __METHOD__);
        }

        $iterator = new LimitIterator($this->records, $nth_record, 1);
        $iterator->rewind();

        /** @var array|null $result */
        $result = $iterator->current();

        return $result ?? [];
    }

    /**
     * @throws Exception
     */
    public function fetchColumnByName(string $name): Iterator
    {
        return $this->yieldColumn(
            $this->getColumnIndexByValue($name, 'name', __METHOD__)
        );
    }

    /**
     * @throws Exception
     */
    public function fetchColumnByOffset(int $offset): Iterator
    {
        return $this->yieldColumn(
            $this->getColumnIndexByKey($offset, 'offset', __METHOD__)
        );
    }

    protected function yieldColumn(string|int $offset): Generator
    {
        yield from new MapIterator(
            new CallbackFilterIterator($this->records, fn (array $record): bool => isset($record[$offset])),
            fn (array $record): string => $record[$offset]
        );
    }

    /**
     * Filters a column name against the header if any.
     *
     * @throws InvalidArgument if the field is invalid or not found
     */
    protected function getColumnIndex(string|int $field, string $type, string $method): string|int
    {
        return match (true) {
            is_string($field) => $this->getColumnIndexByValue($field, $type, $method),
            default => $this->getColumnIndexByKey($field, $type, $method),
        };
    }

    /**
     * Returns the selected column name.
     *
     * @throws InvalidArgument if the column is not found
     */
    protected function getColumnIndexByValue(string $value, string $type, string $method): string
    {
        return match (true) {
            false === array_search($value, $this->header, true) => throw InvalidArgument::dueToInvalidColumnIndex($value, $type, $method),
            default => $value,
        };
    }

    /**
     * Returns the selected column name according to its offset.
     *
     * @throws InvalidArgument if the field is invalid or not found
     */
    protected function getColumnIndexByKey(int $index, string $type, string $method): int|string
    {
        return match (true) {
            $index < 0 => throw InvalidArgument::dueToInvalidColumnIndex($index, $type, $method),
            [] === $this->header => $index,
            false !== ($value = array_search($index, array_flip($this->header), true)) => $value,
            default => throw InvalidArgument::dueToInvalidColumnIndex($index, $type, $method),
        };
    }

    public function fetchPairs($offset_index = 0, $value_index = 1): Iterator
    {
        $offset = $this->getColumnIndex($offset_index, 'offset', __METHOD__);
        $value = $this->getColumnIndex($value_index, 'value', __METHOD__);

        $iterator = new MapIterator(
            new CallbackFilterIterator($this->records, fn (array $record): bool => isset($record[$offset])),
            fn (array $record): array => [$record[$offset], $record[$value] ?? null]
        );

        /** @var array{0:int|string, 1:string|null} $pair */
        foreach ($iterator as $pair) {
            yield $pair[0] => $pair[1];
        }
    }

    /**
     * @deprecated since version 9.9.0
     *
     * @see ::nth
     *
     * @codeCoverageIgnore
     */
    public function fetchOne(int $nth_record = 0): array
    {
        return $this->nth($nth_record);
    }

    /**
     * @deprecated since version 9.8.0
     *
     * @see ::fetchColumnByName
     * @see ::fetchColumnByOffset
     *
     * @codeCoverageIgnore
     * @throws Exception
     */
    public function fetchColumn($index = 0): Iterator
    {
        return $this->yieldColumn(
            $this->getColumnIndex($index, 'offset', __METHOD__)
        );
    }
}
