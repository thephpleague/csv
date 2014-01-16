<?php
/*
* Bakame.csv - A lightweight CSV Coder/Decoder library
*
* @author Ignace Nyamagana Butera <nyamsprod@gmail.com>
* @copyright 2013 Ignace Nyamagana Butera
* @link https://github.com/nyamsprod/Bakame.csv
* @license http://opensource.org/licenses/MIT
* @version 3.2.0
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

use ArrayAccess;
use CallbackFilterIterator;
use DomDocument;
use InvalidArgumentException;
use JsonSerializable;
use RuntimeException;
use SplFileObject;

/**
 *  A Reader to ease CSV parsing in PHP 5.4+
 *
 * @package Bakame.csv
 * @since  3.0.0
 *
 */
class Reader extends AbstractIteratorFilter implements ArrayAccess, JsonSerializable
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
     * @param integer       $flags     Optional SplFileObject constant flags
     */
    public function __construct(SplFileObject $file, $delimiter = ',', $enclosure = '"', $escape = "\\", $flags = 0)
    {
        $this->setDelimiter($delimiter);
        $this->setEnclosure($enclosure);
        $this->setEscape($escape);
        $this->setFlags($flags);
        $this->file = $file;
        $this->file->setCsvControl($this->delimiter, $this->enclosure, $this->escape);
        $this->file->setFlags($this->flags);
    }

    /**
    * Validate a variable to be a positive integer or 0
    *
    * @param integer $rowIndex
    *
    * @return boolean
    */
    private static function isValidInteger($value)
    {
        return false !== filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
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

    /**
     * Return a HTML table representation of the CSV Table
     *
     * @param string $classname optional classname
     *
     * @return string
     */
    public function toHTML($classname = 'csv-data')
    {
        $doc = new DomDocument('1.0');
        $table = $doc->createElement('table');
        $table->setAttribute('class', $classname);
        foreach ($this->file as $row) {
            $tr = $doc->createElement('tr');
            foreach ($row as $value) {
                $tr->appendChild($doc->createElement('td', $value));
            }
            $table->appendChild($tr);
        }

        return $doc->saveHTML($table);
    }

    /**
     * Json Serializable
     */

    public function jsonSerialize()
    {
        return iterator_to_array($this->file);
    }

    /**
     *  Array Access
     */

    public function offsetGet($offset)
    {
        if (! self::isValidInteger($offset)) {
            throw new InvalidArgumentException('the row index must be a positive integer or 0');
        }
        $this->file->setCsvControl($this->delimiter, $this->enclosure, $this->escape);
        $this->file->setFlags($this->flags);
        $this->file->seek($offset);
        $res = $this->file->fgetcsv();
        if (! is_array($res)) {
            return [];
        }

        return $res;
    }

    public function offsetExists($offset)
    {
        return (bool) count($this->offsetGet($offset));
    }

    public function offsetSet($offset, $value)
    {
        throw new RuntimeException(__CLASS__ . ' can not modify the CSV data');
    }

    public function offsetUnset($offset)
    {
        throw new RuntimeException(__CLASS__ . ' can not modify the CSV data');
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release
     *
     * @deprecated  deprecated since version 3.2
     */
    public function fetchOne($rowIndex)
    {
        return $this->offsetGet($rowIndex);
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release
     *
     * @deprecated  deprecated since version 3.2
     */
    public function fetchValue($rowIndex, $columnIndex)
    {
        if (! self::isValidInteger($columnIndex)) {
            throw new InvalidArgumentException('the column index must be a positive integer or 0');
        }
        $res = $this->offsetGet($rowIndex);
        if (! array_key_exists($columnIndex, $res)) {
            return null;
        }

        return $res[$columnIndex];
    }

    /**
     * {@inheritdoc}
     */
    protected function prepare()
    {
        $this->file->setCsvControl($this->delimiter, $this->enclosure, $this->escape);
        $this->file->setFlags($this->flags);

        return new CallbackFilterIterator($this->file, function ($row) {
            return is_array($row);
        });
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
    public function fetchAll(callable $callable = null)
    {
        $res = [];
        $iterator = $this->query();
        if (is_null($callable)) {
            foreach ($iterator as $row) {
                $res[] = $row;
            }

            return $res;
        }

        foreach ($iterator as $row) {
            $res[] = $callable($row);
        }

        return $res;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAssoc(array $keys, callable $callable = null)
    {
        $validKeys = array_unique(array_filter($keys, function ($value) {
            return is_string($value) || is_integer($value);
        }));

        if ($keys !== $validKeys) {
            throw new InvalidArgumentException('The named keys should be unique strings Or integer');
        }

        $res = [];
        $iterator = $this->query();
        if (is_null($callable)) {
            foreach ($iterator as $row) {
                $res[] = self::combineKeyValue($keys, $row);
            }

            return $res;
        }

        foreach ($iterator as $row) {
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

        $iterator = $this->query();
        if (is_null($callable)) {
            foreach ($iterator as $row) {
                $res[] = array_key_exists($columnIndex, $row) ? $row[$columnIndex] : null;
            }

            return $res;
        }

        foreach ($iterator as $row) {
            $res[] = array_key_exists($columnIndex, $row) ? $callable($row[$columnIndex]) : null;
        }

        return $res;
    }
}
