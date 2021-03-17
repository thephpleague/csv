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

use function array_reduce;
use function implode;
use function preg_match;
use function preg_quote;
use function str_replace;
use function strlen;
use const PHP_VERSION_ID;
use const SEEK_CUR;
use const STREAM_FILTER_WRITE;

/**
 * A class to insert records into a CSV Document.
 */
class Writer extends AbstractCsv
{
    protected const STREAM_FILTER_MODE = STREAM_FILTER_WRITE;

    /**
     * callable collection to format the record before insertion.
     *
     * @var callable[]
     */
    protected $formatters = [];

    /**
     * callable collection to validate the record before insertion.
     *
     * @var callable[]
     */
    protected $validators = [];

    /**
     * newline character.
     *
     * @var string
     */
    protected $newline = "\n";

    /**
     * Insert records count for flushing.
     *
     * @var int
     */
    protected $flush_counter = 0;

    /**
     * Buffer flush threshold.
     *
     * @var int|null
     */
    protected $flush_threshold;

    /**
     * Regular expression used to detect if RFC4180 formatting is necessary.
     *
     * @var string
     */
    protected $rfc4180_regexp;

    /**
     * double enclosure for RFC4180 compliance.
     *
     * @var string
     */
    protected $rfc4180_enclosure;

    /**
     * {@inheritdoc}
     */
    protected function resetProperties(): void
    {
        $characters = preg_quote($this->delimiter, '/').'|'.preg_quote($this->enclosure, '/');
        $this->rfc4180_regexp = '/[\s|'.$characters.']/x';
        $this->rfc4180_enclosure = $this->enclosure.$this->enclosure;
    }

    /**
     * Returns the current newline sequence characters.
     */
    public function getNewline(): string
    {
        return $this->newline;
    }

    /**
     * Get the flush threshold.
     *
     * @return int|null
     */
    public function getFlushThreshold()
    {
        return $this->flush_threshold;
    }

    /**
     * Adds multiple records to the CSV document.
     *
     * @see Writer::insertOne
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
     * A record is an array that can contains scalar types values, NULL values
     * or objects implementing the __toString method.
     *
     * @throws CannotInsertRecord If the record can not be inserted
     */
    public function insertOne(array $record): int
    {
        $method = 'addRecord';
        if (70400 > PHP_VERSION_ID && '' === $this->escape) {
            $method = 'addRFC4180CompliantRecord';
        }

        $record = array_reduce($this->formatters, [$this, 'formatRecord'], $record);
        $this->validateRecord($record);
        $bytes = $this->$method($record);
        if (false === $bytes || 0 >= $bytes) {
            throw CannotInsertRecord::triggerOnInsertion($record);
        }

        return $bytes + $this->consolidate();
    }

    /**
     * Adds a single record to a CSV Document using PHP algorithm.
     *
     * @see https://php.net/manual/en/function.fputcsv.php
     *
     * @return int|false
     */
    protected function addRecord(array $record)
    {
        return $this->document->fputcsv($record, $this->delimiter, $this->enclosure, $this->escape);
    }

    /**
     * Adds a single record to a CSV Document using RFC4180 algorithm.
     *
     * @see https://php.net/manual/en/function.fputcsv.php
     * @see https://php.net/manual/en/function.fwrite.php
     * @see https://tools.ietf.org/html/rfc4180
     * @see http://edoceo.com/utilitas/csv-file-format
     *
     * String conversion is done without any check like fputcsv.
     *
     *     - Emits E_NOTICE on Array conversion (returns the 'Array' string)
     *     - Throws catchable fatal error on objects that can not be converted
     *     - Returns resource id without notice or error (returns 'Resource id #2')
     *     - Converts boolean true to '1', boolean false to the empty string
     *     - Converts null value to the empty string
     *
     * Fields must be delimited with enclosures if they contains :
     *
     *     - Embedded whitespaces
     *     - Embedded delimiters
     *     - Embedded line-breaks
     *     - Embedded enclosures.
     *
     * Embedded enclosures must be doubled.
     *
     * The LF character is added at the end of each record to mimic fputcsv behavior
     *
     * @return int|false
     */
    protected function addRFC4180CompliantRecord(array $record)
    {
        foreach ($record as &$field) {
            $field = (string) $field;
            if (1 === preg_match($this->rfc4180_regexp, $field)) {
                $field = $this->enclosure.str_replace($this->enclosure, $this->rfc4180_enclosure, $field).$this->enclosure;
            }
        }
        unset($field);

        return $this->document->fwrite(implode($this->delimiter, $record)."\n");
    }

    /**
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
     * Validate a record.
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
     * Apply post insertion actions.
     */
    protected function consolidate(): int
    {
        $bytes = 0;
        if ("\n" !== $this->newline) {
            $this->document->fseek(-1, SEEK_CUR);
            /** @var int $newlineBytes */
            $newlineBytes = $this->document->fwrite($this->newline, strlen($this->newline));
            $bytes =  $newlineBytes - 1;
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
     * Sets the newline sequence.
     */
    public function setNewline(string $newline): self
    {
        $this->newline = $newline;

        return $this;
    }

    /**
     * Set the flush threshold.
     *
     * @param ?int $threshold
     *
     * @throws InvalidArgument if the threshold is a integer lesser than 1
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
}
