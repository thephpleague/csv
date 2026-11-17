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

use Closure;
use Deprecated;
use SplFileInfo;
use Throwable;

use function array_map;
use function implode;
use function is_numeric;
use function str_replace;

use const STREAM_FILTER_WRITE;

/**
 * A class to insert records into a CSV Document.
 */
class Writer extends AbstractCsv implements TabularDataWriter
{
    protected const STREAM_FILTER_MODE = STREAM_FILTER_WRITE;
    /** @var array<Closure(array): bool> callable collection to validate the record before insertion. */
    protected array $validators = [];
    protected string $newline = "\n";
    protected int $flush_counter = 0;
    protected ?int $flush_threshold = null;
    protected Quoting $quoting = Quoting::Minimal;

    /**
     * @param non-empty-string $mode the file path open mode
     * @param resource|null $context the resource context used with a file path or a SplFileInfo object
     *
     * @throws UnavailableStream
     */
    public static function fromPath(SplFileInfo|string $path, string $mode = 'r+', $context = null): static
    {
        return parent::fromPath($path, $mode, $context);
    }

    /**
     * @throws InvalidArgument
     */
    protected function resetProperties(): void
    {
        parent::resetProperties();

        Quoting::EscapeOnly !== $this->quoting || '' !== $this->escape || throw new InvalidArgument(Quoting::class.'::'.Quoting::EscapeOnly->name.' cannot be used with an empty escape character.');
        Quoting::NonNumeric !== $this->quoting || ('.' !== $this->enclosure && '.' !== $this->delimiter) || throw new InvalidArgument(Quoting::class.'::'.Quoting::NonNumeric->name.' cannot be used with "." as delimiter or enclosure.');
    }

    /**
     * Returns the current end of line sequence characters.
     */
    public function getEndOfLine(): string
    {
        return $this->newline;
    }

    /**
     * Returns the flush threshold.
     */
    public function getFlushThreshold(): ?int
    {
        return $this->flush_threshold;
    }

    /**
     * Returns the quoting mechanism.
     */
    public function getQuoting(): Quoting
    {
        return $this->quoting;
    }

    /**
     * Adds multiple records to the CSV document.
     * @see Writer::insertOne
     *
     * @throws CannotInsertRecord|Exception|Throwable If the record can not be inserted
     */
    public function insertAll(TabularDataProvider|TabularData|iterable $records): int
    {
        $bytes = 0;
        foreach (match (true) {
            $records instanceof TabularDataProvider => $records->getTabularData()->getRecords(),
            $records instanceof TabularData => $records->getRecords(),
            default => $records,
        } as $record) {
            $bytes += $this->insertOne($record);
        }

        $this->flush_counter = 0;
        $this->document->fflush();

        return $bytes;
    }

    /**
     * Adds a single record to a CSV document.
     *
     * @throws CannotInsertRecord|Exception|Throwable If the record can not be inserted
     */
    public function insertOne(array $record): int
    {
        foreach ($this->formatters as $formatter) {
            $record = $formatter($record);
        }

        foreach ($this->validators as $name => $validator) {
            true === $validator($record) || throw CannotInsertRecord::triggerOnValidation($name, $record);
        }

        /** @var int|false $bytes */
        $bytes = Warning::cloak($this->insertRecord(...), $record);
        false !== $bytes || throw CannotInsertRecord::triggerOnInsertion($record);

        if (null === $this->flush_threshold) {
            return $bytes;
        }

        ++$this->flush_counter;
        if (0 === $this->flush_counter % $this->flush_threshold) {
            $this->flush_counter = 0;
            $this->document->fflush();
        }

        return $bytes;
    }

    /**
     * @throws InvalidArgument
     */
    protected function insertRecord(array $record): int|false
    {
        return match ($this->quoting) {
            Quoting::Minimal => $this->document->fputcsv($record, $this->delimiter, $this->enclosure, $this->escape, $this->newline),
            Quoting::All => $this->document->fwrite(implode($this->delimiter, $this->quoteAll($record)).$this->newline),
            Quoting::None => $this->document->fwrite(implode($this->delimiter, $record).$this->newline),
            Quoting::NonNumeric => $this->document->fwrite(implode($this->delimiter, $this->quoteNonNumeric($record)).$this->newline),
            Quoting::EscapeOnly => $this->document->fwrite(implode($this->delimiter, $this->escapeOnlyField($record)).$this->newline),
        };
    }

    final protected function quoteAll(array $record): array
    {
        return array_map(
            fn ($item): string =>
                $this->enclosure
                .str_replace($this->enclosure, $this->enclosure.$this->enclosure, (string) $item)
                .$this->enclosure,
            $record
        );
    }

    final protected function quoteNonNumeric(array $record): array
    {
        return array_map(
            fn ($item): string => is_numeric($item)
                ? (string) $item
                : $this->enclosure
                .str_replace($this->enclosure, $this->enclosure.$this->enclosure, (string) $item)
                .$this->enclosure,
            $record
        );
    }

    final protected function escapeOnlyField(array $record): array
    {
        return array_map(
            fn ($item): string => str_replace(
                [$this->delimiter, $this->escape, "\r", "\n"],
                [$this->escape.$this->delimiter, $this->escape.$this->escape, $this->escape.'r', $this->escape.'n'],
                (string) $item
            ),
            $record
        );
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
     * Sets the end of line sequence.
     */
    public function setEndOfLine(string $endOfLine): self
    {
        if ($endOfLine === $this->newline) {
            return $this;
        }

        $this->newline = $endOfLine;

        return $this;
    }

    /**
     * Sets the flush threshold.
     *
     * @throws InvalidArgument if the threshold is an integer less than 1
     */
    public function setFlushThreshold(?int $threshold): self
    {
        if ($threshold === $this->flush_threshold) {
            return $this;
        }

        null === $threshold || 1 <= $threshold || throw InvalidArgument::dueToInvalidThreshold($threshold, __METHOD__);

        $this->flush_threshold = $threshold;
        $this->flush_counter = 0;
        $this->document->fflush();

        return $this;
    }

    /**
     * Controls quoting and escaping during record insertion.
     *
     * @throws InvalidArgument
     */
    public function setQuoting(Quoting $quoting): self
    {
        if ($quoting === $this->quoting) {
            return $this;
        }

        $this->quoting = $quoting;
        $this->resetProperties();

        return $this;
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @deprecated since version 9.8.0, no longer affecting the class behaviour
     * @codeCoverageIgnore
     *
     * Validates a record.
     *
     * @throws CannotInsertRecord If the validation failed
     */
    #[Deprecated(message:'no longer affecting the class behaviour', since:'league/csv:9.30.0')]
    protected function validateRecord(array $record): void
    {
        foreach ($this->validators as $name => $validator) {
            true === $validator($record) || throw CannotInsertRecord::triggerOnValidation($name, $record);
        }
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @deprecated since version 9.30.0, use setQuoting instead
     * @codeCoverageIgnore
     */
    #[Deprecated(message:'use League\Csv\Writer::getQuoting()', since:'league/csv:9.30.0')]
    public function encloseAll(): bool
    {
        return Quoting::All === $this->quoting;
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @deprecated since version 9.30.0, use setQuoting instead
     * @codeCoverageIgnore
     */
    #[Deprecated(message:'use League\Csv\Writer::getQuoting()', since:'league/csv:9.30.0')]
    public function encloseNecessary(): bool
    {
        return Quoting::Minimal === $this->quoting;
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @deprecated since version 9.30.0, use setQuoting instead
     * @codeCoverageIgnore
     */
    #[Deprecated(message:'use League\Csv\Writer::getQuoting()', since:'league/csv:9.30.0')]
    public function encloseNone(): bool
    {
        return Quoting::None === $this->quoting;
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @deprecated since version 9.30.0, use setQuoting instead
     * @codeCoverageIgnore
     */
    #[Deprecated(message:'use League\Csv\Writer::setQuoting()', since:'league/csv:9.30.0')]
    public function necessaryEnclosure(): self
    {
        return $this->setQuoting(Quoting::Minimal);
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @deprecated since version 9.30.0, use setQuoting instead
     * @codeCoverageIgnore
     */
    #[Deprecated(message:'use League\Csv\Writer::setQuoting()', since:'league/csv:9.30.0')]
    public function forceEnclosure(): self
    {
        return $this->setQuoting(Quoting::All);
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @deprecated since version 9.30.0, use setQuoting instead
     * @codeCoverageIgnore
     */
    #[Deprecated(message:'use League\Csv\Writer::setQuoting()', since:'league/csv:9.30.0')]
    public function noEnclosure(): self
    {
        return $this->setQuoting(Quoting::None);
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @deprecated since version 9.8.0, no longer affecting the class behaviour
     * @codeCoverageIgnore
     *
     * Format a record.
     *
     * The returned array must contain
     *   - scalar types values,
     *   - NULL values,
     *   - or objects implementing the __toString() method.
     */
    #[Deprecated(message:'no longer affecting the class behaviour', since:'league/csv:9.8.0')]
    protected function formatRecord(array $record, callable $formatter): array
    {
        return $formatter($record);
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @deprecated Since version 9.9.0, no longer affecting the class behaviour
     * @codeCoverageIgnore
     *
     * Adds a single record to a CSV Document using PHP algorithm.
     *
     * @see https://php.net/manual/en/function.fputcsv.php
     */
    #[Deprecated(message:'no longer affecting the class behaviour', since:'league/csv:9.9.0')]
    protected function addRecord(array $record): int|false
    {
        return $this->document->fputcsv($record, $this->delimiter, $this->enclosure, $this->escape, $this->newline);
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @deprecated Since version 9.9.0, no longer affecting the class behaviour
     * @codeCoverageIgnore
     *
     * Applies post insertion actions.
     */
    #[Deprecated(message:'no longer affecting the class behaviour', since:'league/csv:9.9.0')]
    protected function consolidate(): int
    {
        if (null === $this->flush_threshold) {
            return 0;
        }

        ++$this->flush_counter;
        if (0 === $this->flush_counter % $this->flush_threshold) {
            $this->flush_counter = 0;
            $this->document->fflush();
        }

        return 0;
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @see Writer::getEndOfLine()
     * @deprecated Since version 9.10.0, use League\Csv\Writer::getEndOfLine()
     * @codeCoverageIgnore
     *
     * Returns the current newline sequence characters.
     */
    #[Deprecated(message:'use League\Csv\Writer::getEndOfLine()', since:'league/csv:9.10.0')]
    public function getNewline(): string
    {
        return $this->getEndOfLine();
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @see Writer::setEndOfLine()
     * @deprecated Since version 9.10.0, use League\Csv\Writer::setEndOfLine()
     * @codeCoverageIgnore
     *
     * Sets the newline sequence.
     */
    #[Deprecated(message:'use League\Csv\Writer::setEndOfLine()', since:'league/csv:9.10.0')]
    public function setNewline(string $newline): self
    {
        return $this->setEndOfLine($newline);
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @see Writer::necessaryEnclosure()
     * @deprecated Since version 9.22.0, use League\Csv\Writer::necessaryEnclosure()
     * @codeCoverageIgnore
     *
     * Sets the enclosure threshold to only enclose necessary fields.
     */
    #[Deprecated(message:'use League\Csv\Writer::necessaryEnclosure()', since:'league/csv:9.22.0')]
    public function relaxEnclosure(): self
    {
        return $this->necessaryEnclosure();
    }
}
