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
use JsonSerializable;
use League\Csv\Serializer\Denormalizer;
use League\Csv\Serializer\MappingFailed;
use League\Csv\Serializer\TypeCastingFailed;
use SplFileObject;

use function array_filter;
use function array_unique;
use function is_array;
use function iterator_count;
use function mb_strlen;
use function mb_substr;
use function strlen;
use function substr;

use const STREAM_FILTER_READ;

/**
 * A class to parse and read records from a CSV document.
 */
class Reader extends AbstractCsv implements TabularDataReader, JsonSerializable
{
    protected const STREAM_FILTER_MODE = STREAM_FILTER_READ;

    protected ?int $header_offset = null;
    protected int $nb_records = -1;
    protected bool $is_empty_records_included = false;
    /** @var array<string> header record. */
    protected array $header = [];
    /** @var array<callable> callable collection to format the record before reading. */
    protected array $formatters = [];

    public static function createFromPath(string $path, string $open_mode = 'r', $context = null): static
    {
        return parent::createFromPath($path, $open_mode, $context);
    }

    /**
     * Adds a record formatter.
     */
    public function addFormatter(callable $formatter): self
    {
        $this->formatters[] = $formatter;

        return $this;
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
        $header = $this->seekRow($offset);
        if (in_array($header, [[], [null], [false]], true)) {
            throw SyntaxError::dueToHeaderNotFound($offset);
        }

        if (0 !== $offset) {
            return $header;
        }

        $header = $this->removeBOM(
            $header,
            !$this->is_input_bom_included ? mb_strlen($this->getInputBOM()) : 0,
            $this->enclosure
        );

        if ([''] === $header) {
            throw SyntaxError::dueToHeaderNotFound($offset);
        }

        return $header;
    }

    /**
     * @throws Exception
     */
    private function prepareRecords(): Iterator
    {
        $normalized = fn ($record): bool => is_array($record) && ($this->is_empty_records_included || $record !== [null]);
        $bom = '';
        if (!$this->is_input_bom_included) {
            $bom = $this->getInputBOM();
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
        if (0 === $bom_length) {
            return $record;
        }

        $record[0] = mb_substr($record[0], $bom_length);
        if ($enclosure.$enclosure !== substr($record[0].$record[0], strlen($record[0]) - 1, 2)) {
            return $record;
        }

        $record[0] = substr($record[0], 1, -1);

        return $record;
    }

    /**
     * @throws Exception
     */
    public function fetchColumnByName(string $name): Iterator
    {
        return ResultSet::createFromTabularDataReader($this)->fetchColumnByName($name);
    }

    /**
     * @throws Exception
     */
    public function fetchColumnByOffset(int $offset = 0): Iterator
    {
        return ResultSet::createFromTabularDataReader($this)->fetchColumnByOffset($offset);
    }

    public function value(int|string $column = 0): mixed
    {
        return match (true) {
            is_string($column) => $this->first()[$column] ?? null,
            default => array_values($this->first())[$column] ?? null,
        };
    }

    /**
     * @throws Exception
     */
    public function first(): array
    {
        return ResultSet::createFromTabularDataReader($this)->first();
    }

    /**
     * @throws Exception
     */
    public function nth(int $nth_record): array
    {
        return ResultSet::createFromTabularDataReader($this)->nth($nth_record);
    }

    /**
     * @param class-string $className
     *
     * @throws Exception
     */
    public function nthAsObject(int $nth, string $className, array $header = []): ?object
    {
        return ResultSet::createFromTabularDataReader($this)->nthAsObject($nth, $className, $header);
    }

    /**
     * @param class-string $className
     *
     * @throws Exception
     */
    public function firstAsObject(string $className, array $header = []): ?object
    {
        return ResultSet::createFromTabularDataReader($this)->firstAsObject($className, $header);
    }

    public function fetchPairs($offset_index = 0, $value_index = 1): Iterator
    {
        return ResultSet::createFromTabularDataReader($this)->fetchPairs($offset_index, $value_index);
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
     *
     * @return Iterator<array-key, array<mixed>>
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
     * @param Closure(array<mixed>, array-key=): (void|bool|null) $closure
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
     * @param Closure(array<mixed>, array-key=): bool $closure
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
     * @param Closure(TInitial|null, array<mixed>, array-key=): TInitial $closure
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

    /**
     * @param Closure(array<mixed>, array-key): bool $closure
     *
     * @throws Exception
     * @throws SyntaxError
     */
    public function filter(Closure $closure): TabularDataReader
    {
        return Statement::create()->where($closure)->process($this);
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
        return Statement::create()->offset($offset)->limit($length)->process($this);
    }

    /**
     * @param Closure(array<string|null>, array<string|null>): int $orderBy
     *
     * @throws Exception
     * @throws SyntaxError
     */
    public function sorted(Closure $orderBy): TabularDataReader
    {
        return Statement::create()->orderBy($orderBy)->process($this);
    }

    public function matching(string $expression): iterable
    {
        return FragmentFinder::create()->findAll($expression, $this);
    }

    public function matchingFirst(string $expression): ?TabularDataReader
    {
        return FragmentFinder::create()->findFirst($expression, $this);
    }

    /**
     * @throws SyntaxError
     * @throws FragmentNotFound
     */
    public function matchingFirstOrFail(string $expression): TabularDataReader
    {
        return FragmentFinder::create()->findFirstOrFail($expression, $this);
    }

    public function select(string|int ...$columns): TabularDataReader
    {
        return ResultSet::createFromTabularDataReader($this)->select(...$columns);
    }

    /**
     * @param array<string> $header
     *
     * @throws Exception
     *
     * @return Iterator<array<mixed>>
     */
    public function getRecords(array $header = []): Iterator
    {
        $header = $this->prepareHeader($header);

        return $this->combineHeader($this->prepareRecords(), $header);
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
        if ($header !== (array_filter($header, is_string(...)))) {
            throw SyntaxError::dueToInvalidHeaderColumnNames();
        }

        return $this->computeHeader($header);
    }

    /**
     * @param class-string $className
     * @param array<string> $header
     *
     * @throws Exception
     * @throws MappingFailed
     * @throws TypeCastingFailed
     */
    public function getObjects(string $className, array $header = []): Iterator
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
     * Returns the header to be used for iteration.
     *
     * @param array<array-key, string|int> $header
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

    /**
     * Strips the BOM sequence from the returned records if necessary.
     */
    protected function stripBOM(Iterator $iterator, string $bom): Iterator
    {
        if ('' === $bom) {
            return $iterator;
        }

        $bom_length = mb_strlen($bom);
        $mapper = function (array $record, int $index) use ($bom_length): array {
            if (0 !== $index) {
                return $record;
            }

            $record = $this->removeBOM($record, $bom_length, $this->enclosure);
            if ([''] === $record) {
                return [null];
            }

            return $record;
        };

        return new CallbackFilterIterator(
            new MapIterator($iterator, $mapper),
            fn (array $record): bool => $this->is_empty_records_included || $record !== [null]
        );
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

        if (null !== $offset && 0 > $offset) {
            throw InvalidArgument::dueToInvalidHeaderOffset($offset, __METHOD__);
        }

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

    /** @codeCoverageIgnore */
    public function fetchColumn($index = 0): Iterator
    {
        return ResultSet::createFromTabularDataReader($this)->fetchColumn($index);
    }

    /** @codeCoverageIgnore */
    public function fetchOne(int $nth_record = 0): array
    {
        return $this->nth($nth_record);
    }

    /** @codeCoverageIgnore */
    protected function combineHeader(Iterator $iterator, array $header): Iterator
    {
        $formatter = fn (array $record): array => array_reduce(
            $this->formatters,
            fn (array $record, callable $formatter): array => $formatter($record),
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
}
