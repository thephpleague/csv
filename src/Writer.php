<?php
/**
* This file is part of the League.csv library
*
* @license http://opensource.org/licenses/MIT
* @link https://github.com/thephpleague/csv/
* @version 7.0.0
* @package League.csv
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
namespace League\Csv;

use InvalidArgumentException;
use OutOfBoundsException;
use RuntimeException;
use SplFileObject;
use Traversable;

/**
 *  A class to manage data insertion into a CSV
 *
 * @package League.csv
 * @since  4.0.0
 *
 */
class Writer extends AbstractCsv
{
    /**
     * set null handling mode to throw exception
     */
    const NULL_AS_EXCEPTION = 1;

    /**
     * set null handling mode to remove cell
     */
    const NULL_AS_SKIP_CELL = 2;

    /**
     * set null handling mode to convert null into empty string
     */
    const NULL_AS_EMPTY = 3;

    /**
     * disable null handling
     */
    const NULL_HANDLING_DISABLED = 4;

    /**
     * the object current null handling mode
     *
     * @var int
     */
    protected $null_handling_mode = self::NULL_AS_EXCEPTION;

    /**
     * The number of column per row
     *
     * @var int
     */
    protected $columns_count = -1;

    /**
     * should the class detect the column count based the inserted row
     *
     * @var bool
     */
    protected $detect_columns_count = false;

    /**
     * {@ihneritdoc}
     */
    protected $stream_filter_mode = STREAM_FILTER_WRITE;

    /**
     * The CSV object holder
     *
     * @var \SplFileObject
     */
    protected $csv;

    /**
     * should the class validate the input before insertion
     *
     * @var boolean
     */
    protected $useValidation = true;

    /**
     * Tell the class how to handle null value
     *
     * @param int $value a Writer null behavior constant
     *
     * @throws \OutOfBoundsException If the Integer is not valid
     *
     * @return static
     */
    public function setNullHandlingMode($value)
    {
        if (! in_array($value, [
                self::NULL_AS_SKIP_CELL,
                self::NULL_AS_EXCEPTION,
                self::NULL_AS_EMPTY,
                self::NULL_HANDLING_DISABLED,
            ])) {
            throw new OutOfBoundsException('invalid value for null handling');
        }
        $this->null_handling_mode = $value;

        return $this;
    }

    /**
     * null handling getter
     *
     * @return int
     */
    public function getNullHandlingMode()
    {
        return $this->null_handling_mode;
    }

    /**
     * Set Inserted row column count
     *
     * @param int $value
     *
     * @throws \InvalidArgumentException If $value is lesser than -1
     *
     * @return static
     */
    public function setColumnsCount($value)
    {
        if (false === filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => -1]])) {
            throw new InvalidArgumentException('the column count must an integer greater or equals to -1');
        }
        $this->detect_columns_count = false;
        $this->columns_count = $value;

        return $this;
    }

    /**
     * Column count getter
     *
     * @return int
     */
    public function getColumnsCount()
    {
        return $this->columns_count;
    }

    /**
     * The method will set the $columns_count property according to the next inserted row
     * and therefore will also validate the next line whatever length it has no matter
     * the current $columns_count property value.
     *
     * @return static
     */
    public function autodetectColumnsCount()
    {
        $this->detect_columns_count = true;

        return $this;
    }

    /**
     * Tells wether the library should check or not the input
     *
     * @param  bool $status
     *
     * @return static
     */
    public function useValidation($status)
    {
        $this->useValidation = (bool) $status;

        return $this;
    }

    /**
     * Add multiple lines to the CSV your are generating
     *
     * a simple helper/Wrapper method around insertOne
     *
     * @param \Traversable|array $rows a multidimentional array or a Traversable object
     *
     * @throws \InvalidArgumentException If the given rows format is invalid
     *
     * @return static
     */
    public function insertAll($rows)
    {
        if (! is_array($rows) && ! $rows instanceof Traversable) {
            throw new InvalidArgumentException(
                'the provided data must be an array OR a \Traversable object'
            );
        }

        foreach ($rows as $row) {
            $this->insertOne($row);
        }

        return $this;
    }

    /**
     * Add a new CSV row to the generated CSV
     *
     * @param string[]|string $row a string, an array or an object implementing to '__toString' method
     *
     * @return static
     */
    public function insertOne($row)
    {
        $row = $this->formatRow($row);
        if ($this->useValidation) {
            $row = $this->validateRow($row);
        }
        $csv = $this->getCsv();
        $csv->fputcsv($row, $this->delimiter, $this->enclosure);
        if ("\n" !== $this->newline) {
            $csv->fseek(-1, SEEK_CUR);
            $csv->fwrite($this->newline);
        }

        return $this;
    }

    /**
     * format the submitted data to be an array
     *
     * @param  array|string $row the row data
     *
     * @return array
     */
    protected function formatRow($row)
    {
        if (! is_array($row)) {
            return str_getcsv($row, $this->delimiter, $this->enclosure, $this->escape);
        }

        return $row;
    }

    /**
     * Is the submitted row valid
     *
     * @param array $row
     *
     * @throws \InvalidArgumentException If the given $row is not valid
     * @throws \RuntimeException If the given $row does not contain valid column count
     *
     * @return array
     */
    protected function validateRow(array $row)
    {
        if (self::NULL_HANDLING_DISABLED != $this->null_handling_mode) {
            array_walk($row, function ($value) {
                if (! $this->isConvertibleContent($value)) {
                    throw new InvalidArgumentException('The values are not convertible into strings');
                }
            });
            $row = $this->sanitizeColumnsContent($row);
        }

        if (! $this->isColumnsCountConsistent($row)) {
            throw new RuntimeException('Adding '.count($row).' cells on a {$this->columns_count} cells per row CSV.');
        }

        return $row;
    }

    /**
     * Check if a given value can be added into a CSV cell
     *
     * The value MUST respect the null handling mode
     * The value MUST be convertible into a string
     *
     * @param string|null $value the value to be added
     *
     * @return bool
     */
    protected function isConvertibleContent($value)
    {
        return (is_null($value) && self::NULL_AS_EXCEPTION != $this->null_handling_mode)
            || self::isValidString($value);
    }

    /**
     * Format the row according to the null handling behavior
     *
     * @param array $row
     *
     * @return array
     */
    protected function sanitizeColumnsContent(array $row)
    {
        if (self::NULL_AS_EMPTY == $this->null_handling_mode) {
            return str_replace(null, '', $row);
        } elseif (self::NULL_AS_SKIP_CELL == $this->null_handling_mode) {
            return array_filter($row, function ($value) {
                return !is_null($value);
            });
        }

        return $row;
    }

    /**
     * Check column count consistency
     *
     * @param array $row the row to be added to the CSV
     *
     * @return bool
     */
    protected function isColumnsCountConsistent(array $row)
    {
        if ($this->detect_columns_count) {
            $this->columns_count = count($row);
            $this->detect_columns_count = false;

            return true;
        } elseif (-1 == $this->columns_count) {
            return true;
        }

        return count($row) == $this->columns_count;
    }

    /**
     * set the csv container as a SplFileObject instance
     * insure we use the same object for insertion to
     * avoid loosing the cursor position
     *
     * @return \SplFileObject
     */
    protected function getCsv()
    {
        if (is_null($this->csv)) {
            $this->csv = $this->getIterator();
        }

        return $this->csv;
    }

    /**
     *  {@inheritdoc}
     */
    public function isActiveStreamFilter()
    {
        return parent::isActiveStreamFilter() && is_null($this->csv);
    }

    /**
     * The destructor
     */
    public function __destruct()
    {
        $this->csv = null;
        parent::__destruct();
    }
}
