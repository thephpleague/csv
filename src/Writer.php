<?php
/**
* League.csv - A CSV data manipulation library
*
* @author Ignace Nyamagana Butera <nyamsprod@gmail.com>
* @copyright 2014 Ignace Nyamagana Butera
* @link https://github.com/thephpleague/csv/
* @license http://opensource.org/licenses/MIT
* @version 5.4.0
* @package League.csv
*
* MIT LICENSE
*
* Permission is hereby granted, free of charge, to any person obtaining
* a copy of this software and associated documentation files (the
* "Software"), to deal in the Software without restriction, including
* without limitation the rights to use, copy, modify, merge, publish,
* distribute, sublicense, and/or sell copies of the Software, and to
* permit persons to whom the Software is furnished to do so, subject to
* the following conditions:
*
* The above copyright notice and this permission notice shall be
* included in all copies or substantial portions of the Software.
*
* THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
* EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
* MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
* NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
* LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
* OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
* WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/
namespace League\Csv;

use Traversable;

use InvalidArgumentException;
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
     * Is the submitted row valid
     *
     * @param mixed $row
     *
     * @return array
     *
     * @throws InvalidArgumentException If the given $row is invalid
     */
    private function validateRow($row)
    {
        if (self::isValidString($row)) {
            $row = str_getcsv((string) $row, $this->delimiter, $this->enclosure, $this->escape);
        }
        if (! is_array($row)) {
            throw new InvalidArgumentException(
                'the data provided must be convertible into an array'
            );
        }
        $check = array_filter($row, function ($value) {
            return (is_null($value) && self::NULL_AS_EXCEPTION != $this->null_handling_mode)
            || self::isValidString($value);
        });

        if (count($check) != count($row)) {
            throw new InvalidArgumentException(
                'the converted array must contain only data that can be converted into string'
            );
        }

        $row = $this->formatRow($row);
        if ($this->detect_columns_count) {
            $this->columns_count = count($row);
            $this->detect_columns_count = false;
        }

        if (-1 != $this->columns_count && count($row) != $this->columns_count) {
            throw new InvalidArgumentException(
                'You are trying to add '.count($row).' columns to a CSV
                that requires '.$this->columns_count.' columns.'
            );
        }

        return $row;
    }

    /**
     * Format the row according to the null handling behavior
     *
     * @param array $row
     *
     * @return array
     */
    private function formatRow(array $row)
    {
        if (self::NULL_AS_EXCEPTION == $this->null_handling_mode) {
            return $row;
        } elseif (self::NULL_AS_EMPTY == $this->null_handling_mode) {
            foreach ($row as &$value) {
                if (is_null($value)) {
                    $value = '';
                }
            }
            unset($value);

            return $row;
        }

        return array_filter($row, function ($value) {
            return !is_null($value);
        });
    }

    /**
     * Add a new CSV row to the generated CSV
     *
     * @param mixed $row a string, an array or an object implementing to '__toString' method
     *
     * @return self
     *
     * @throws \InvalidArgumentException If the given row is invalid
     */
    public function insertOne($row)
    {
        $this->csv->fputcsv($this->validateRow($row), $this->delimiter, $this->enclosure);

        return $this;
    }

    /**
     * Add multiple lines to the CSV your are generating
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
     * Instantiate a {@link Reader} class from the current {@link Writer}
     *
     * @param string $open_mode the file open mode flag
     *
     * @return \League\Csv\Reader
     */
    public function getReader($open_mode = 'r+')
    {
        return $this->getInstance('\League\Csv\Reader', $open_mode);
    }
}
