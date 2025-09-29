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

use function array_map;
use function array_reduce;
use function implode;
use function str_replace;

use const STREAM_FILTER_WRITE;

/**
 * A class to insert records into a CSV Document.
 */
class Writer extends AbstractCsv implements TabularDataWriter
{
    protected const ENCLOSE_ALL = 1;
    protected const ENCLOSE_NECESSARY = 0;
    protected const ENCLOSE_NONE = -1;

    protected const STREAM_FILTER_MODE = STREAM_FILTER_WRITE;
    /** @var array<Closure(array): bool> callable collection to validate the record before insertion. */
    protected array $validators = [];
    protected string $newline = "\n";
    protected int $flush_counter = 0;
    protected ?int $flush_threshold = null;
    protected int $enclose_all = self::ENCLOSE_NECESSARY;
    /** @var array{0:array<string>,1:array<string>} */
    protected array $enclosure_replace = [[], []];

    protected function resetProperties(): void
    {
        parent::resetProperties();

        $this->enclosure_replace = [
            [$this->enclosure, $this->escape.$this->enclosure.$this->enclosure],
            [$this->enclosure.$this->enclosure, $this->escape.$this->enclosure],
        ];
    }

    protected function insertRecord(array $record): int|false
    {
        return match ($this->enclose_all) {
            self::ENCLOSE_ALL => $this->document->fwrite(implode(
                $this->delimiter,
                array_map(
                    fn ($content) => $this->enclosure.$content.$this->enclosure,
                    str_replace($this->enclosure_replace[0], $this->enclosure_replace[1], $record)
                )
            ).$this->newline),
            self::ENCLOSE_NONE => $this->document->fwrite(implode($this->delimiter, $record).$this->newline),
            default => $this->document->fputcsv($record, $this->delimiter, $this->enclosure, $this->escape, $this->newline),
        };
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
     * Tells whether new entries will all be enclosed on writing.
     */
    public function encloseAll(): bool
    {
        return self::ENCLOSE_ALL === $this->enclose_all;
    }

    /**
     * Tells whether new entries will be selectively enclosed on writing
     * if the field content requires encoding.
     */
    public function encloseNecessary(): bool
    {
        return self::ENCLOSE_NECESSARY === $this->enclose_all;
    }

    /**
     * Tells whether new entries will never be enclosed on writing.
     */
    public function encloseNone(): bool
    {
        return self::ENCLOSE_NONE === $this->enclose_all;
    }

    /**
     * Adds multiple records to the CSV document.
     * @see Writer::insertOne
     *
     * @throws CannotInsertRecord
     * @throws Exception
     */
    public function insertAll(TabularDataProvider|TabularData|iterable $records): int
    {
        if ($records instanceof TabularDataProvider) {
            $records = $records->getTabularData();
        }

        if ($records instanceof TabularData) {
            $records = $records->getRecords();
        }

        $bytes = 0;
        foreach ($records as $record) {
            $bytes += $this->insertOne($record);
        }

        $this->flush_counter = 0;
        $this->document->fflush();

        return $bytes;
    }

    /**
     * Adds a single record to a CSV document.
     *
     * A record is an array that can contain scalar type values, NULL values
     * or objects implementing the __toString method.
     *
     * @throws CannotInsertRecord If the record can not be inserted
     * @throws Exception If the record can not be inserted
     */
    public function insertOne(array $record): int
    {
        $record = array_reduce($this->formatters, fn (array $record, callable $formatter): array => $formatter($record), $record);
        $this->validateRecord($record);
        /** @var int|false $bytes */
        $bytes = Warning::cloak($this->insertRecord(...), $record);
        if (false === $bytes) {
            throw CannotInsertRecord::triggerOnInsertion($record);
        }

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
     * Validates a record.
     *
     * @throws CannotInsertRecord If the validation failed
     */
    protected function validateRecord(array $record): void
    {
        foreach ($this->validators as $name => $validator) {
            true === $validator($record) || throw CannotInsertRecord::triggerOnValidation($name, $record);
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
     * Sets the end of line sequence.
     */
    public function setEndOfLine(string $endOfLine): self
    {
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

    public function necessaryEnclosure(): self
    {
        $this->enclose_all = self::ENCLOSE_NECESSARY;
        $this->resetProperties();

        return $this;
    }

    public function forceEnclosure(): self
    {
        $this->enclose_all = self::ENCLOSE_ALL;
        $this->resetProperties();

        return $this;
    }

    public function noEnclosure(): self
    {
        $this->enclose_all = self::ENCLOSE_NONE;
        $this->resetProperties();

        return $this;
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @deprecated since version 9.8.0
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
     * @deprecated Since version 9.9.0
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
     * @deprecated Since version 9.9.0
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
     * @deprecated Since version 9.10.0
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
     * @deprecated Since version 9.10.0
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
     * @deprecated Since version 9.22.0
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
