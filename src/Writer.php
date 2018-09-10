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
use function array_map;
use function array_reduce;
use function gettype;
use function implode;
use function is_iterable;
use function sprintf;
use function str_replace;
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
    const MODE_PHP = 'MODE_PHP';

    const MODE_RFC4180 = 'MODE_RFC4180';

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
     * Regular expression used to detect if enclosure are necessary or not.
     *
     * @var string
     */
    protected $rfc4180_regexp;

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
    public function insertAll($records, string $mode = self::MODE_PHP): int
    {
        if (!is_iterable($records)) {
            throw new TypeError(sprintf('%s() expects argument passed to be iterable, %s given', __METHOD__, gettype($records)));
        }

        $bytes = 0;
        foreach ($records as $record) {
            $bytes += $this->insertOne($record, $mode);
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
    public function insertOne(array $record, string $mode = self::MODE_PHP): int
    {
        static $method = [self::MODE_PHP => 'fputcsvPHP', self::MODE_RFC4180 => 'fputcsvRFC4180'];
        if (!isset($method[$mode])) {
            throw new Exception(sprintf('Unknown or unsupported writing mode %s', $mode));
        }

        $record = array_reduce($this->formatters, [$this, 'formatRecord'], $record);
        $this->validateRecord($record);
        $bytes = $this->{$method[$mode]}($record);
        if (false !== $bytes && 0 !== $bytes) {
            return $bytes + $this->consolidate();
        }

        throw CannotInsertRecord::triggerOnInsertion($record);
    }

    /**
     * Adds a single record to a CSV Document using PHP algorithm.
     */
    protected function fputcsvPHP(array $record)
    {
        return $this->document->fputcsv($record, $this->delimiter, $this->enclosure, $this->escape);
    }

    /**
     * Adds a single record to a CSV Document using RFC4180 algorithm.
     */
    protected function fputcsvRFC4180(array $record)
    {
        return $this->document->fwrite(implode($this->delimiter, array_map([$this, 'convertField'], $record))."\n");
    }

    /**
     * Converts and Format a record field to be inserted into a CSV Document.
     *
     * @see https://tools.ietf.org/html/rfc4180
     */
    protected function convertField($field): string
    {
        $field = (string) $field;
        if (!preg_match($this->rfc4180_regexp, $field)) {
            return $field;
        }

        return $this->enclosure
            .str_replace($this->enclosure, $this->enclosure.$this->enclosure, $field)
            .$this->enclosure
        ;
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
     * Reset dynamic object properties to improve performance.
     */
    protected function resetProperties()
    {
        $this->rfc4180_regexp = "/[\n|\r"
            .preg_quote($this->delimiter, '/')
            .'|'
            .preg_quote($this->enclosure, '/')
        .']/';
    }

    /**
     * Set the flush threshold.
     *
     * @param int|null $threshold
     *
     * @throws Exception if the threshold is a integer lesser than 1
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
