<?php
/**
* Bakame.csv - A lightweight CSV Coder/Decoder library
*
* @author Ignace Nyamagana Butera <nyamsprod@gmail.com>
* @copyright 2014 Ignace Nyamagana Butera
* @link https://github.com/nyamsprod/Bakame.csv
* @license http://opensource.org/licenses/MIT
* @version 3.3.0
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
use InvalidArgumentException;
use IteratorAggregate;
use JsonSerializable;
use LimitIterator;
use RuntimeException;
use SplFileObject;
use SplTempFileObject;
use Bakame\Csv\Iterator\AbstractIteratorFilter;
use Bakame\Csv\Iterator\MapIterator;
use Bakame\Csv\Traits\CsvControls;
use Bakame\Csv\Traits\CsvOutput;

/**
 *  A Reader to ease CSV parsing in PHP 5.4+
 *
 * @package Bakame.csv
 * @since  3.0.0
 *
 */
class Reader extends AbstractIteratorFilter implements
    IteratorAggregate,
    ArrayAccess,
    JsonSerializable
{
    use CsvControls;
    use CsvOutput;

    /**
     * The CSV file Object
     *
     * @var \SplFileObject
     */
    private $csv;

    /**
     * The constructor
     *
     * @param \SplFileObject $file      The CSV file Object
     * @param string         $delimiter Optional CSV file delimiter character
     * @param string         $enclosure Optional CSV file enclosure character
     * @param string         $escape    Optional CSV file escape character
     * @param integer        $flags     Optional \SplFileObject constant flags
     */
    public function __construct(SplFileObject $file, $delimiter = ',', $enclosure = '"', $escape = "\\", $flags = 0)
    {
        $this->setDelimiter($delimiter);
        $this->setEnclosure($enclosure);
        $this->setEscape($escape);
        $this->setFlags($flags);
        $this->csv = $file;
        $this->csv->setCsvControl($this->delimiter, $this->enclosure, $this->escape);
        $this->csv->setFlags($this->flags);
    }

    /**
     * Create a \Bakame\Csv\Reader from a string
     *
     * @param string  $str       The CSV data as string
     * @param string  $delimiter Optional CSV file delimiter character
     * @param string  $enclosure Optional CSV file enclosure character
     * @param string  $escape    Optional CSV file escape character
     * @param integer $flags     Optional \SplFileObject constant flags
     *
     * @return self
     *
     * @throws \InvalidArgumentException If the data provided is invalid
     */
    public static function createFromString($str, $delimiter = ',', $enclosure = '"', $escape = "\\", $flags = 0)
    {
        if (is_string($str) || (is_object($str) && method_exists($str, '__toString'))) {
            $csv = new SplTempFileObject;
            $csv->fwrite((string) $str);

            return new static($csv, $delimiter, $enclosure, $escape, $flags);
        }
        throw new InvalidArgumentException(
            'the submitted data must be a string or an object implementing the `__toString` method'
        );
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
     *  \IteratorAggregate Interface
     */

    public function getIterator()
    {
        return $this->csv;
    }

    /**
     *  \ArrayAccess Interface
     */

    public function offsetGet($offset)
    {
        if (! self::isValidInteger($offset)) {
            throw new InvalidArgumentException('the row index must be a positive integer or 0');
        }
        $iterator = $this->prepare();
        $iterator = new LimitIterator($iterator, $offset, 1);
        $iterator->rewind();
        $res = $iterator->getInnerIterator()->current();
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
     * @deprecated  deprecated since version 3.3
     *
     * @return \SplFileObject
     */
    public function getFile()
    {
        return $this->csv;
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
        $this->csv->setCsvControl($this->delimiter, $this->enclosure, $this->escape);
        $this->csv->setFlags($this->flags);

        return new CallbackFilterIterator($this->csv, function ($row) {
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
    private static function combineArray(array $keys, array $value)
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
     * Return a sequential array of all CSV lines
     *
     * @param callable $callable a callable function to be applied to each row to be return
     *
     * @return array
     */
    public function fetchAll(callable $callable = null)
    {
        $res = [];
        foreach ($this->query($callable) as $row) {
            $res[] = $row;
        }

        return $res;
    }

    /**
     * Return a sequential array of all CSV lines; the rows are presented as associated arrays
     *
     * @param array    $keys     the name for each key member
     * @param callable $callable a callable function to be applied to each row to be return
     *
     * @return array
     *
     * @throws \InvalidArgumentException If the submitted keys are not integer or strng
     */
    public function fetchAssoc(array $keys, callable $callable = null)
    {
        $validKeys = array_unique(array_filter($keys, function ($value) {
            return is_scalar($value) || (is_object($value) && method_exists($value, '__toString'));
        }));

        if ($keys !== $validKeys) {
            throw new InvalidArgumentException('The named keys should be unique strings Or integer');
        }

        $res = [];
        foreach ($this->query($callable) as $row) {
            $res[] = self::combineArray($keys, $row);
        }

        return $res;
    }

    /**
     * Return a single column from the CSV data
     *
     * @param integer  $fieldIndex field Index
     * @param callable $callable   a callable function to be applied to each value to be return
     * @param boolean  $strict     if true will remove undefined column from result set
     *
     * @return array
     *
     * @throws \InvalidArgumentException If the column index is not a positive integer or 0
     */
    public function fetchCol($columnIndex, callable $callable = null, $strict = false)
    {
        if (! self::isValidInteger($columnIndex)) {
            throw new InvalidArgumentException('the column index must be a positive integer or 0');
        }

        $iterator = $this->query($callable);
        if ($strict) {
            $iterator = new CallbackFilterIterator($iterator, function ($row) use ($columnIndex) {
                return array_key_exists($columnIndex, $row);
            });
        } else {
            $iterator = new MapIterator($iterator, function ($row) use ($columnIndex) {
                if (! array_key_exists($columnIndex, $row)) {
                    $row[$columnIndex] = null;
                }

                return $row;
            });
        }

        $res = [];
        foreach ($iterator as $row) {
            $res[] = $row[$columnIndex];
        }

        return $res;
    }
}
