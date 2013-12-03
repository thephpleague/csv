<?php
/**
* Bakame.csv - A lightweight CSV Coder/Decoder library
*
* @author Ignace Nyamagana Butera <nyamsprod@gmail.com>
* @copyright 2013 Ignace Nyamagana Butera
* @link https://github.com/nyamsprod/Bakame.csv
* @license http://opensource.org/licenses/MIT
* @version 1.0.0
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

use SplFileInfo;
use SplFileObject;
use SplTempFileObject;
use Traversable;
use InvalidArgumentException;
use RuntimeException;

/**
 *  A simple Coder/Decoder to ease CSV management in PHP 5.4+
 *
 * @package Bakame.csv
 */
class CsvCodec
{
    /**
     * the field delimiter (one character only)
     *
     * @var string
     */
    private $delimiter = ',';

    /**
     * the field enclosure character (one character only)
     *
     * @var string
     */
    private $enclosure = '"';

    /**
     * the field escape character (one character only)
     * @var string
     */
    private $escape = '\\';

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
     * return the current field delimiter
     *
     * @return string
     */
    public function getDelimiter()
    {
        return $this->delimiter;
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
     * return the current field escape character
     *
     * @return string
     */
    public function getEscape()
    {
        return $this->escape;
    }

    /**
     * The constructor
     *
     * @param string $delimiter
     * @param string $enclosure
     * @param string $escape
     */
    public function __construct($delimiter = ',', $enclosure = '"', $escape = "\\")
    {
        $this->setDelimiter($delimiter);
        $this->setEnclosure($enclosure);
        $this->setEscape($escape);
    }

    /**
     * Load a CSV string
     *
     * @param string $str the csv content string
     *
     * @return \SplTempFileObject
     */
    public function loadString($str)
    {
        $file = new SplTempFileObject();
        $file->fwrite($str);
        $file->setFlags(SplFileObject::READ_CSV);
        $file->setCsvControl($this->delimiter, $this->enclosure, $this->escape);

        return $file;
    }

    /**
     * Load a CSV File
     *
     * @param string $str the file path
     *
     * @return \SplFileObject
     *
     * @throws \RuntimeException if the $file can not be instantiate
     */
    public function loadFile($path, $mode = 'r')
    {
        return $this->create($path, $mode, ['r', 'r+', 'w+', 'x+', 'a+', 'c+']);
    }

    /**
     * Return a new \SplFileObject
     *
     * @param string|\SplFileInfo $path    where to save the data
     * @param string              $mode    specifies the type of access you require to the file
     * @param array               $include non valid type of access
     *
     * @return \SplFileObject
     *
     * @throws \InvalidArgumentException If the $file is not set
     */
    public function create($path, $mode, array $include = [])
    {
        $include += ['r', 'r+', 'w', 'w+', 'x', 'x+', 'a', 'a+', 'c', 'c+'];
        $mode = $this->filterMode($mode, $include);
        if ($path instanceof SplFileInfo) {
            $file = $path->openFile($mode);
            $file->setFlags(SplFileObject::READ_CSV);
            $file->setCsvControl($this->delimiter, $this->enclosure, $this->escape);

            return $file;
        } elseif (is_string($path)) {
            $file = new SplFileObject($path, $mode);
            $file->setFlags(SplFileObject::READ_CSV);
            $file->setCsvControl($this->delimiter, $this->enclosure, $this->escape);

            return $file;
        }
        throw new InvalidArgumentException('$path must be a `SplFileInfo` object or a valid file path.');
    }

    /**
     * Save the given data into a CSV
     *
     * @param array|\Traversable  $data the data to be saved
     * @param string|\SplFileInfo $path where to save the data
     * @param string              $mode specifies the type of access you require to the file
     *
     * @return \SplFileObject
     *
     * @throws \InvalidArgumentException If $data is not an array or does not implement the \Traversable interface
     * @throws \InvalidArgumentException If the $mode is invalid
     */
    public function save($data, $path, $mode = 'w')
    {
        $file = $this->create($path, $mode, ['r+', 'w', 'w+', 'x', 'x+', 'a', 'a+', 'c', 'c+']);
        if (! is_array($data) && ! $data instanceof Traversable) {
            throw new InvalidArgumentException(
                '$data must be an Array or an object implementing the `Traversable` interface'
            );
        }

        foreach ($data as $row) {
            if (is_string($row)) {
                $row = explode($this->delimiter, $row);
            }
            $row = (array) $row;
            array_walk($row, function (&$value) {
                $value = (string) $value;
                $value = trim($value);
            });
            $file->fputcsv($row);
        }

        return $file;
    }

    /**
     * validate the type of access you require for a given file
     *
     * @param string $mode    specifies the type of access you require to the file
     * @param array  $include valid type of access
     *
     * @return string
     *
     * @throws \InvalidArgumentException If the $mode is invalid
     */
    private function filterMode($mode, array $include)
    {
        $mode = strtolower($mode);
        if (! in_array($mode, $include)) {
            throw new InvalidArgumentException(
                'Invalid `$mode` value. Available values are : "'.implode('", "', $include).'"'
            );
        }

        return $mode;
    }
}
