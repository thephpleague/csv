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
use Iterator;
use League\Csv\Query\Constraint\Criteria;
use League\Csv\Query\Predicate;
use League\Csv\Serializer\Denormalizer;
use League\Csv\Serializer\MappingFailed;
use League\Csv\Serializer\TypeCastingFailed;
use mysqli_result;
use PDOStatement;
use PgSql\Result;
use ReflectionException;
use RuntimeException;
use SQLite3Result;

use function array_combine;
use function array_diff;
use function array_fill_keys;
use function array_filter;
use function array_is_list;
use function array_key_exists;
use function array_key_first;
use function array_key_last;
use function array_keys;
use function array_map;
use function array_push;
use function array_reduce;
use function array_unique;
use function array_values;
use function count;
use function in_array;
use function is_int;
use function sort;

use const ARRAY_FILTER_USE_KEY;

final class Buffer implements TabularData
{
    public const INCLUDE_HEADER = 1;
    public const EXCLUDE_HEADER = 2;

    /** @var list<string>|array{} */
    private readonly array $header;
    /** @var list<string>|array{} */
    private readonly array $sortedHeader;
    /** @var array<string, null> */
    private readonly array $nullRecord;
    /** @var array<int, list<mixed>> */
    private array $rows = [];
    /** @var array<Closure(array): bool> callable collection to validate the record before insertion. */
    private array $validators = [];
    /** @var array<Closure(array): array> collection of Closure to format the record before reading. */
    private array $formatters = [];

    /**
     * @param list<string>|array{} $header
     *
     * @throws SyntaxError
     */
    public function __construct(array $header = [])
    {
        $this->header = match (true) {
            !array_is_list($header) => throw new SyntaxError('The header must be a list of unique column names.'),
            $header !== array_filter($header, is_string(...)) => throw SyntaxError::dueToInvalidHeaderColumnNames(),
            $header !== array_unique($header) => throw SyntaxError::dueToDuplicateHeaderColumnNames($header),
            default => $header,
        };
        sort($header);
        $this->sortedHeader = $header;
        $this->nullRecord = array_fill_keys($this->header, null);
    }

    /**
     * Returns a new instance from a tabular data implementing object.
     *
     * @throws RuntimeException|SyntaxError If the column names can not be found
     */
    public static function from(PDOStatement|Result|mysqli_result|SQLite3Result|TabularData|TabularDataProvider $dataStorage, int $options = self::INCLUDE_HEADER): self
    {
        if ($dataStorage instanceof TabularDataProvider) {
            $dataStorage = $dataStorage->getTabularData();
        }

        /** @var Iterator<int, array> $rows */
        $rows = $dataStorage instanceof TabularData ? $dataStorage->getRecords() : RdbmsResult::rows($dataStorage);
        $instance = new self(match (true) {
            self::EXCLUDE_HEADER === $options => [],
            $dataStorage instanceof TabularData => $dataStorage->getHeader(),
            default => RdbmsResult::columnNames($dataStorage),
        });

        /**
         * @var int $offset
         * @var list<mixed> $row
         */
        foreach (new MapIterator($rows, fn (array $record): array => array_values($record)) as $offset => $row) {
            $instance->rows[$offset] = $row;
        }

        return $instance;
    }

    /**
     * @throws CannotInsertRecord
     * @throws Exception
     */
    public function to(TabularDataWriter $dataStorage, int $options = self::INCLUDE_HEADER): int
    {
        $bytes = 0;
        $header = $this->getHeader();
        if (self::INCLUDE_HEADER === $options && [] !== $header) {
            $bytes += $dataStorage->insertOne($header);
        }

        return $bytes + $dataStorage->insertAll($this->getRecords());
    }

    public function isEmpty(): bool
    {
        return [] === $this->rows;
    }

    public function hasHeader(): bool
    {
        return [] !== $this->header;
    }

    public function recordCount(): int
    {
        return count($this->rows);
    }

    /**
     * @return list<string>|array{}
     */
    public function getHeader(): array
    {
        return $this->header;
    }

    /**
     * @throws SyntaxError
     *
     * @return Iterator<int, array>
     */
    public function getRecords(array $header = []): Iterator
    {
        $header = match (true) {
            [] === $header => $this->header,
            $header !== array_filter($header, is_int(...), ARRAY_FILTER_USE_KEY) => throw new SyntaxError('The header must be a list of unique column names.'),
            $header !== array_filter($header, is_string(...)) => throw SyntaxError::dueToInvalidHeaderColumnNames(),
            $header !== array_unique($header) => throw SyntaxError::dueToDuplicateHeaderColumnNames($header),
            default => $header,
        };

        return MapIterator::fromIterable($this->rows, fn (array $row): array => $this->rowToRecord($row, $header));
    }

    /**
     * @template T of object
     * @param class-string<T> $className
     * @param array<string> $header
     *
     * @throws Exception
     * @throws MappingFailed
     * @throws TypeCastingFailed
     *
     * @return iterator<T>
     */
    public function getRecordsAsObject(string $className, array $header = []): Iterator
    {
        return Denormalizer::assignAll($className, $this->getRecords($header), [] === $header ? $this->header : $header);
    }

    /**
     * Run a map over each container members.
     *
     * @template TMap
     *
     * @param callable(array, int): TMap $callback
     *
     * @throws SyntaxError
     *
     * @return Iterator<TMap>
     */
    public function map(callable $callback): Iterator
    {
        return MapIterator::fromIterable($this->getRecords(), $callback);
    }

    /**
     * @param non-negative-int $nth
     *
     * @throws InvalidArgument
     */
    public function nth(int $nth): array
    {
        if ([] === ($row = $this->nthRow($nth, __METHOD__))) {
            return [];
        }

        return $this->rowToRecord($row, $this->header);
    }

    /**
     * @template T of object
     *
     * @param non-negative-int $nth
     * @param class-string<T> $className
     * @param array<string> $header
     *
     * @throws InvalidArgument
     * @throws ReflectionException
     */
    public function nthAsObject(int $nth, string $className, array $header = []): ?object
    {
        if ([] === ($row = $this->nthRow($nth, __METHOD__))) {
            return null;
        }

        return Denormalizer::assign($className, $this->rowToRecord($row, [] !== $header ? $header : $this->header));
    }

    public function firstOffset(): ?int
    {
        return array_key_first($this->rows);
    }

    public function first(): array
    {
        return null === ($offset = $this->firstOffset()) ? [] : $this->rowToRecord($this->rows[$offset], $this->header);
    }

    /**
     * @param class-string $className
     * @param array<string> $header
     *
     * @throws ReflectionException
     */
    public function firstAsObject(string $className, array $header = []): ?object
    {
        if ([] === ($row = $this->rows[$this->firstOffset()] ?? [])) {
            return null;
        }

        return Denormalizer::assign($className, $this->rowToRecord($row, [] !== $header ? $header : $this->header));
    }

    public function lastOffset(): ?int
    {
        return array_key_last($this->rows);
    }

    public function last(): array
    {
        return null === ($offset = $this->lastOffset()) ? [] : $this->rowToRecord($this->rows[$offset], $this->header);
    }

    /**
     * @param class-string $className
     * @param array<string> $header
     *
     * @throws ReflectionException
     */
    public function lastAsObject(string $className, array $header = []): ?object
    {
        if ([] === ($row = $this->rows[$this->lastOffset()] ?? [])) {
            return null;
        }

        return Denormalizer::assign($className, $this->rowToRecord($row, [] !== $header ? $header : $this->header));
    }

    /**
     * @throws InvalidArgument
     */
    private function nthRow(int $nth, string $method): array
    {
        -1 < $nth || throw InvalidArgument::dueToInvalidRecordOffset($nth, $method);
        if (null === ($first = $this->firstOffset())) {
            return [];
        }

        $offset = $first + $nth;
        if (!array_key_exists($offset, $this->rows)) {
            return [];
        }

        return $this->rows[$nth + $first];
    }

    public function fetchColumn(int|string $index = 0): Iterator
    {
        if (is_int($index)) {
            $index > -1 || throw InvalidArgument::dueToInvalidColumnIndex($index, 'offset', __METHOD__);
            [] === $this->header || array_key_exists($index, $this->header) || throw InvalidArgument::dueToInvalidColumnIndex($index, 'name', __METHOD__);

            $iterator = new MapIterator($this->getRecords(), fn (array $row) => array_values($row));
            $iterator = new CallbackFilterIterator($iterator, fn (array $row) => array_key_exists($index, $row));

            return new MapIterator($iterator, fn (array $row) => $row[$index]);
        }

        [] !== $this->header || throw InvalidArgument::dueToInvalidColumnIndex($index, 'name', __METHOD__);
        in_array($index, $this->header, true) || throw InvalidArgument::dueToInvalidColumnIndex($index, 'name', __METHOD__);

        $iterator = new CallbackFilterIterator($this->getRecords(), fn (array $row) => array_key_exists($index, $row));

        return new MapIterator($iterator, fn (array $row) => $row[$index]);
    }

    /**
     * Adds a record validator.
     *
     * @param callable(array): bool $validator
     */
    public function addValidator(callable $validator, string $name): self
    {
        $this->validators[$name] = !$validator instanceof Closure ? $validator(...) : $validator;

        return $this;
    }

    /**
     * Adds a record formatter.
     *
     * @param callable(array): array $formatter
     */
    public function addFormatter(callable $formatter): self
    {
        $this->formatters[] = !$formatter instanceof Closure ? $formatter(...) : $formatter;

        return $this;
    }

    /**
     * @throws CannotInsertRecord
     */
    public function insert(array ...$records): int
    {
        [] !== $records || throw CannotInsertRecord::triggerOnValidation('@buffer_record_validation_on_insert', $records);

        array_push($this->rows, ...array_map($this->formatInsertRecord(...), $records));

        return count($records);
    }

    /**
     * @throws CannotInsertRecord
     * @throws SyntaxError
     */
    public function update(Predicate|Closure|callable $where, array $record): int
    {
        $record = $this->filterUpdateRecord($record);
        $updateRecord = function (array $row) use ($record): array {
            foreach ($record as $index => $value) {
                $row[$index] = $value;
            }

            return $this->validateRecord($row);
        };

        $affectedRecords = 0;
        /** @var Iterator<int, array> $iterator */
        $iterator = new MapIterator(new CallbackFilterIterator($this->getRecords(), $this->filterPredicate($where)), $updateRecord);
        foreach ($iterator as $offset => $row) {
            $this->rows[$offset] = $row;
            $affectedRecords++;
        }

        return $affectedRecords;
    }

    /**
     * @throws SyntaxError
     */
    public function delete(Predicate|Closure|callable $where): int
    {
        $affectedRecords = 0;
        foreach (new CallbackFilterIterator($this->getRecords(), $this->filterPredicate($where)) as $offset => $row) {
            unset($this->rows[$offset]);
            $affectedRecords++;
        }

        return $affectedRecords;
    }

    public function truncate(): void
    {
        $this->rows = [];
    }

    /**
     * @throws CannotInsertRecord
     */
    private function formatInsertRecord(array $record): array
    {
        $this->filterInsertRecord($record) || throw CannotInsertRecord::triggerOnValidation('@buffer_record_validation_on_insert', $record);

        return $this->validateRecord(match (true) {
            [] === $this->header => !array_is_list($record) ? array_values($record) : $record,
            array_is_list($record) => array_combine($this->header, $record),
            default => [...$this->nullRecord, ...$record],
        });
    }

    private function filterInsertRecord(array $record): bool
    {
        if ([] === $this->header) {
            return true;
        }

        if (array_is_list($record)) {
            return count($record) === count($this->header);
        }

        $keys = array_keys($record);
        sort($keys);

        return $keys === $this->sortedHeader;
    }

    /**
     * @throws CannotInsertRecord
     */
    private function filterUpdateRecord(array $record): array
    {
        [] !== $record || throw CannotInsertRecord::triggerOnValidation('@buffer_record_validation_on_update', $record);
        if (array_is_list($record)) {
            return $this->rowToRecord($record, $this->header);
        }

        $keys = array_keys($record);

        return match (true) {
            $keys === array_filter($keys, is_int(...)) => $record,
            $keys !== array_filter($keys, is_string(...)),
            [] !== array_diff($keys, $this->header) => throw CannotInsertRecord::triggerOnValidation('@buffer_record_validation_on_update', $record),
            default => $record,
        };
    }

    /**
     * Validates a record.
     *
     * @throws CannotInsertRecord If the validation failed
     */
    private function validateRecord(array $record): array
    {
        $record = array_reduce($this->formatters, fn (array $record, callable $formatter): array => $formatter($record), $record);
        foreach ($this->validators as $name => $validator) {
            true === $validator($record) || throw CannotInsertRecord::triggerOnValidation($name, $record);
        }

        return !array_is_list($record) ? array_values($record) : $record;
    }

    private function filterPredicate(Predicate|Closure|callable $predicate): Predicate
    {
        return !$predicate instanceof Predicate ? Criteria::all($predicate) : $predicate;
    }

    private function rowToRecord(array $row, array $header): array
    {
        if ([] === $header) {
            return $row;
        }

        $record = [];
        foreach ($header as $offset => $headerName) {
            $record[$headerName] = $row[$offset] ?? null;
        }

        return $record;
    }
}
