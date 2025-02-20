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
use League\Csv\Query\Constraint\Comparison;
use League\Csv\Query\Constraint\Criteria;
use League\Csv\Query\Constraint\Offset;
use League\Csv\Query\Predicate;
use League\Csv\Query\QueryException;
use League\Csv\Serializer\Denormalizer;
use League\Csv\Serializer\MappingFailed;
use League\Csv\Serializer\TypeCastingFailed;
use LimitIterator;
use mysqli_result;
use OutOfBoundsException;
use PDOStatement;
use PgSql\Result;
use ReflectionException;
use RuntimeException;
use SQLite3Result;
use Throwable;
use TypeError;

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
use function array_unique;
use function array_values;
use function count;
use function in_array;
use function is_array;
use function is_int;
use function is_string;
use function iterator_to_array;
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
    /** @var array<int, array<array-key, mixed>> */
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
    public static function from(PDOStatement|Result|mysqli_result|SQLite3Result|TabularData $dataStorage, int $options = self::INCLUDE_HEADER): self
    {
        if ($dataStorage instanceof TabularData) {
            if (self::INCLUDE_HEADER === $options) {
                $instance = new self(header: $dataStorage->getHeader());
                $instance->rows = iterator_to_array($dataStorage->getRecords());

                return $instance;
            }

            $instance = new self();
            $instance->rows = iterator_to_array(new MapIterator($dataStorage->getRecords(), fn (array $row) => array_values($row))); /* @phpstan-ignore-line */

            return $instance;
        }

        if (self::INCLUDE_HEADER === $options) {
            $instance = new self(header: RdbmsResult::columnNames($dataStorage));
            $instance->rows = iterator_to_array(RdbmsResult::rows($dataStorage));

            return $instance;
        }

        $instance = new self();
        $instance->rows = iterator_to_array(new MapIterator(RdbmsResult::rows($dataStorage), fn (array $row) => array_values($row))); /* @phpstan-ignore-line */

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

        return $bytes + $dataStorage->insertAll($this->getRecords()); /* @phpstan-ignore-line */
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

    public function getRecords(array $header = []): Iterator
    {
        $header = $this->prepareHeader($header);

        return MapIterator::fromIterable($this->rows, fn (array $row): array => $this->getRecord($row, $header));
    }

    private function prepareHeader(array $header): array
    {
        return match (true) {
            [] === $header => $this->header,
            $header !== array_filter($header, is_int(...), ARRAY_FILTER_USE_KEY) => throw new SyntaxError('The header must be a list of unique column names.'),
            $header !== array_filter($header, is_string(...)) => throw SyntaxError::dueToInvalidHeaderColumnNames(),
            $header !== array_unique($header) => throw SyntaxError::dueToDuplicateHeaderColumnNames($header),
            default => $header,
        };
    }

    private function getRecord(array $row, array $header): array
    {
        if (!array_is_list($row)) {
            $row = array_values($row);
        }

        if ([] === $header) {
            return $row;
        }

        $record = [];
        foreach ($header as $offset => $headerName) {
            $record[$headerName] = $row[$offset] ?? null;
        }

        return $record;
    }

    /**
     * @param class-string $className
     *
     * @throws ReflectionException
     * @throws SyntaxError
     */
    private function recordToObject(array $record, string $className, array $header): ?object
    {
        return [] === $record ? null : Denormalizer::assign($className, $this->getRecord($record, $this->prepareHeader($header)));
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

    public function first(): array
    {
        $offset = array_key_first($this->rows);
        if (null === $offset) {
            return [];
        }

        return $this->getRecord($this->rows[$offset], $this->header);
    }

    public function last(): array
    {
        $offset = array_key_last($this->rows);
        if (null === $offset) {
            return [];
        }

        return $this->getRecord($this->rows[$offset], $this->header);
    }

    public function nth(int $nth): array
    {
        0 <= $nth || throw InvalidArgument::dueToInvalidRecordOffset($nth, __METHOD__);

        $iterator = new LimitIterator($this->getRecords(), $nth, 1);
        $iterator->rewind();

        /** @var array|null $result */
        $result = $iterator->current();

        return $result ?? [];
    }

    /**
     * @param class-string $className
     * @param array<string> $header
     *
     * @throws SyntaxError
     * @throws ReflectionException
     */
    public function nthAsObject(int $nth, string $className, array $header = []): ?object
    {
        return $this->recordToObject($this->nth($nth), $className, $header);
    }

    /**
     * @param class-string $className
     * @param array<string> $header
     *
     * @throws ReflectionException
     * @throws SyntaxError
     */
    public function firstAsObject(string $className, array $header = []): ?object
    {
        return $this->recordToObject($this->first(), $className, $header);
    }

    /**
     * @param class-string $className
     * @param array<string> $header
     *
     * @throws ReflectionException
     * @throws SyntaxError
     */
    public function lastAsObject(string $className, array $header = []): ?object
    {
        return $this->recordToObject($this->last(), $className, $header);
    }

    public function fetchColumn(int|string $index = 0): Iterator
    {
        if (is_int($index)) {
            $index > -1 || throw new OutOfBoundsException('The specified column `'.$index.'` does not exist.');
            [] === $this->header || array_key_exists($index, $this->header) || throw new OutOfBoundsException('The specified column `'.$index.'` does not exist.');

            $iterator = new MapIterator($this->getRecords(), fn (array $row) => array_values($row));
            $iterator = new CallbackFilterIterator($iterator, fn (array $row) => array_key_exists($index, $row));

            return new MapIterator($iterator, fn (array $row) => $row[$index]);
        }

        [] !== $this->header || throw new OutOfBoundsException('The specified column `'.$index.'` does not exist.');
        in_array($index, $this->header, true) || throw new OutOfBoundsException('The specified column `'.$index.'` does not exist.');

        $iterator = new CallbackFilterIterator($this->getRecords(), fn (array $row) => array_key_exists($index, $row));

        return new MapIterator($iterator, fn (array $row) => $row[$index]);
    }

    public function fetchPairs(string|int $offset_index = 0, string|int $value_index = 1): Iterator
    {
        $offset = $this->fetchIndex($offset_index);
        $value = $this->fetchIndex($value_index);

        foreach (new CallbackFilterIterator($this->getRecords(), fn (array $record) => isset($record[$offset])) as $record) {
            yield $record[$offset] => $record[$value] ?? null;
        }
    }

    /**
     * Adds a record validator.
     *
     * @param callable(array): bool $validator
     */
    public function addValidator(callable $validator, string $validator_name): self
    {
        $this->validators[$validator_name] = !$validator instanceof Closure ? $validator(...) : $validator;

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
        $nbRecords = count($records);
        if (0 !== $nbRecords) {
            array_push(
                $this->rows,
                ...array_map(
                    fn (array $row) => $this->formatInsertRecord($row),
                    $records
                )
            );
        }

        return $nbRecords;
    }

    /**
     * @throws QueryException
     * @throws CannotInsertRecord
     * @throws SyntaxError
     */
    public function update(Predicate|Closure|callable|array|int $where, array $record): int
    {
        $record = $this->filterUpdateRecord($record);
        $updateRecord = function (array $row) use ($record): array {
            foreach ($record as $index => $value) {
                $row[$index] = $value;
            }

            return $this->validateRecord($row);
        };

        /** @var Iterator<int, array> $iterator */
        $iterator = new MapIterator(
            new CallbackFilterIterator($this->getRecords(), $this->filterPredicate($where)),
            $updateRecord
        );

        $affectedRecords = 0;
        foreach ($iterator as $offset => $row) {
            $this->rows[$offset] = $row;
            $affectedRecords++;
        }

        return $affectedRecords;
    }

    /**
     * @throws QueryException
     * @throws SyntaxError
     */
    public function delete(Predicate|Closure|callable|array|int $where): int
    {
        $affectedRecords = 0;
        foreach (new CallbackFilterIterator($this->getRecords(), $this->filterPredicate($where)) as $offset => $row) {
            unset($this->rows[$offset]);
            $affectedRecords++;
        }

        return $affectedRecords;
    }

    private function fetchIndex(string|int $index): string|int
    {
        if (is_string($index)) {
            [] !== $this->header || throw new OutOfBoundsException('The specified column `'.$index.'` does not exist.');
            in_array($index, $this->header, true) || throw new OutOfBoundsException('The specified column `'.$index.'` does not exist.');

            return $index;
        }

        $index > -1 || throw new OutOfBoundsException('The specified column `'.$index.'` does not exist.');
        [] === $this->header || array_key_exists($index, $this->header) || throw new OutOfBoundsException('The specified column `'.$index.'` does not exist.');
        if ([] === $this->header) {
            return $index;
        }

        return $this->header[$index];
    }

    /**
     * @throws CannotInsertRecord
     */
    private function formatInsertRecord(array $record): array
    {
        if (!$this->filterInsertRecord($record)) {
            throw CannotInsertRecord::triggerOnValidation('@buffer_record_validation_on_insert', $record);
        }

        return $this->validateRecord(match (true) {
            [] === $this->header => array_values($record),
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
     * Validates a record.
     *
     * @throws CannotInsertRecord If the validation failed
     */
    private function validateRecord(array $record): array
    {
        foreach ($this->formatters as $formatter) {
            $record = $formatter($record);
        }

        foreach ($this->validators as $name => $validator) {
            true === $validator($record) || throw CannotInsertRecord::triggerOnValidation($name, $record);
        }

        return $record;
    }

    private function filterUpdateRecord(array $record): array
    {
        if ([] === $record) {
            throw CannotInsertRecord::triggerOnValidation('@buffer_record_validation_on_update', $record);
        }

        if (array_is_list($record)) {
            if ([] !== $this->header) {
                $formattedRecord = [];
                foreach ($this->header as $offset => $headerName) {
                    $formattedRecord[$headerName] = $record[$offset] ?? null;
                }

                return $formattedRecord;
            }

            return $record;
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
     * @throws QueryException
     */
    private function filterPredicate(Predicate|Closure|callable|array|int $predicate): Predicate
    {
        if (is_int($predicate)) {
            array_key_exists($predicate, $this->rows) || throw new OutOfBoundsException('The specified offset does not exist.');

            return Offset::filterOn('=', $predicate);
        }

        if (!is_array($predicate)) {
            return Criteria::all($predicate);
        }

        if ($predicate === array_filter($predicate, is_int(...))) {
            $foundPredicate = array_filter(
                array_map(fn (int $index): ?int => array_key_exists($index, $this->rows) ? $index : null, $predicate),
                fn (?int $index): bool => null !== $index
            );

            ($foundPredicate === $predicate) || throw new OutOfBoundsException('At least one of the specified offset does not exist.');

            return Offset::filterOn(Comparison::In, $predicate);
        }

        try {
            return Criteria::all($predicate); /* @phpstan-ignore-line */
        } catch (Throwable $exception) {
            throw new TypeError('The specified predicate is invalid.', previous: $exception);
        }
    }
}
