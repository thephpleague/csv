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
use mysqli_result;
use OutOfBoundsException;
use PDOStatement;
use PgSql\Result;
use ReflectionException;
use RuntimeException;
use SQLite3Result;
use Throwable;
use TypeError;
use ValueError;

use function array_combine;
use function array_diff;
use function array_filter;
use function array_is_list;
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_unique;
use function array_values;
use function count;
use function in_array;
use function is_array;
use function is_int;
use function is_string;
use function iterator_to_array;

final class Buffer implements TabularData
{
    /** @var list<string>|array{} */
    private readonly array $header;
    /** @var list<string>|array{} */
    private readonly array $sortedHeader;
    /** @var array<int, array<array-key, mixed>> */
    private array $rows = [];

    /**
     * @param iterable<array-key, array<array-key, mixed>> $rows
     * @param list<string>|array{} $header
     *
     * @throws SyntaxError
     */
    public function __construct(iterable $rows = [], array $header = [])
    {
        $this->header = match (true) {
            !array_is_list($header) => throw new SyntaxError('The header must be a list of unique column names.'),
            $header !== array_filter($header, is_string(...)) => throw SyntaxError::dueToInvalidHeaderColumnNames(),
            $header !== array_unique($header) => throw SyntaxError::dueToDuplicateHeaderColumnNames($header),
            default => $header,
        };
        sort($header);
        $this->sortedHeader = $header;

        $this->insertAll($rows);
    }

    /**
     * Returns a new instance from a tabular data implementing object.
     *
     * @throws RuntimeException|SyntaxError If the column names can not be found
     */
    public static function from(PDOStatement|Result|mysqli_result|SQLite3Result|TabularData $dataStorage, bool $includeHeader = true): self
    {
        if ($dataStorage instanceof TabularData) {
            if ($includeHeader) {
                $instance = new self(header: $dataStorage->getHeader());
                $instance->rows = iterator_to_array($dataStorage->getRecords(), false);

                return $instance;
            }

            $instance = new self();
            $instance->rows = iterator_to_array(new MapIterator($dataStorage->getRecords(), fn (array $row) => array_values($row)), false); /* @phpstan-ignore-line */

            return $instance;
        }

        if ($includeHeader) {
            $instance = new self(header: RdbmsResult::columnNames($dataStorage));
            $instance->rows = RdbmsResult::rows($dataStorage);

            return $instance;
        }

        $instance = new self();
        $instance->rows = array_map(array_values(...), RdbmsResult::rows($dataStorage));

        return $instance;
    }

    /**
     * @throws CannotInsertRecord
     * @throws Exception
     */
    public function to(TabularDataWriter $dataStorage, bool $includeHeader = true): int
    {
        $bytes = 0;
        $header = $this->getHeader();
        if ($includeHeader && [] !== $header) {
            $bytes += $dataStorage->insertOne($header);
        }

        return $bytes + $dataStorage->insertAll($this->getRecords()); /* @phpstan-ignore-line */
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
        $header = match (true) {
            !array_is_list($header) => throw new SyntaxError('The header must be a list of unique column names.'),
            $header !== array_filter($header, is_string(...)) => throw SyntaxError::dueToInvalidHeaderColumnNames(),
            $header !== array_unique($header) => throw SyntaxError::dueToDuplicateHeaderColumnNames($header),
            [] === $header => $this->header,
            default => $header,
        };

        return MapIterator::fromIterable($this->rows, match ([]) {
            $this->header => fn (array $row): array => array_values($row),
            default => function (array $row) use ($header): array {
                $record = [];
                $values = array_values($row);
                foreach ($header as $offset => $headerName) {
                    $record[$headerName] = $values[$offset] ?? null;
                }

                return $record;
            },
        });
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

    public function nth(int $nth): array
    {
        try {
            array_key_exists($nth, $this->rows) || throw new OutOfBoundsException('The specified offset does not exist.');
            $values = array_values($this->rows[$nth]);
            if ([] === $this->header) {
                return $values;
            }

            $record = [];
            foreach ($this->header as $index => $headerName) {
                $record[$headerName] = $values[$index] ?? null;
            }

            return $record;
        } catch (Throwable) {
            return [];
        }
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
        $record = $this->nth($nth);
        if ([] === $record) {
            return null;
        }

        if ([] === $header) {
            return Denormalizer::assign($className, $record);
        }

        $header = match (true) {
            !array_is_list($header) => throw new SyntaxError('The header must be a list of unique column names.'),
            $header !== array_filter($header, is_string(...)) => throw SyntaxError::dueToInvalidHeaderColumnNames(),
            $header !== array_unique($header) => throw SyntaxError::dueToDuplicateHeaderColumnNames($header),
            default => $header,
        };

        $values = array_values($record);
        $record = [];
        foreach ($header as $index => $headerName) {
            $record[$headerName] = $values[$index] ?? null;
        }

        return Denormalizer::assign($className, $record);
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

    public function insertOne(array $record): int
    {
        $this->rows[] = $this->formatInsertRecord($record);

        return 1;
    }

    /**
     * @param iterable<array> $records
     */
    public function insertAll(iterable $records): int
    {
        $affectedRows = 0;
        foreach ($records as $record) {
            $affectedRows += $this->insertOne($record);
        }

        return $affectedRows;
    }

    /**
     * @throws QueryException
     */
    public function update(Predicate|Closure|callable|array|int $where, array $record): int
    {
        $where = $this->filterPredicate($where);
        $this->filterUpdateRecord($record) || throw new ValueError('The specified record contain invalid column names.');

        if (array_is_list($record) && [] !== $this->header) {
            $formattedRecord = [];
            foreach ($this->header as $offset => $headerName) {
                $formattedRecord[$headerName] = $record[$offset] ?? null;
            }

            $record = $formattedRecord;
        }

        $affectedRecords = 0;
        foreach ($this->getRecords() as $offset => $currentRecord) {
            if ($where($currentRecord, $offset)) {
                foreach ($record as $index => $value) {
                    $currentRecord[$index] = $record[$index];
                }

                $this->rows[$offset] = $currentRecord;
                $affectedRecords++;
            }
        }

        return $affectedRecords;
    }

    /**
     * @throws QueryException|SyntaxError
     */
    public function delete(Predicate|Closure|callable|array|int $where): int
    {
        $affectedRecords = 0;
        $where = $this->filterPredicate($where);
        foreach ($this->getRecords() as $offset => $record) {
            if ($where($record, $offset)) {
                unset($this->rows[$offset]);
                $affectedRecords++;
            }
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

    private function formatInsertRecord(array $record): array
    {
        if (!$this->filterInsertRecord($record)) {
            throw new ValueError('The specified record contain invalid column names.');
        }

        if ([] === $this->header) {
            return array_values($record);
        }

        if (array_is_list($record)) {
            return array_combine($this->header, $record);
        }

        // re-order the associative array to have all the data
        // correctly aligned
        $newRow = [];
        foreach ($this->header as $name) {
            $newRow[$name] = $record[$name];
        }

        return $newRow;
    }

    private function filterInsertRecord(array $record): bool
    {
        $recordIsList = array_is_list($record);
        if ([] === $this->header) {
            return $recordIsList;
        }

        if ($recordIsList) {
            return count($record) === count($this->header);
        }

        $keys = array_keys($record);
        sort($keys);

        return $keys === $this->sortedHeader;
    }

    private function filterUpdateRecord(array $record): bool
    {
        if (array_is_list($record)) {
            return true;
        }

        $keys = array_keys($record);

        return match (true) {
            $keys === array_filter($keys, is_int(...)) => true,
            $keys !== array_filter($keys, is_string(...)),
            [] !== array_diff($keys, $this->header) => false,
            default => true,
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
