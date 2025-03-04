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
use Deprecated;
use Iterator;
use JsonSerializable;
use League\Csv\Serializer\Denormalizer;
use League\Csv\Serializer\MappingFailed;
use League\Csv\Serializer\TypeCastingFailed;
use SplFileObject;

use function array_filter;
use function array_unique;
use function is_array;
use function iterator_count;
use function strlen;
use function substr;

use const STREAM_FILTER_READ;

/**
 * A class to parse and read records from a CSV document.
 *
 * @template TValue of array
 */
class Reader extends AbstractCsv implements TabularDataReader, JsonSerializable
{
    protected const STREAM_FILTER_MODE = STREAM_FILTER_READ;

    protected ?int $header_offset = null;
    protected int $nb_records = -1;
    protected bool $is_empty_records_included = false;
    /** @var array<string> header record. */
    protected array $header = [];

    public static function createFromPath(string $path, string $open_mode = 'r', $context = null): static
    {
        return parent::createFromPath($path, $open_mode, $context);
    }

    /**
     * Selects the record to be used as the CSV header.
     *
     * Because the header is represented as an array, to be valid
     * a header MUST contain only unique string value.
     *
     * @param int|null $offset the header record offset
     *
     * @throws Exception if the offset is a negative integer
     */
    public function setHeaderOffset(?int $offset): static
    {
        if ($offset === $this->header_offset) {
            return $this;
        }

        null === $offset || -1 < $offset || throw InvalidArgument::dueToInvalidHeaderOffset($offset, __METHOD__);

        $this->header_offset = $offset;
        $this->resetProperties();

        return $this;
    }

    /**
     * Enables skipping empty records.
     */
    public function skipEmptyRecords(): static
    {
        if ($this->is_empty_records_included) {
            $this->is_empty_records_included = false;
            $this->nb_records = -1;
        }

        return $this;
    }

    /**
     * Disables skipping empty records.
     */
    public function includeEmptyRecords(): static
    {
        if (!$this->is_empty_records_included) {
            $this->is_empty_records_included = true;
            $this->nb_records = -1;
        }

        return $this;
    }

    /**
     * Tells whether empty records are skipped by the instance.
     */
    public function isEmptyRecordsIncluded(): bool
    {
        return $this->is_empty_records_included;
    }

    protected function resetProperties(): void
    {
        parent::resetProperties();

        $this->nb_records = -1;
        $this->header = [];
    }

    /**
     * Returns the header offset.
     */
    public function getHeaderOffset(): ?int
    {
        return $this->header_offset;
    }

    /**
     * @throws SyntaxError
     *
     * Returns the header record.
     */
    public function getHeader(): array
    {
        return match (true) {
            null === $this->header_offset,
            [] !== $this->header => $this->header,
            default => ($this->header = $this->setHeader($this->header_offset)),
        };
    }

    /**
     * Determines the CSV record header.
     *
     * @throws SyntaxError If the header offset is set and no record is found or is the empty array
     *
     * @return array<string>
     */
    protected function setHeader(int $offset): array
    {
        $inputBom = null;
        $header = $this->seekRow($offset);
        if (0 === $offset) {
            $inputBom = Bom::tryFrom($this->getInputBOM());
            $header = $this->removeBOM(
                $header,
                !$this->is_input_bom_included ? $inputBom?->length() ?? 0 : 0,
                $this->enclosure
            );
        }

        return match (true) {
            [] === $header,
            [null] === $header,
            [false] === $header,
            [''] === $header && 0 === $offset && null !== $inputBom => throw SyntaxError::dueToHeaderNotFound($offset),
            default => $header,
        };
    }

    /**
     * @throws Exception
     *
     * Returns the row at a given offset.
     */
    protected function seekRow(int $offset): array
    {
        $this->getDocument()->seek($offset);
        $record = $this->document->current();

        return match (true) {
            false === $record => [],
            default => (array) $record,
        };
    }

    /**
     * @throws Exception
     *
     * Returns the document as an Iterator.
     */
    protected function getDocument(): SplFileObject|Stream
    {
        $this->document->setFlags(SplFileObject::READ_CSV | SplFileObject::READ_AHEAD);
        $this->document->setCsvControl($this->delimiter, $this->enclosure, $this->escape);
        $this->document->rewind();

        return $this->document;
    }

    /**
     * Strips the BOM sequence from a record.
     *
     * @param array<string> $record
     *
     * @return array<string>
     */
    protected function removeBOM(array $record, int $bom_length, string $enclosure): array
    {
        if ([] === $record || !is_string($record[0]) || 0 === $bom_length || strlen($record[0]) < $bom_length) {
            return $record;
        }

        $record[0] = substr($record[0], $bom_length);
        if ($enclosure.$enclosure !== substr($record[0].$record[0], strlen($record[0]) - 1, 2)) {
            return $record;
        }

        $record[0] = substr($record[0], 1, -1);

        return $record;
    }

    public function fetchColumn(string|int $index = 0): Iterator
    {
        return ResultSet::from($this)->fetchColumn($index);
    }

    public function value(int|string $column = 0): mixed
    {
        return ResultSet::from($this)->value($column);
    }

    /**
     * @throws Exception
     */
    public function first(): array
    {
        return ResultSet::from($this)->first();
    }

    /**
     * @throws Exception
     */
    public function nth(int $nth): array
    {
        return ResultSet::from($this)->nth($nth);
    }

    /**
     * @param class-string $className
     *
     * @throws Exception
     */
    public function nthAsObject(int $nth, string $className, array $header = []): ?object
    {
        return ResultSet::from($this)->nthAsObject($nth, $className, $header);
    }

    /**
     * @param class-string $className
     *
     * @throws Exception
     */
    public function firstAsObject(string $className, array $header = []): ?object
    {
        return ResultSet::from($this)->firstAsObject($className, $header);
    }

    public function fetchPairs(string|int $offset_index = 0, string|int $value_index = 1): Iterator
    {
        return ResultSet::from($this)->fetchPairs($offset_index, $value_index);
    }

    /**
     * @throws Exception
     */
    public function count(): int
    {
        if (-1 === $this->nb_records) {
            $this->nb_records = iterator_count($this->getRecords());
        }

        return $this->nb_records;
    }

    /**
     * @throws Exception
     */
    public function getIterator(): Iterator
    {
        return $this->getRecords();
    }

    /**
     * @throws Exception
     */
    public function jsonSerialize(): array
    {
        return array_values([...$this->getRecords()]);
    }

    /**
     * @param callable(array<mixed>, array-key=): (void|bool|null) $callback
     */
    public function each(callable $callback): bool
    {
        return ResultSet::from($this)->each($callback);
    }

    /**
     * @param callable(array<mixed>, array-key=): bool $callback
     */
    public function exists(callable $callback): bool
    {
        return ResultSet::from($this)->exists($callback);
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
        return ResultSet::from($this)->reduce($callback, $initial);
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
        return ResultSet::from($this)->chunkBy($recordsCount);
    }

    /**
     * @param array<string> $headers
     */
    public function mapHeader(array $headers): TabularDataReader
    {
        return (new Statement())->process($this, $headers);
    }

    /**
     * @param \League\Csv\Query\Predicate|Closure(array, array-key): bool $predicate
     *
     * @throws Exception
     * @throws SyntaxError
     */
    public function filter(Query\Predicate|Closure $predicate): TabularDataReader
    {
        return (new Statement())->where($predicate)->process($this);
    }

    /**
     * @param int<0, max> $offset
     * @param int<-1, max> $length
     *
     * @throws Exception
     * @throws SyntaxError
     */
    public function slice(int $offset, int $length = -1): TabularDataReader
    {
        return (new Statement())->offset($offset)->limit($length)->process($this);
    }

    /**
     * @param Closure(mixed, mixed): int $orderBy
     *
     * @throws Exception
     * @throws SyntaxError
     */
    public function sorted(Query\Sort|Closure $orderBy): TabularDataReader
    {
        return (new Statement())->orderBy($orderBy)->process($this);
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

    public function select(string|int ...$columns): TabularDataReader
    {
        return ResultSet::from($this)->select(...$columns);
    }

    public function selectAllExcept(string|int ...$columns): TabularDataReader
    {
        return ResultSet::from($this)->selectAllExcept(...$columns);
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
        return $this->combineHeader(
            $this->prepareRecords(),
            $this->prepareHeader($header)
        );
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
        /** @var array<string> $header */
        $header = $this->prepareHeader($header);

        return Denormalizer::assignAll(
            $className,
            $this->combineHeader($this->prepareRecords(), $header),
            $header
        );
    }

    /**
     * @throws Exception
     */
    protected function prepareRecords(): Iterator
    {
        $normalized = fn ($record): bool => is_array($record) && ($this->is_empty_records_included || $record !== [null]);
        $bom = null;
        if (!$this->is_input_bom_included) {
            $bom = Bom::tryFrom($this->getInputBOM());
        }

        $records = $this->stripBOM(new CallbackFilterIterator($this->getDocument(), $normalized), $bom);
        if (null !== $this->header_offset) {
            $records = new CallbackFilterIterator($records, fn (array $record, int $offset): bool => $offset !== $this->header_offset);
        }

        if ($this->is_empty_records_included) {
            $records = new MapIterator($records, fn (array $record): array => ([null] === $record) ? [] : $record);
        }

        return $records;
    }

    /**
     * Strips the BOM sequence from the returned records if necessary.
     */
    protected function stripBOM(Iterator $iterator, ?Bom $bom): Iterator
    {
        if (null === $bom) {
            return $iterator;
        }

        $bomLength = $bom->length();
        $mapper = function (array $record, int $index) use ($bomLength): array {
            if (0 !== $index) {
                return $record;
            }

            $record = $this->removeBOM($record, $bomLength, $this->enclosure);

            return match ($record) {
                [''] => [null],
                default => $record,
            };
        };

        return new CallbackFilterIterator(
            new MapIterator($iterator, $mapper),
            fn (array $record): bool => $this->is_empty_records_included || $record !== [null]
        );
    }

    /**
     * @param array<string> $header
     *
     * @throws SyntaxError
     *
     * @return array<int|string>
     */
    protected function prepareHeader($header = []): array
    {
        $header == array_filter($header, is_string(...)) || throw SyntaxError::dueToInvalidHeaderColumnNames();

        return $this->computeHeader($header);
    }

    /**
     * Returns the header to be used for iteration.
     *
     * @param array<int|string> $header
     *
     * @throws SyntaxError If the header contains non unique column name
     *
     * @return array<int|string>
     */
    protected function computeHeader(array $header): array
    {
        if ([] === $header) {
            $header = $this->getHeader();
        }

        return match (true) {
            $header !== array_unique($header) => throw SyntaxError::dueToDuplicateHeaderColumnNames($header),
            [] !== array_filter(array_keys($header), fn (string|int $value) => !is_int($value) || $value < 0) => throw new SyntaxError('The header mapper indexes should only contain positive integer or 0.'),
            default => $header,
        };
    }

    protected function combineHeader(Iterator $iterator, array $header): Iterator
    {
        $formatter = fn (array $record): array => array_reduce(
            $this->formatters,
            fn (array $record, Closure $formatter): array => $formatter($record),
            $record
        );

        return match ([]) {
            $header => new MapIterator($iterator, $formatter(...)),
            default => new MapIterator($iterator, function (array $record) use ($header, $formatter): array {
                $assocRecord = [];
                foreach ($header as $offset => $headerName) {
                    $assocRecord[$headerName] = $record[$offset] ?? null;
                }

                return $formatter($assocRecord);
            }),
        };
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
        return ResultSet::from($this)->fetchColumnByName($name);
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
    public function fetchColumnByOffset(int $offset = 0): Iterator
    {
        return ResultSet::from($this)->fetchColumnByOffset($offset);
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @see Reader::nth()
     * @deprecated since version 9.9.0
     * @codeCoverageIgnore
     */
    #[Deprecated(message:'use League\Csv\Reader::nth() instead', since:'league/csv:9.9.0')]
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
    #[Deprecated(message:'use League\Csv\Reader::getRecordsAsObject() instead', since:'league/csv:9.15.0')]
    public function getObjects(string $className, array $header = []): Iterator
    {
        return $this->getRecordsAsObject($className, $header);
    }
}
