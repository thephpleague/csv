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

use League\Csv\Exception\InsertionException;
use League\Csv\Exception\InvalidArgumentException;
use Traversable;

/**
 * A class to manage data insertion into a CSV
 *
 * @package League.csv
 * @since   4.0.0
 * @author  Ignace Nyamagana Butera <nyamsprod@gmail.com>
 *
 */
class Writer extends AbstractCsv
{
    /**
     * @inheritdoc
     */
    protected $stream_filter_mode = STREAM_FILTER_WRITE;

    /**
     * callable collection to validate the record before insertion
     *
     * @var callable[]
     */
    protected $validators = [];

    /**
     * callable collection to format the record before insertion
     *
     * @var callable[]
     */
    protected $formatters = [];

    /**
     * Insert records count for flushing
     *
     * @var int
     */
    protected $flush_counter = 0;

    /**
     * newline character
     *
     * @var string
     */
    protected $newline = "\n";

    /**
     * Buffer flush threshold
     *
     * @var int|null
     */
    protected $flush_threshold;

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
     * Adds multiple lines to the CSV document
     *
     * a simple wrapper method around insertOne
     *
     * @param Traversable|array $records a multidimensional array or a Traversable object
     *
     * @throws InvalidArgumentException If the given rows format is invalid
     *
     * @return int
     */
    public function insertAll($records): int
    {
        $bytes = 0;
        foreach ($this->filterIterable($records, __METHOD__) as $record) {
            $bytes += $this->insertOne($record);
        }

        $this->flush_counter = 0;
        $this->document->fflush();

        return $bytes;
    }

    /**
     * Adds a single line to a CSV document
     *
     * @param string[] $record an array
     *
     * @throws InsertionException If the record can not be inserted
     *
     * @return int
     */
    public function insertOne(array $record): int
    {
        $record = array_reduce($this->formatters, [$this, 'formatRecord'], $record);
        $this->validateRecord($record);
        $bytes = $this->document->fputcsv($record, $this->delimiter, $this->enclosure, $this->escape);
        if (!$bytes) {
            throw InsertionException::createFromCsv($record);
        }

        return $bytes + $this->consolidate();
    }

    /**
     * Format the given row
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
     * Validate a row
     *
     * @param string[] $record
     *
     * @throws InsertionException If the validation failed
     */
    protected function validateRecord(array $record)
    {
        foreach ($this->validators as $name => $validator) {
            if (true !== ($validator)($record)) {
                throw InsertionException::createFromValidator($name, $record);
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
     * add a formatter to the collection
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
     * add a Validator to the collection
     *
     * @param callable $validator
     * @param string   $name      the validator name
     *
     * @return static
     */
    public function addValidator(callable $validator, string $name): self
    {
        $this->validators[$name] = $validator;

        return $this;
    }

    /**
     * Sets the newline sequence characters
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
     * Set the automatic flush threshold on write
     *
     * @param int|null $val
     */
    public function setFlushThreshold($val): self
    {
        if (null !== $val) {
            $val = $this->filterInteger($val, 1, __METHOD__.': The flush threshold must be a valid positive integer or null');
        }

        if ($val === $this->flush_threshold) {
            return $this;
        }

        $this->flush_threshold = $val;
        if (0 < $this->flush_counter) {
            $this->flush_counter = 0;
            $this->document->fflush();
        }

        return $this;
    }
}
