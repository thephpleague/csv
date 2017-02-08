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

/**
 * A class to manage data insertion into a CSV
 *
 * @package League.csv
 * @since  4.0.0
 *
 */
class Writer extends AbstractCsv
{
    /**
     * Callables to validate the record before insertion
     *
     * @var callable[]
     */
    protected $validators = [];

    /**
     * Callables to format the record before insertion
     *
     * @var callable[]
     */
    protected $formatters = [];

    /**
     * @inheritdoc
     */
    protected $stream_filter_mode = STREAM_FILTER_WRITE;

    /**
     * Insert Rows count
     *
     * @var int
     */
    protected $insert_count = 0;

    /**
     * newline character
     *
     * @var string
     */
    protected $newline = "\n";

    /**
     * Buffer flush threshold
     *
     * @var int
     */
    protected $flush_threshold = 500;

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
     * add a formatter to the collection
     *
     * @param callable $callable
     *
     * @return static
     */
    public function addFormatter(callable $callable): self
    {
        $this->formatters[] = $callable;

        return $this;
    }

    /**
     * add a Validator to the collection
     *
     * @param callable $callable
     * @param string   $name     the rule name
     *
     * @return static
     */
    public function addValidator(callable $callable, string $name): self
    {
        $this->validators[$name] = $callable;

        return $this;
    }

    /**
     * Adds multiple lines to the CSV document
     *
     * a simple wrapper method around insertOne
     *
     * @param Traversable|array $rows a multidimensional array or a Traversable object
     *
     * @throws Exception If the given rows format is invalid
     *
     * @return int
     */
    public function insertAll($rows): int
    {
        if (!is_array($rows) && !$rows instanceof Traversable) {
            throw new Exception('the provided data must be an array OR a `Traversable` object');
        }

        $bytes = 0;
        foreach ($rows as $row) {
            $bytes += $this->insertOne($row);
        }

        return $bytes;
    }

    /**
     * Adds a single line to a CSV document
     *
     * @param string[] $row an array
     *
     * @throws InsertionException If the row can not be inserted
     *
     * @return int
     */
    public function insertOne(array $row): int
    {
        $record = array_reduce($this->formatters, [$this, 'formatRecord'], $row);
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
     * @param string[] $row
     * @param callable $formatter
     *
     * @return string[]
     */
    protected function formatRecord(array $row, callable $formatter): array
    {
        return $formatter($row);
    }

    /**
    * Validate a row
    *
    * @param string[] $row
    *
    * @throws InsertionException If the validation failed
    */
    protected function validateRecord(array $row)
    {
        foreach ($this->validators as $name => $validator) {
            if (true !== $validator($row)) {
                throw InsertionException::createFromValidator($name, $row);
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

        $this->insert_count++;
        if (null !== $this->flush_threshold && 0 === $this->insert_count % $this->flush_threshold) {
            $this->document->fflush();
        }

        return $bytes;
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
        $this->newline = (string) $newline;

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
            $val = $this->filterInteger($val, 1, 'The flush threshold must be a valid positive integer or null');
        }

        $this->flush_threshold = $val;

        return $this;
    }
}
