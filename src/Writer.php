<?php
/**
* This file is part of the League.csv library
*
* @license http://opensource.org/licenses/MIT
* @link https://github.com/thephpleague/csv/
* @version 5.5.0
* @package League.csv
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
namespace League\Csv;

use Traversable;
use SplFileObject;
use InvalidArgumentException;
use RuntimeException;
use OutOfBoundsException;

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
     * the object current null handling mode
     *
     * @var integer
     */
    protected $null_handling_mode = self::NULL_AS_EXCEPTION;

    /**
     * The number of column per row
     *
     * @var integer
     */
    protected $columns_count = -1;

    /**
     * should the class detect the column count based the inserted row
     *
     * @var boolean
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
     * The destructor
     */
    public function __destruct()
    {
        $this->csv = null;
        parent::__destruct();
    }

    /**
     * Tell the class how to handle null value
     *
     * @param integer $value a Writer null behavior constant
     *
     * @return self
     *
     * @throws OutOfBoundsException If the Integer is not valid
     */
    public function setNullHandlingMode($value)
    {
        if (!in_array($value, [self::NULL_AS_SKIP_CELL, self::NULL_AS_EXCEPTION, self::NULL_AS_EMPTY])) {
            throw new OutOfBoundsException('invalid value for null handling');
        }
        $this->null_handling_mode = $value;

        return $this;
    }

    /**
     * null handling getter
     *
     * @return integer
     */
    public function getNullHandlingMode()
    {
        return $this->null_handling_mode;
    }

    /**
     * Format the row according to the null handling behavior
     *
     * @param array $row
     *
     * @return array
     */
    private function sanitizeColumnsContent(array $row)
    {
        if (self::NULL_AS_EXCEPTION == $this->null_handling_mode) {
            return $row;
        } elseif (self::NULL_AS_EMPTY == $this->null_handling_mode) {
            array_walk($row, function (&$value) {
                if (is_null($value)) {
                    $value = '';
                }
            });

            return $row;
        }

        return array_filter($row, function ($value) {
            return !is_null($value);
        });
    }

    /**
     * Set Inserted row column count
     * @param integer $value
     *
     * @return self
     *
     * @throws \InvalidArgumentException If $value is lesser than -1
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
     * @return integer
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
     * @return self
     */
    public function autodetectColumnsCount()
    {
        $this->detect_columns_count = true;

        return $this;
    }

    /**
     * Check column count consistency
     *
     * @param array $row the row to be added to the CSV
     *
     * @return boolean
     */
    private function isColumnsCountConsistent(array $row)
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
     * Is the submitted row valid
     *
     * @param mixed $row
     *
     * @return array
     *
     * @throws InvalidArgumentException If the given $row is not valid
     */
    private function validateRow($row)
    {
        //convert input string row into a proper array
        if (self::isValidString($row)) {
            $row = str_getcsv((string) $row, $this->delimiter, $this->enclosure, $this->escape);
        }

        if (! is_array($row)) {
            throw new InvalidArgumentException(
                'the data provided must be convertible into an array'
            );
        }

        //validate row according to null handling mode
        $check = array_filter($row, function ($value) {
            return (is_null($value) && self::NULL_AS_EXCEPTION != $this->null_handling_mode)
            || self::isValidString($value);
        });

        if (count($check) != count($row)) {
            throw new InvalidArgumentException(
                'the converted array must contain only data that can be converted into string'
            );
        }

        return $row;
    }

    /**
     * set the csv container as a SplFileObject instance
     * insure we use the same object for insertion to
     * avoid loosing the cursor position
     *
     * @return SplFileObject
     *
     * @throws \RuntimeException If the file could not be created and/or opened
     */
    protected function getCsv()
    {
        if (! is_null($this->csv)) {
            return $this->csv;
        } elseif ($this->path instanceof SplFileObject) {
            $this->csv = $this->path;

            return $this->csv;
        }

        $this->csv = new SplFileObject($this->getStreamFilterPath(), $this->open_mode);

        return $this->csv;
    }

    /**
     * Add a new CSV row to the generated CSV
     *
     * @param mixed $data a string, an array or an object implementing to '__toString' method
     *
     * @return self
     *
     * @throws \InvalidArgumentException If the given row is invalid
     */
    public function insertOne($data)
    {
        $data = $this->validateRow($data);
        $data = $this->sanitizeColumnsContent($data);
        if (! $this->isColumnsCountConsistent($data)) {
            throw new RuntimeException(
                'You are trying to add '.count($data).' columns to a CSV
                that requires '.$this->columns_count.' columns per row.'
            );
        }
        $this->getCsv()->fputcsv($data, $this->delimiter, $this->enclosure);

        return $this;
    }

    /**
     * Add multiple lines to the CSV your are generating
     *
     * a simple helper/Wrapper method around insertOne
     *
     * @param mixed $rows a multidimentional array or a Traversable object
     *
     * @return self
     *
     * @throws \InvalidArgumentException If the given rows format is invalid
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
     * DEPRECATION WARNING! This method will be removed in the next major point release
     *
     * @deprecated deprecated since version 5.5
     *
     * @param string $open_mode the file open mode flag
     *
     * @return \League\Csv\Reader object
     */
    public function getReader($open_mode = 'r+')
    {
        return $this->newReader($open_mode);
    }

    /**
     * Create a {@link Reader} instance from a {@link Writer} object
     *
     * @param string $open_mode the file open mode flag
     *
     * @return \League\Csv\Reader object
     */
    public function newReader($open_mode = 'r+')
    {
        $csv = new Reader($this->path, $open_mode);
        $csv->setDelimiter($this->delimiter);
        $csv->setEnclosure($this->enclosure);
        $csv->setEscape($this->escape);
        $csv->setFlags($this->flags);
        $csv->setEncodingFrom($this->encodingFrom);

        return $csv;
    }
}
