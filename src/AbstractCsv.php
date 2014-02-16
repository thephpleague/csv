<?php
/**
* Bakame.csv - A lightweight CSV Coder/Decoder library
*
* @author Ignace Nyamagana Butera <nyamsprod@gmail.com>
* @copyright 2014 Ignace Nyamagana Butera
* @link https://github.com/nyamsprod/Bakame.csv
* @license http://opensource.org/licenses/MIT
* @version 4.0.0
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

use IteratorAggregate;
use DomDocument;
use JsonSerializable;
use RuntimeException;
use SplFileInfo;
use SplFileObject;
use SplTempFileObject;
use InvalidArgumentException;
use Bakame\Csv\Iterator\MapIterator;

/**
 *  A abstract class to enable basic CSV manipulation
 *
 * @package Bakame.csv
 * @since  4.0.0
 *
 */
class AbstractCsv implements JsonSerializable, IteratorAggregate
{
    /**
     * The CSV object holder
     *
     * @var \SplFileObject
     */
    protected $csv;

    /**
     * the field delimiter (one character only)
     *
     * @var string
     */
    protected $delimiter = ',';

    /**
     * the field enclosure character (one character only)
     *
     * @var string
     */
    protected $enclosure = '"';

    /**
     * the field escape character (one character only)
     *
     * @var string
     */
    protected $escape = '\\';

    /**
     * the \SplFileObject flags holder
     *
     * @var integer
     */
    protected $flags = SplFileObject::READ_CSV;

    /**
     * Charset Encoding for the CSV
     *
     * @var string
     */
    protected $encoding = 'UTF-8';

    /**
     * Open mode available flag
     *
     * @var array
     */
    protected $available_open_mode = ['r', 'r+', 'w', 'w+', 'x', 'x+', 'a', 'a+', 'c', 'c+'];

    /**
     * The constructor
     *
     * @param mixed  $path      an SplFileInfo object or the path to a file
     * @param string $open_mode the file open mode flag
     */
    public function __construct($path, $open_mode = 'r')
    {
        $this->csv = $this->fetchFile($path, $open_mode);
        $this->csv->setFlags($this->flags);
    }

    /**
     * The destructor
     *
     * Make sure the class reference is destroy when the class is no longer used
     */
    public function __destruct()
    {
        $this->csv = null;
    }

    /**
     * Create a {@link AbstractCsv} from a string
     *
     * @param string $str The CSV data as string
     *
     * @return self
     *
     * @throws \InvalidArgumentException If the data provided is invalid
     */
    public static function createFromString($str)
    {
        if (self::isValidString($str)) {
            $csv = new SplTempFileObject;
            $raw = (string) $str;
            $raw .= PHP_EOL;
            $csv->fwrite($raw);

            return new static($csv);
        }
        throw new InvalidArgumentException(
            'the submitted data must be a string or an object implementing the `__toString` method'
        );
    }

    /**
    * Validate a variable to be stringable
    *
    * @param mixed $str
    *
    * @return boolean
    */
    protected static function isValidString($str)
    {
        return (is_scalar($str) || (is_object($str) && method_exists($str, '__toString')));
    }

    /**
     * Return a new {@link SplFileObject}
     *
     * @param mixed $path A SplFileInfo object or the path to a file
     *
     * @return \SplFileObject
     *
     * @throws \InvalidArgumentException If the $file is not set
     * @throws \RuntimeException         If the $file could not be created and/or opened
     */
    protected function fetchFile($path, $open_mode)
    {
        if ($path instanceof SplTempFileObject) {
            return $path;
        }
        $open_mode = strtolower($open_mode);
        if (! in_array($open_mode, $this->available_open_mode)) {
            throw new InvalidArgumentException(
                'Invalid `$open_mode` value. Available values are : "'
                .implode('", "', $this->available_open_mode).'"'
            );
        }

        if ($path instanceof SplFileInfo) {
            return $path->openFile($open_mode);
        } elseif (is_string($path)) {
            return new SplFileObject($path, $open_mode);
        }
        throw new InvalidArgumentException(
            '$path must be a `SplFileInfo` object or a valid file path.'
        );
    }

    /**
     * set the field delimeter
     *
     * @param string $delimiter
     *
     * @return self
     *
     * @throws \InvalidArgumentException If $delimeter is not a single character
     */
    public function setDelimiter($delimiter = ',')
    {
        if (1 != mb_strlen($delimiter)) {
            throw new InvalidArgumentException('The delimiter must be a single character');
        }
        $this->delimiter = $delimiter;

        return $this;
    }

    /**
     * return the current field delimiter
     *
     * @return string
     */
    public function getDelimiter()
    {
        return $this->delimiter;
    }

    /**
     * set the field enclosure
     *
     * @param string $enclosure
     *
     * @return self
     *
     * @throws \InvalidArgumentException If $enclosure is not a single character
     */
    public function setEnclosure($enclosure = '"')
    {
        if (1 != mb_strlen($enclosure)) {
            throw new InvalidArgumentException('The enclosure must be a single character');
        }
        $this->enclosure = $enclosure;

        return $this;
    }

    /**
     * return the current field enclosure
     *
     * @return string
     */
    public function getEnclosure()
    {
        return $this->enclosure;
    }

    /**
     * set the field escape character
     *
     * @param string $escape
     *
     * @return self
     *
     * @throws \InvalidArgumentException If $escape is not a single character
     */
    public function setEscape($escape = "\\")
    {
        if (1 != mb_strlen($escape)) {
            throw new InvalidArgumentException('The escape character must be a single character');
        }
        $this->escape = $escape;

        return $this;
    }

    /**
     * return the current field escape character
     *
     * @return string
     */
    public function getEscape()
    {
        return $this->escape;
    }

    /**
     * Set the Flags associated to the CSV SplFileObject
     *
     * @return self
     */
    public function setFlags($flags)
    {
        if (false === filter_var($flags, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]])) {
            throw new InvalidArgumentException('you should use a `SplFileObject` Constant');
        }

        $this->flags = $flags|SplFileObject::READ_CSV|SplFileObject::DROP_NEW_LINE;

        return $this;
    }

    /**
     * Returns the file Flags
     *
     * @return integer
     */
    public function getFlags()
    {
        return $this->flags;
    }

    /**
     * Set the CSV encoding charset
     *
     * @param string $str
     *
     * @return self
     */
    public function setEncoding($str)
    {
        $str = str_replace('_', '-', $str);
        $str = filter_var($str, FILTER_SANITIZE_STRING, ['flags' => FILTER_FLAG_STRIP_LOW|FILTER_FLAG_STRIP_HIGH]);
        if (empty($str)) {
            throw new InvalidArgumentException('you should use a valid charset');
        }
        $this->encoding = strtoupper($str);

        return $this;
    }

    /**
     * Get the CSV encoding charset
     *
     * @return string
     */
    public function getEncoding()
    {
        return $this->encoding;
    }

    /**
     * Return the CSV Iterator
     *
     * @return \SplFileObject
     */
    public function getIterator()
    {
        $this->csv->setCsvControl($this->delimiter, $this->enclosure, $this->escape);
        $this->csv->setFlags($this->flags);

        return $this->csv;
    }

    /**
     * Output all data on the CSV file
     */
    public function output()
    {
        $iterator = $this->getIterator();
        $iterator->rewind();
        $iterator->fpassthru();
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
    public function toHTML($classname = 'table-csv-data')
    {
        $doc = new DomDocument('1.0', $this->encoding);
        $table = $doc->createElement('table');
        $table->setAttribute('class', $classname);
        foreach ($this->getIterator() as $row) {
            $tr = $doc->createElement('tr');
            foreach ($row as $value) {
                $tr->appendChild($doc->createElement('td', htmlspecialchars($value, ENT_COMPAT, $this->encoding)));
            }
            $table->appendChild($tr);
        }

        return $doc->saveHTML($table);
    }

    /**
     * JsonSerializable Interface
     *
     * @return array
     */
    public function jsonSerialize()
    {
        $iterator = $this->getIterator();
        if ('UTF-8' != $this->encoding) {
            $iterator = new MapIterator($iterator, function ($row) {
                foreach ($row as &$value) {
                    $value = mb_convert_encoding($value, 'UTF-8', $this->encoding);
                }
                unset($value);

                return $row;
            });
        }

        return iterator_to_array($iterator, false);
    }
}
