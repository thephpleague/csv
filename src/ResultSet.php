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
use Iterator;
use JsonSerializable;
use LimitIterator;
use function array_flip;
use function array_search;
use function is_string;
use function iterator_count;
use function iterator_to_array;

/**
 * Represents the result set of a {@link Reader} processed by a {@link Statement}.
 */
class ResultSet implements TabularDataReader, JsonSerializable
{
    /**
     * The CSV records collection.
     *
     * @var Iterator
     */
    protected $records;

    /**
     * The CSV records collection header.
     *
     * @var array
     */
    protected $header = [];

    /**
     * New instance.
     */
    public function __construct(Iterator $records, array $header)
    {
        $this->validateHeader($header);

        $this->records = $records;
        $this->header = $header;
    }

    /**
     * @throws SyntaxError if the header syntax is invalid
     */
    protected function validateHeader(array $header): void
    {
        if ($header !== ($filtered_header = array_filter($header, 'is_string'))) {
            throw SyntaxError::dueToInvalidHeaderColumnNames();
        }

        if ($header !== array_unique($filtered_header)) {
            throw SyntaxError::dueToDuplicateHeaderColumnNames($header);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function __destruct()
    {
        unset($this->records);
    }

    /**
     * Returns a new instance from a League\Csv\Reader object.
     */
    public static function createFromTabularDataReader(TabularDataReader $reader): self
    {
        return new self($reader->getRecords(), $reader->getHeader());
    }

    /**
     * Returns the header associated with the result set.
     *
     * @return string[]
     */
    public function getHeader(): array
    {
        return $this->header;
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator(): Iterator
    {
        return $this->getRecords();
    }

    /**
     * {@inheritdoc}
     */
    public function getRecords(array $header = []): Iterator
    {
        $this->validateHeader($header);
        $records = $this->combineHeader($header);
        foreach ($records as $offset => $value) {
            yield $offset => $value;
        }
    }

    /**
     * Combine the header to each record if present.
     */
    protected function combineHeader(array $header): Iterator
    {
        if ($header === $this->header || [] === $header) {
            return $this->records;
        }

        $field_count = count($header);
        $mapper = static function (array $record) use ($header, $field_count): array {
            if (count($record) != $field_count) {
                $record = array_slice(array_pad($record, $field_count, null), 0, $field_count);
            }

            /** @var array<string|null> $assocRecord */
            $assocRecord = array_combine($header, $record);

            return $assocRecord;
        };

        return new MapIterator($this->records, $mapper);
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        return iterator_count($this->records);
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize(): array
    {
        return iterator_to_array($this->records, false);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchOne(int $nth_record = 0): array
    {
        if ($nth_record < 0) {
            throw InvalidArgument::dueToInvalidRecordOffset($nth_record, __METHOD__);
        }

        $iterator = new LimitIterator($this->records, $nth_record, 1);
        $iterator->rewind();

        return (array) $iterator->current();
    }

    /**
     * {@inheritdoc}
     */
    public function fetchColumn($index = 0): Iterator
    {
        $offset = $this->getColumnIndex($index, 'offset', __METHOD__);
        $filter = static function (array $record) use ($offset): bool {
            return isset($record[$offset]);
        };

        $select = static function (array $record) use ($offset): string {
            return $record[$offset];
        };

        $iterator = new MapIterator(new CallbackFilterIterator($this->records, $filter), $select);
        foreach ($iterator as $tKey => $tValue) {
            yield $tKey => $tValue;
        }
    }

    /**
     * Filter a column name against the header if any.
     *
     * @param string|int $field  the field name or the field index
     * @param string     $method the calling method
     *
     * @throws Exception if the field is invalid or not found
     *
     * @return string|int
     */
    protected function getColumnIndex($field, string $type, string $method)
    {
        if (is_string($field)) {
            return $this->getColumnIndexByValue($field, $type, $method);
        }

        return $this->getColumnIndexByKey($field, $type, $method);
    }

    /**
     * Returns the selected column name.
     *
     * @throws Exception if the column is not found
     */
    protected function getColumnIndexByValue(string $value, string $type, string $method): string
    {
        if (false !== array_search($value, $this->header, true)) {
            return $value;
        }

        throw InvalidArgument::dueToInvalidColumnIndex($value, $type, $method);
    }

    /**
     * Returns the selected column name according to its offset.
     *
     * @throws Exception if the field is invalid or not found
     *
     * @return int|string
     */
    protected function getColumnIndexByKey(int $index, string $type, string $method)
    {
        if ($index < 0) {
            throw InvalidArgument::dueToInvalidColumnIndex($index, $type, $method);
        }

        if ([] === $this->header) {
            return $index;
        }

        $value = array_search($index, array_flip($this->header), true);
        if (false === $value) {
            throw InvalidArgument::dueToInvalidColumnIndex($index, $type, $method);
        }

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchPairs($offset_index = 0, $value_index = 1): Iterator
    {
        $offset = $this->getColumnIndex($offset_index, 'offset', __METHOD__);
        $value = $this->getColumnIndex($value_index, 'value', __METHOD__);

        $filter = static function (array $record) use ($offset): bool {
            return isset($record[$offset]);
        };

        $select = static function (array $record) use ($offset, $value): array {
            return [$record[$offset], $record[$value] ?? null];
        };

        $iterator = new MapIterator(new CallbackFilterIterator($this->records, $filter), $select);
        foreach ($iterator as $pair) {
            yield $pair[0] => $pair[1];
        }
    }
}
