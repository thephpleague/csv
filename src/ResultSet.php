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
use Deprecated;
use Generator;
use Iterator;
use JsonSerializable;
use League\Csv\Serializer\Denormalizer;
use League\Csv\Serializer\MappingFailed;
use League\Csv\Serializer\TypeCastingFailed;
use LimitIterator;
use mysqli_result;
use PDOStatement;
use PgSql\Result;
use ReflectionException;
use RuntimeException;
use SQLite3Result;
use Throwable;

use function array_filter;
use function array_flip;
use function array_key_exists;
use function array_reduce;
use function array_search;
use function array_values;
use function is_int;
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
     * @internal
     *
     * @see self::from() for public API usage
     *
     * @param Iterator|array<array-key, array<array-key, mixed>> $records
     * @param array<string> $header
     *
     * @throws SyntaxError
     */
    public function __construct(Iterator|array $records = [], array $header = [])
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

    /**
     * Returns a new instance from a tabular data implementing object.
     *
     * @throws RuntimeException|SyntaxError If the column names can not be found
     */
    public static function tryFrom(PDOStatement|Result|mysqli_result|SQLite3Result|TabularData $tabularData): ?self
    {
        try {
            return self::from($tabularData);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Returns a new instance from a tabular data implementing object.
     *
     * @throws RuntimeException|SyntaxError If the column names can not be found
     */
    public static function from(PDOStatement|Result|mysqli_result|SQLite3Result|TabularData $tabularData): self
    {
        if (!$tabularData instanceof TabularData) {
            /** @var ArrayIterator<array-key, array<array-key, mixed>> $data */
            $data = new ArrayIterator();
            foreach (RdbmsResult::rows($tabularData) as $offset => $row) {
                $data[$offset] = $row;
            }

            return new self($data, RdbmsResult::columnNames($tabularData));
        }

        return new self($tabularData->getRecords(), $tabularData->getHeader());
    }

    public function __destruct()
    {
        unset($this->records);
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
     * @param callable(array<mixed>, array-key=): mixed $callback
     */
    public function each(callable $callback): bool
    {
        foreach ($this as $offset => $record) {
            if (false === $callback($record, $offset)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param callable(array<mixed>, array-key=): bool $callback
     */
    public function exists(callable $callback): bool
    {
        foreach ($this as $offset => $record) {
            if (true === $callback($record, $offset)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param callable(TInitial|null, array<mixed>, array-key=): TInitial $callback
     * @param TInitial|null $initial
     *
     * @template TInitial
     *
     * @return TInitial|null
     */
    public function reduce(callable $callback, mixed $initial = null): mixed
    {
        foreach ($this as $offset => $record) {
            $initial = $callback($initial, $record, $offset);
        }

        return $initial;
    }

    /**
     * Run a map over each container members.
     *
     * @template TMap
     *
     * @param callable(array, int): TMap $callback
     *
     * @return Iterator<TMap>
     */
    public function map(callable $callback): Iterator
    {
        return MapIterator::fromIterable($this, $callback);
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
        return (new Statement())->process($this, $headers);
    }

    public function filter(Query\Predicate|Closure $predicate): TabularDataReader
    {
        return (new Statement())->where($predicate)->process($this);
    }

    public function slice(int $offset, ?int $length = null): TabularDataReader
    {
        return (new Statement())->offset($offset)->limit($length ?? -1)->process($this);
    }

    public function sorted(Query\Sort|Closure $orderBy): TabularDataReader
    {
        return (new Statement())->orderBy($orderBy)->process($this);
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

    public function selectAllExcept(string|int ...$columns): TabularDataReader
    {
        if ([] === $columns) {
            return $this;
        }

        $recordsHeader = $this->getHeader();
        $hasHeader = [] !== $recordsHeader;
        $selectColumnsToSkip = function (array $res, string|int $column) use ($recordsHeader, $hasHeader): array {
            if ($hasHeader) {
                if (is_string($column)) {
                    $index = array_search($column, $recordsHeader, true);
                    if (false === $index) {
                        throw InvalidArgument::dueToInvalidColumnIndex($column, 'offset', __METHOD__);
                    }

                    $res[$index] = 1;

                    return $res;
                }

                if (!array_key_exists($column, $recordsHeader)) {
                    throw InvalidArgument::dueToInvalidColumnIndex($column, 'offset', __METHOD__);
                }

                $res[$column] = 1;

                return $res;
            }

            if (!is_int($column)) {
                throw InvalidArgument::dueToInvalidColumnIndex($column, 'offset', __METHOD__);
            }

            $res[$column] = 1;

            return $res;
        };

        /** @var array<int> $columnsToSkip */
        $columnsToSkip = array_reduce($columns, $selectColumnsToSkip, []);
        $callback = function (array $record) use ($columnsToSkip): array {
            $element = [];
            $index = 0;
            foreach ($record as $name => $value) {
                if (!array_key_exists($index, $columnsToSkip)) {
                    $element[$name] = $value;
                }
                ++$index;
            }

            return $element;
        };

        $newHeader = [];
        if ($hasHeader) {
            $newHeader = array_values(
                array_filter(
                    $recordsHeader,
                    fn (string|int $key) => !array_key_exists($key, $columnsToSkip),
                    ARRAY_FILTER_USE_KEY
                )
            );
        }

        return new self(new MapIterator($this, $callback), $newHeader);
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
        return (new FragmentFinder())->findAll($expression, $this);
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
        return (new FragmentFinder())->findFirst($expression, $this);
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
        return (new FragmentFinder())->findFirstOrFail($expression, $this);
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

    public function last(): array
    {
        $last = [];
        foreach ($this->getRecords() as $last); /* @phpstan-ignore-line */

        return $last;
    }

    public function value(int|string $column = 0): mixed
    {
        return match (true) {
            is_string($column) => $this->first()[$column] ?? null,
            default => array_values($this->first())[$column] ?? null,
        };
    }

    public function nth(int $nth): array
    {
        0 <= $nth || throw InvalidArgument::dueToInvalidRecordOffset($nth, __METHOD__);

        $iterator = new LimitIterator($this->getIterator(), $nth, 1);
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

    /**
     * @param class-string $className
     *
     * @throws SyntaxError
     * @throws ReflectionException
     */
    public function lastAsObject(string $className, array $header = []): ?object
    {
        $header = $this->prepareHeader($header);
        $record = $this->last();
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

    public function fetchColumn(string|int $index = 0): Iterator
    {
        return $this->yieldColumn(
            $this->getColumnIndex($index, 'offset', __METHOD__)
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

    public function fetchPairs(string|int $offset_index = 0, string|int $value_index = 1): Iterator
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
     * @throws Exception
     *
     * @deprecated since version 9.23.0
     * @codeCoverageIgnore
     *
     * @see ResultSet::fetchColumn()
     */
    #[Deprecated(message:'use League\Csv\Resultset::fetchColumn() instead', since:'league/csv:9.23.0')]
    public function fetchColumnByName(string $name): Iterator
    {
        return $this->yieldColumn(
            $this->getColumnIndexByValue($name, 'name', __METHOD__)
        );
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @throws Exception
     *
     * @deprecated since version 9.23.0
     * @codeCoverageIgnore
     *
     * @see ResultSet::fetchColumn()
     */
    #[Deprecated(message:'use League\Csv\Resultset::fetchColumn() instead', since:'league/csv:9.23.0')]
    public function fetchColumnByOffset(int $offset): Iterator
    {
        return $this->yieldColumn(
            $this->getColumnIndexByKey($offset, 'offset', __METHOD__)
        );
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @see ResultSet::nth()
     * @deprecated since version 9.9.0
     * @codeCoverageIgnore
     */
    #[Deprecated(message:'use League\Csv\Resultset::nth() instead', since:'league/csv:9.9.0')]
    public function fetchOne(int $nth_record = 0): array
    {
        return $this->nth($nth_record);
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @see ResultSet::getRecordsAsObject()
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
    #[Deprecated(message:'use League\Csv\ResultSet::getRecordsAsObject() instead', since:'league/csv:9.15.0')]
    public function getObjects(string $className, array $header = []): Iterator
    {
        return $this->getRecordsAsObject($className, $header);
    }

    /**
     * Returns a new instance from an object implementing the TabularDataReader interface.
     *
     * @throws SyntaxError
     */
    #[Deprecated(message:'use League\Csv\ResultSet::from() instead', since:'league/csv:9.22.0')]
    public static function createFromTabularDataReader(TabularDataReader $reader): self
    {
        return self::from($reader);
    }

    /**
     * Returns a new instance from a collection without header.
     */
    #[Deprecated(message:'use League\Csv\ResultSet::from() instead', since:'league/csv:9.22.0')]
    public static function createFromRecords(iterable $records = []): self
    {
        return new self(MapIterator::toIterator($records));
    }
}
