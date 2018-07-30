<?php

/**
 * League.Csv (https://csv.thephpleague.com).
 *
 * @author  Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @license https://github.com/thephpleague/csv/blob/master/LICENSE (MIT License)
 * @version 9.1.5
 * @link    https://github.com/thephpleague/csv
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace League\Csv;

use Traversable;
use TypeError;
use const SEEK_CUR;
use const STREAM_FILTER_WRITE;
use function array_reduce;
use function gettype;
use function sprintf;
use function strlen;

/**
 * A class to insert records into a CSV Document.
 *
 * @package League.csv
 * @since   4.0.0
 * @author  Ignace Nyamagana Butera <nyamsprod@gmail.com>
 */
class Writer extends AbstractCsv
{
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
     * {@inheritdoc}
     */
    protected $stream_filter_mode = STREAM_FILTER_WRITE;

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
     *
     * @param Traversable|array $records
     */
    public function insertAll($records): int
    {
        if (!\is_iterable($records)) {
            throw new TypeError(sprintf('%s() expects argument passed to be iterable, %s given', __METHOD__, gettype($records)));
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
     * A record is an array that can contains scalar types values, NULL values
     * or objects implementing the __toString method.
     *
     *
     * @throws CannotInsertRecord If the record can not be inserted
     */
    public function insertOne(array $record): int
    {
        $record = array_reduce($this->formatters, [$this, 'formatRecord'], $record);
        $this->validateRecord($record);
        $bytes = $this->document->fputcsv($record, $this->delimiter, $this->enclosure, $this->escape);
        if ('' !== (string) $bytes) {
            return $bytes + $this->consolidate();
        }

        throw CannotInsertRecord::triggerOnInsertion($record);
    }

    /**
     * Format a record.
     *
     * The returned array must contain
     *   - scalar types values,
     *   - NULL values,
     *   - or objects implementing the __toString() method.
     *
     */
    protected function formatRecord(array $record, callable $formatter): array
    {
        return $formatter($record);
    }

    /**
     * Validate a record.
     *
     *
     * @throws CannotInsertRecord If the validation failed
     */
    protected function validateRecord(array $record)
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
            $bytes = $this->document->fwrite($this->newline, strlen($this->newline)) - 1;
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
     *
     * @return static
     */
    public function addFormatter(callable $formatter): self
    {
        $this->formatters[] = $formatter;

        return $this;
    }

    /**
     * Adds a record validator.
     *
     *
     * @return static
     */
    public function addValidator(callable $validator, string $validator_name): self
    {
        $this->validators[$validator_name] = $validator;

        return $this;
    }

    /**
     * Sets the newline sequence.
     *
     * @return static
     */
    public function setNewline(string $newline): self
    {
        $this->newline = $newline;

        return $this;
    }

    /**
     * Set the flush threshold.
     *
     * @param int|null $threshold
     *
     * @throws Exception if the threshold is a integer lesser than 1
     *
     * @return static
     */
    public function setFlushThreshold($threshold): self
    {
        if ($threshold === $this->flush_threshold) {
            return $this;
        }

        if (!is_nullable_int($threshold)) {
            throw new TypeError(sprintf(__METHOD__.'() expects 1 Argument to be null or an integer %s given', gettype($threshold)));
        }

        if (null !== $threshold && 1 > $threshold) {
            throw new Exception(__METHOD__.'() expects 1 Argument to be null or a valid integer greater or equal to 1');
        }

        $this->flush_threshold = $threshold;
        $this->flush_counter = 0;
        $this->document->fflush();

        return $this;
    }
}
