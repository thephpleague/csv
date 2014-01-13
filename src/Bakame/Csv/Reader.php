<?php
/*
* Bakame.csv - A lightweight CSV Coder/Decoder library
*
* @author Ignace Nyamagana Butera <nyamsprod@gmail.com>
* @copyright 2013 Ignace Nyamagana Butera
* @link https://github.com/nyamsprod/Bakame.csv
* @license http://opensource.org/licenses/MIT
* @version 3.0.1
* @package Bakame.csv
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
namespace Bakame\Csv;

use SplFileObject;
use InvalidArgumentException;

/**
 *  A Reader to ease CSV parsing in PHP 5.4+
 *
 * @package Bakame.csv
 * @since  3.0.0
 *
 */
class Reader implements ReaderInterface
{
    use CsvControlsTrait;

    /**
     * The CSV file Object
     *
     * @var SplFileObject
     */
    private $file;

    /**
     * The constructor
     *
     * @param SplFileObject $file      The CSV file Object
     * @param string        $delimiter Optional CSV file delimiter character
     * @param string        $enclosure Optional CSV file enclosure character
     * @param string        $escape    Optional CSV file escape character
     */
    public function __construct(SplFileObject $file, $delimiter = ',', $enclosure = '"', $escape = "\\")
    {
        $this->setDelimiter($delimiter);
        $this->setEnclosure($enclosure);
        $this->setEscape($escape);
        $this->file = $file;
        $this->file->setCsvControl($this->delimiter, $this->enclosure, $this->escape);
        $this->setFlags(SplFileObject::READ_CSV|SplFileObject::DROP_NEW_LINE);
    }

    /**
     * Return the current associated file
     *
     * @return SplFileObject
     */
    public function getFile()
    {
        return $this->file;
    }

    /**
     * Set the Flags associated to the CSV SplFileObject
     *
     * @return self
     */
    public function setFlags($flags)
    {
        if (! self::isValidInteger($flags)) {
            throw new InvalidArgumentException('you should use a `SplFileObject` Constant');
        }
        $this->file->setFlags($flags|SplFileObject::READ_CSV|SplFileObject::DROP_NEW_LINE);

        return $this;
    }

    /**
     * Returns the file Flags
     *
     * @return integer
     */
    public function getFlags()
    {
        return $this->file->getFlags();
    }

    /**
     * Validate a variable to be a positive integer or 0
     * @param integer $rowIndex
     *
     * @return boolean
     */
    private static function isValidInteger($rowIndex)
    {
        $rowIndex = filter_var($rowIndex, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);

        return false !== $rowIndex;
    }

    /**
     * Intelligent Array Combine
     *
     * @param array $keys
     * @param array $value
     *
     * @return array
     */
    private static function combineKeyValue(array $keys, array $value)
    {
        $nbKeys = count($keys);
        $diff = $nbKeys - count($value);
        if ($diff > 0) {
            $value = array_merge($value, array_fill(0, $diff, null));
        } elseif ($diff < 0) {
            $value = array_slice($value, 0, $nbKeys);
        }

        return array_combine($keys, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchOne($rowIndex)
    {
        if (! self::isValidInteger($rowIndex)) {
            throw new InvalidArgumentException('the row index must be a positive integer or 0');
        }
        $this->file->setCsvControl($this->delimiter, $this->enclosure, $this->escape);
        $this->file->seek($rowIndex);
        $res = $this->file->fgetcsv();
        if (is_null($res)) {
            return [];
        }

        return $res;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchValue($rowIndex, $columnIndex)
    {
        if (! self::isValidInteger($columnIndex)) {
            throw new InvalidArgumentException('the column index must be a positive integer or 0');
        }
        $res = $this->fetchOne($rowIndex);
        if (! array_key_exists($columnIndex, $res)) {
            return null;
        }

        return $res[$columnIndex];
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAll(callable $callable = null)
    {
        $res = [];
        $this->file->rewind();
        $this->file->setCsvControl($this->delimiter, $this->enclosure, $this->escape);
        if (is_null($callable)) {
            foreach ($this->file as $row) {
                $res[] = $row;
            }

            return $res;
        }
        foreach ($this->file as $row) {
            $res[] = $callable($row);
        }

        return $res;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAssoc(array $keys, callable $callable = null)
    {
        $nbKeys = count($keys);
        $keys = array_filter($keys, function ($value) {
            return is_scalar($value);
        });
        $keys = array_unique($keys);
        if (count($keys) != $nbKeys) {
            throw new InvalidArgumentException('The named keys should be unique strings');
        }

        $res = [];
        $this->file->rewind();
        $this->file->setCsvControl($this->delimiter, $this->enclosure, $this->escape);
        if (is_null($callable)) {
            foreach ($this->file as $row) {
                $res[] = self::combineKeyValue($keys, $row);
            }

            return $res;
        }
        foreach ($this->file as $row) {
            $res[] = self::combineKeyValue($keys, $callable($row));
        }

        return $res;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchCol($columnIndex, callable $callable = null)
    {
        if (! self::isValidInteger($columnIndex)) {
            throw new InvalidArgumentException('the column index must be a positive integer or 0');
        }
        $res = [];
        $this->file->rewind();
        $this->file->setCsvControl($this->delimiter, $this->enclosure, $this->escape);
        if (is_null($callable)) {
            foreach ($this->file as $row) {
                $value = null;
                if (array_key_exists($columnIndex, $row)) {
                    $value = $row[$columnIndex];
                }
                $res[] = $value;
            }

            return $res;
        }
        foreach ($this->file as $row) {
            $value = null;
            if (array_key_exists($columnIndex, $row)) {
                $value = $callable($row[$columnIndex]);
            }
            $res[] = $value;
        }

        return $res;
    }

    /**
     * Output all data on the CSV file
     */
    public function output()
    {
        $this->file->rewind();
        $this->file->fpassthru();
    }

    /**
     * Retrieves the CSV content
     *
     * @return string
     */
    public function __toString()
    {
        ob_start();
        $this->output();

        return ob_get_clean();
    }
}
