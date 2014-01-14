<?php
/**
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

use SplFileInfo;
use SplFileObject;
use SplTempFileObject;
use Traversable;
use InvalidArgumentException;

/**
 *  A simple Coder/Decoder to ease CSV management in PHP 5.4+
 *
 * @package Bakame.csv
 * @since  2.0
 *
 */
class Codec
{
    use CsvControlsTrait;

    /**
     * The constructor
     *
     * @param string $delimiter
     * @param string $enclosure
     * @param string $escape
     */
    public function __construct($delimiter = ',', $enclosure = '"', $escape = "\\", $flags = 0)
    {
        $this->setDelimiter($delimiter);
        $this->setEnclosure($enclosure);
        $this->setEscape($escape);
        $this->setFlags($flags);
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

        return new Reader($file, $this->delimiter, $this->enclosure, $this->escape, $this->flags);
    }

    /**
     * Load a CSV File
     *
     * @param string $str the file path
     *
     * @return \SplFileObject
     */
    public function loadFile($path, $mode = 'r')
    {
        return new Reader(
            $this->create($path, $mode, ['r', 'r+', 'w+', 'x+', 'a+', 'c+']),
            $this->delimiter,
            $this->enclosure,
            $this->escape,
            $this->flags
        );
    }

    /**
     * Save the given data into a CSV
     *
     * @param array|\Traversable  $data the data to be saved (Array or Traversable Interface)
     * @param string|\SplFileInfo $path where to save the data (String Path or SplFileInfo Instance)
     * @param string              $mode specifies the type of access you require to the file
     *
     * @return \SplFileObject
     */
    public function save($data, $path, $mode = 'w')
    {
        $file = $this->create($path, $mode, ['r+', 'w', 'w+', 'x', 'x+', 'a', 'a+', 'c', 'c+']);
        $data = $this->formatData($data);
        array_walk($data, function ($row) use ($file) {
            $file->fputcsv($row);
        });

        return new Reader($file, $this->delimiter, $this->enclosure, $this->escape, $this->flags);
    }

    /**
     * format the data before inclusion into the CSV
     *
     * @param array|\Traversable $traversable the data to be formatted (Array or Traversable Interface)
     *
     * @return array
     *
     * @throws \InvalidArgumentException If $data is not an array or does not implement the \Traversable interface
     */
    private function formatData($traversable)
    {
        if (! is_array($traversable) && ! $traversable instanceof Traversable) {
            throw new InvalidArgumentException(
                'The provided data must be an Array or an object implementing the `Traversable` interface'
            );
        }
        $res = [];
        foreach ($traversable as $row) {
            $res[] = $this->extractRowData($row);
        }

        return $res;
    }

    /**
     * extract and format row field data to be string
     *
     * @param mixed $row the data for One CSV line
     *
     * @return array
     */
    private function extractRowData($row)
    {
        if (is_array($row)) {
            return array_map(function ($value) {
                return (string) $value;
            }, $row);
        }

        return explode($this->delimiter, (string) $row);
    }

    /**
     * Return a new \SplFileObject
     *
     * @param mixed  $path    where to save the data (String or SplFileInfo Instance)
     * @param string $mode    specifies the type of access you require to the file
     * @param array  $include non valid type of access
     *
     * @return \SplFileObject
     *
     * @throws \InvalidArgumentException If the $file is not set
     */
    private function create($path, $mode, array $include = [])
    {
        $include += ['r', 'r+', 'w', 'w+', 'x', 'x+', 'a', 'a+', 'c', 'c+'];
        $mode = $this->filterMode($mode, $include);
        if ($path instanceof SplFileInfo) {
            $file = $path->openFile($mode);
            $file->setFlags($this->flags);
            $file->setCsvControl($this->delimiter, $this->enclosure, $this->escape);

            return $file;
        } elseif (is_string($path)) {
            $file = new SplFileObject($path, $mode);
            $file->setFlags($this->flags);
            $file->setCsvControl($this->delimiter, $this->enclosure, $this->escape);

            return $file;
        }
        throw new InvalidArgumentException('$path must be a `SplFileInfo` object or a valid file path.');
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
