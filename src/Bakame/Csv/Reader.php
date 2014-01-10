<?php
/*
* Bakame.csv - A lightweight CSV Coder/Decoder library
*
* @author Ignace Nyamagana Butera <nyamsprod@gmail.com>
* @copyright 2013 Ignace Nyamagana Butera
* @link https://github.com/nyamsprod/Bakame.csv
* @license http://opensource.org/licenses/MIT
* @version 2.1.0
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
 */
class Reader implements ReaderInterface
{
    use CsvControlsTrait;

    private $file;

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
        $flags = filter_var($flags, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (false === $flags) {
            throw new InvalidArgumentException('you should use SplFileObject Constant');
        }
        $this->file->setFlags(SplFileObject::READ_CSV|SplFileObject::DROP_NEW_LINE|$flags);

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
     * {@inheritdoc}
     */
    public function fetchOne($rowIndex)
    {
        $rowIndex = filter_var($rowIndex, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if (false === $rowIndex) {
            throw new InvalidArgumentException('the index can not be negative');
        }
        $this->file->seek($rowIndex);

        return $this->file->fgetcsv($this->delimiter, $this->enclosure, $this->escape);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchValue($rowIndex, $fieldIndex)
    {
        $res = $this->fetchOne($rowIndex);
        if (is_null($res) || ! array_key_exists($fieldIndex, $res)) {
            return null;
        }

        return $res[$fieldIndex];
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAll(callable $callable = null)
    {
        $res = [];
        $this->file->rewind();
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
        $res = [];
        $this->file->rewind();
        if (is_null($callable)) {
            foreach ($this->file as $row) {
                $res[] = array_combine($keys, $row);
            }

            return $res;
        }
        foreach ($this->file as $row) {
            $res[] = array_combine($keys, $callable($row));
        }

        return $res;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchCol($fieldIndex, callable $callable = null)
    {
        $res = [];
        $this->file->rewind();
        if (is_null($callable)) {
            foreach ($this->file as $row) {
                if (array_key_exists($fieldIndex, $row)) {
                    $res[] = $row[$fieldIndex];
                }
            }

            return $res;
        }
        foreach ($this->file as $row) {
            if (array_key_exists($fieldIndex, $row)) {
                $res[] = $callable($row[$fieldIndex]);
            }
        }

        return $res;
    }
}
