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

use function array_map;
use function array_reduce;
use function implode;
use function restore_error_handler;
use function set_error_handler;
use function str_replace;

use const STREAM_FILTER_WRITE;

/**
 * A class to insert records into a CSV Document.
 */
class Writer extends AbstractCsv implements TabularDataWriter
{
    protected const STREAM_FILTER_MODE = STREAM_FILTER_WRITE;

    /** @var array<callable> callable collection to format the record before insertion. */
    protected array $formatters = [];
    /** @var array<callable> callable collection to validate the record before insertion. */
    protected array $validators = [];
    protected string $newline = "\n";
    protected int $flush_counter = 0;
    protected ?int $flush_threshold = null;
    protected bool $enclose_all = false;
    /** @var array{0:array<string>,1:array<string>} */
    protected array $enclosure_replace;

    protected function resetProperties(): void
    {
        parent::resetProperties();

        $this->enclosure_replace = [
            [$this->enclosure, $this->escape.$this->enclosure.$this->enclosure],
            [$this->enclosure.$this->enclosure, $this->escape.$this->enclosure],
        ];
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
        return $this->enclose_all;
    }

    /**
     * Adds multiple records to the CSV document.
     * @see Writer::insertOne
     *
     * @throws CannotInsertRecord
     * @throws Exception
     */
    public function insertAll(iterable $records): int
    {
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
        $insert = fn (array $record): int|false => match (true) {
            $this->enclose_all => $this->document->fwrite(implode(
                $this->delimiter,
                array_map(
                    fn ($content) => $this->enclosure.$content.$this->enclosure,
                    str_replace($this->enclosure_replace[0], $this->enclosure_replace[1], $record)
                )
            ).$this->newline),
            default => $this->document->fputcsv($record, $this->delimiter, $this->enclosure, $this->escape, $this->newline),
        };

        $record = array_reduce($this->formatters, fn (array $record, callable $formatter): array => $formatter($record), $record);
        $this->validateRecord($record);
        set_error_handler(fn (int $errno, string $errstr, string $errfile, int $errline) => true);
        $bytes = $insert($record);
        restore_error_handler();
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
            if (true !== $validator($record)) {
                throw CannotInsertRecord::triggerOnValidation($name, $record);
            }
        }
    }

    /**
     * Adds a record formatter.
     */
    public function addFormatter(callable $formatter): self
    {
        $this->formatters[] = $formatter;

        return $this;
    }

    /**
     * Adds a record validator.
     */
    public function addValidator(callable $validator, string $validator_name): self
    {
        $this->validators[$validator_name] = $validator;

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
     * @throws InvalidArgument if the threshold is a integer less than 1
     */
    public function setFlushThreshold(?int $threshold): self
    {
        if ($threshold === $this->flush_threshold) {
            return $this;
        }

        if (null !== $threshold && 1 > $threshold) {
            throw InvalidArgument::dueToInvalidThreshold($threshold, __METHOD__);
        }

        $this->flush_threshold = $threshold;
        $this->flush_counter = 0;
        $this->document->fflush();

        return $this;
    }

    public function relaxEnclosure(): self
    {
        $this->enclose_all = false;

        return $this;
    }

    public function forceEnclosure(): self
    {
        $this->enclose_all = true;

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
    public function setNewline(string $newline): self
    {
        return $this->setEndOfLine($newline);
    }
}
