<?php
/**
* This file is part of the League.csv library
*
* @license http://opensource.org/licenses/MIT
* @link https://github.com/thephpleague/csv/
* @version 9.0.0
* @package League.csv
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
declare(strict_types=1);

namespace League\Csv;

use Traversable;
use TypeError;

/**
 * A class to insert records into a CSV Document
 *
 * @package League.csv
 * @since   4.0.0
 * @author  Ignace Nyamagana Butera <nyamsprod@gmail.com>
 */
class Writer extends AbstractCsv
{
    /**
     * callable collection to format the record before insertion
     *
     * @var callable[]
     */
    protected $formatters = [];

    /**
     * callable collection to validate the record before insertion
     *
     * @var callable[]
     */
    protected $validators = [];

    /**
     * newline character
     *
     * @var string
     */
    protected $newline = "\n";

    /**
     * Insert records count for flushing
     *
     * @var int
     */
    protected $flush_counter = 0;

    /**
     * Buffer flush threshold
     *
     * @var int|null
     */
    protected $flush_threshold;

    /**
     * @inheritdoc
     */
    protected $stream_filter_mode = STREAM_FILTER_WRITE;

    /**
     * Returns the current newline sequence characters
     *
     * @return string
     */
    public function getNewline(): string
    {
        return $this->newline;
    }

    /**
     * Get the flush threshold
     *
     * @return int|null
     */
    public function getFlushThreshold()
    {
        return $this->flush_threshold;
    }

    /**
     * Adds multiple records to the CSV document
     *
     * a simple wrapper method around insertOne
     *
     * @param Traversable|array $records a multidimensional array or a Traversable object
     *
     * @return int
     */
    public function insertAll($records): int
    {
        if (!is_iterable($records)) {
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
     * Adds a single record to a CSV document
     *
     * @param string[] $record an array
     *
     * @throws CannotInsertRecord If the record can not be inserted
     *
     * @return int
     */
    public function insertOne(array $record): int
    {
        $record = array_reduce($this->formatters, [$this, 'formatRecord'], $record);
        $this->validateRecord($record);
        $bytes = $this->document->fputcsv($record, ...$this->document->getCsvControl());
        if (!$bytes) {
            throw CannotInsertRecord::triggerOnInsertion($record);
        }

        return $bytes + $this->consolidate();
    }

    /**
     * Format a record
     *
     * @param string[] $record
     * @param callable $formatter
     *
     * @return string[]
     */
    protected function formatRecord(array $record, callable $formatter): array
    {
        return $formatter($record);
    }

    /**
     * Validate a record
     *
     * @param string[] $record
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
     * Apply post insertion actions
     *
     * @return int
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
     * Adds a record formatter
     *
     * @param callable $formatter
     *
     * @return static
     */
    public function addFormatter(callable $formatter): self
    {
        $this->formatters[] = $formatter;

        return $this;
    }

    /**
     * Adds a record validator
     *
     * @param callable $validator
     * @param string   $validator_name the validator name
     *
     * @return static
     */
    public function addValidator(callable $validator, string $validator_name): self
    {
        $this->validators[$validator_name] = $validator;

        return $this;
    }

    /**
     * Sets the newline sequence
     *
     * @param string $newline
     *
     * @return static
     */
    public function setNewline(string $newline): self
    {
        $this->newline = $newline;

        return $this;
    }

    /**
     * Set the flush threshold
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

        if (null !== $threshold && 1 >= $threshold) {
            throw new Exception(__METHOD__.'() expects 1 Argument to be null or a valid integer greater or equal to 1');
        }

        $this->flush_threshold = $threshold;
        $this->flush_counter = 0;
        $this->document->fflush();

        return $this;
    }
}
