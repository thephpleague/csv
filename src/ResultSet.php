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
use CallbackFilterIterator;
use Closure;
use Generator;
use Iterator;
use JsonSerializable;
use League\Csv\Serializer\Denormalizer;
use League\Csv\Serializer\MappingFailed;
use League\Csv\Serializer\TypeCastingFailed;
use LimitIterator;

use function array_filter;
use function array_flip;
use function array_key_exists;
use function array_reduce;
use function array_search;
use function array_values;
use function is_string;
use function iterator_count;

/**
 * Represents the result set of a {@link Reader} processed by a {@link Statement}.
 *
 * @template TValue of array
 */
class ResultSet implements TabularDataReader, JsonSerializable
{
    /** @var array<string> */
    protected array $header;

    /* @var Iterator<array-key, array<array-key, mixed>> */
    protected Iterator $records;

    /**
     * @param Iterator|array<array-key, array<array-key, mixed>> $records
     * @param array<string> $header
     *
     * @throws SyntaxError
     */
    public function __construct(Iterator|array $records, array $header = [])
    {
        $header === array_filter($header, is_string(...)) || throw SyntaxError::dueToInvalidHeaderColumnNames();

        $this->header = array_values($this->validateHeader($header));
        $this->records = match (true) {
            $records instanceof Iterator => $records,
            default => new ArrayIterator($records),
        };
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
     * Returns a new instance from a collection without header.
     *
     * @throws SyntaxError
     */
    public static function createFromRecords(iterable $records = []): self
    {
        return new self(MapIterator::toIterator($records));
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
     * @param Closure(array<mixed>, array-key=): mixed $callback
     */
    public function each(Closure $callback): bool
    {
        foreach ($this as $offset => $record) {
            if (false === $callback($record, $offset)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param Closure(array<mixed>, array-key=): bool $callback
     */
    public function exists(Closure $callback): bool
    {
        foreach ($this as $offset => $record) {
            if (true === $callback($record, $offset)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param Closure(TInitial|null, array<mixed>, array-key=): TInitial $callback
     * @param TInitial|null $initial
     *
     * @template TInitial
     *
     * @return TInitial|null
     */
    public function reduce(Closure $callback, mixed $initial = null): mixed
    {
        foreach ($this as $offset => $record) {
            $initial = $callback($initial, $record, $offset);
        }

        return $initial;
    }

    /**
     * @param positive-int $recordsCount
     *
     * @throws InvalidArgument
     *
     * @return iterable<TabularDataReader>
     */
    public function chunkBy(int $recordsCount): iterable
    {
        $recordsCount > 0 || throw InvalidArgument::dueToInvalidChunkSize($recordsCount, __METHOD__);

        $header = $this->getHeader();
        $records = [];
        $nbRecords = 0;
        foreach ($this->getRecords() as $record) {
            $records[] = $record;
            ++$nbRecords;
            if ($nbRecords === $recordsCount) {
                yield new self($records, $header);
                $records = [];
                $nbRecords = 0;
            }
        }

        if ([] !== $records) {
            yield new self($records, $header);
        }
    }

    /**
     * @param array<string> $headers
     */
    public function mapHeader(array $headers): TabularDataReader
    {
        return Statement::create()->process($this, $headers);
    }

    public function filter(Query\Predicate|Closure $predicate): TabularDataReader
    {
        return Statement::create()->where($predicate)->process($this);
    }

    public function slice(int $offset, ?int $length = null): TabularDataReader
    {
        return Statement::create()->offset($offset)->limit($length ?? -1)->process($this);
    }

    public function sorted(Query\Sort|Closure $orderBy): TabularDataReader
    {
        return Statement::create()->orderBy($orderBy)->process($this);
    }

    public function select(string|int ...$columns): TabularDataReader
    {
        if ([] === $columns) {
            return $this;
        }

        $recordsHeader = $this->getHeader();
        $hasHeader = [] !== $recordsHeader;
        $selectColumn = function (array $header, string|int $field) use ($recordsHeader, $hasHeader): array {
            if (is_string($field)) {
                $index = array_search($field, $recordsHeader, true);
                if (false === $index) {
                    throw InvalidArgument::dueToInvalidColumnIndex($field, 'offset', __METHOD__);
                }

                $header[$index] = $field;

                return $header;
            }

            if ($hasHeader && !array_key_exists($field, $recordsHeader)) {
                throw InvalidArgument::dueToInvalidColumnIndex($field, 'offset', __METHOD__);
            }

            $header[$field] = $recordsHeader[$field] ?? $field;

            return $header;
        };

        /** @var array<string> $header */
        $header = array_reduce($columns, $selectColumn, []);
        $callback = function (array $record) use ($header): array {
            $element = [];
            $row = array_values($record);
            foreach ($header as $offset => $headerName) {
                $element[$headerName] = $row[$offset] ?? null;
            }

            return $element;
        };

        return new self(new MapIterator($this, $callback), $hasHeader ? $header : []);
    }

    /**
     * EXPERIMENTAL WARNING! This method implementation will change in the next major point release.
     *
     * Extract all found fragment identifiers for the specifield tabular data
     *
     * @experimental since version 9.12.0
     *
     * @throws SyntaxError
     * @return iterable<int, TabularDataReader>
     */
    public function matching(string $expression): iterable
    {
        return FragmentFinder::create()->findAll($expression, $this);
    }

    /**
     * EXPERIMENTAL WARNING! This method implementation will change in the next major point release.
     *
     * Extract the first found fragment identifier of the tabular data or returns null
     *
     * @experimental since version 9.12.0
     *
     * @throws SyntaxError
     */
    public function matchingFirst(string $expression): ?TabularDataReader
    {
        return FragmentFinder::create()->findFirst($expression, $this);
    }

    /**
     * EXPERIMENTAL WARNING! This method implementation will change in the next major point release.
     *
     * Extract the first found fragment identifier of the tabular data or fail
     *
     * @experimental since version 9.12.0
     *
     * @throws SyntaxError
     * @throws FragmentNotFound
     */
    public function matchingFirstOrFail(string $expression): TabularDataReader
    {
        return FragmentFinder::create()->findFirstOrFail($expression, $this);
    }

    /**
     * @param array<string> $header
     *
     * @throws Exception
     *
     * @return Iterator<array-key, TValue>
     */
    public function getRecords(array $header = []): Iterator
    {
        return $this->combineHeader($this->prepareHeader($header));
    }

    /**
     * @template T of object
     * @param class-string<T> $className
     * @param array<string> $header
     *
     * @throws Exception
     * @throws MappingFailed
     * @throws TypeCastingFailed
     * @return iterator<T>
     */
    public function getRecordsAsObject(string $className, array $header = []): Iterator
    {
        $header = $this->prepareHeader($header);

        return Denormalizer::assignAll(
            $className,
            $this->combineHeader($header),
            $header
        );
    }

    /**
     * @param array<string> $header
     *
     * @throws SyntaxError
     * @return array<string>
     */
    protected function prepareHeader(array $header): array
    {
        $header === array_filter($header, is_string(...)) || throw SyntaxError::dueToInvalidHeaderColumnNames();
        $header = $this->validateHeader($header);
        if ([] === $header) {
            $header = $this->header;
        }
        return $header;
    }

    /**
     * Combines the header to each record if present.
     *
     * @param array<array-key, string|int> $header
     *
     * @return Iterator<array-key, TValue>
     */
    protected function combineHeader(array $header): Iterator
    {
        return match (true) {
            [] === $header => $this->records,
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

    public function value(int|string $column = 0): mixed
    {
        return match (true) {
            is_string($column) => $this->first()[$column] ?? null,
            default => array_values($this->first())[$column] ?? null,
        };
    }

    public function nth(int $nth_record): array
    {
        0 <= $nth_record || throw InvalidArgument::dueToInvalidRecordOffset($nth_record, __METHOD__);

        $iterator = new LimitIterator($this->getIterator(), $nth_record, 1);
        $iterator->rewind();

        /** @var array|null $result */
        $result = $iterator->current();

        return $result ?? [];
    }

    /**
     * @param class-string $className
     *
     * @throws InvalidArgument
     */
    public function nthAsObject(int $nth, string $className, array $header = []): ?object
    {
        $header = $this->prepareHeader($header);
        $record = $this->nth($nth);
        if ([] === $record) {
            return null;
        }

        if ([] === $header || $this->header === $header) {
            return Denormalizer::assign($className, $record);
        }

        $row = array_values($record);
        $record = [];
        foreach ($header as $offset => $headerName) {
            $record[$headerName] = $row[$offset] ?? null;
        }

        return Denormalizer::assign($className, $record);
    }

    /**
     * @param class-string $className
     *
     * @throws InvalidArgument
     */
    public function firstAsObject(string $className, array $header = []): ?object
    {
        return $this->nthAsObject(0, $className, $header);
    }

    public function fetchColumn(string|int $index = 0): Iterator
    {
        return $this->yieldColumn(
            $this->getColumnIndex($index, 'offset', __METHOD__)
        );
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
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @see ResultSet::nth()
     * @deprecated since version 9.9.0
     * @codeCoverageIgnore
     */
    public function fetchOne(int $nth_record = 0): array
    {
        return $this->nth($nth_record);
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @see Reader::getRecordsAsObject()
     * @deprecated Since version 9.15.0
     * @codeCoverageIgnore
     *
     * @param class-string $className
     * @param array<string> $header
     *
     * @throws Exception
     * @throws MappingFailed
     * @throws TypeCastingFailed
     */
    public function getObjects(string $className, array $header = []): Iterator
    {
        return $this->getRecordsAsObject($className, $header);
    }
}
