<?php
/**
* Bakame.csv - A lightweight CSV Coder/Decoder library
*
* @author Ignace Nyamagana Butera <nyamsprod@gmail.com>
* @copyright 2014 Ignace Nyamagana Butera
* @link https://github.com/nyamsprod/Bakame.csv
* @license http://opensource.org/licenses/MIT
* @version 4.2.1
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

use InvalidArgumentException;
use Traversable;

/**
 *  A class to manage data insertion into a CSV
 *
 * @package Bakame.csv
 * @since  4.0.0
 *
 */
class Writer extends AbstractCsv
{
    /**
     * {@inheritdoc}
     */
    protected $available_open_mode = ['r+', 'w', 'w+', 'x', 'x+', 'a', 'a+', 'c', 'c+'];

    /**
     * {@inheritdoc}
     */
    public function __construct($path, $open_mode = 'w')
    {
        parent::__construct($path, $open_mode);
    }

    /**
     * Add a new CSV row to the generated CSV
     *
     * @param mixed $row a string, an array or an object implementing to '__toString' method
     *
     * @return self
     *
     * @throws InvalidArgumentException If the given row format is invalid
     */
    public function insertOne($row)
    {
        if (self::isValidString($row)) {
            $row = str_getcsv((string) $row, $this->delimiter, $this->enclosure, $this->escape);
        }
        if (! is_array($row)) {
            throw new InvalidArgumentException(
                'the row provided must be an array of a valid string that can be converted into an array'
            );
        }
        $check = array_filter($row, function ($value) {
            return self::isValidString($value);
        });
        if (count($check) == count($row)) {
            $this->csv->fputcsv($row, $this->delimiter, $this->enclosure);

            return $this;
        }
        throw new InvalidArgumentException(
            'the provided data can not be transform into a single CSV data row'
        );
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
     * @return \Bakame\Csv\Reader
     */
    public function getReader()
    {
        $csv = new Reader($this->csv);
        $csv->setDelimiter($this->delimiter);
        $csv->setEnclosure($this->enclosure);
        $csv->setEscape($this->escape);
        $csv->setFlags($this->flags);
        $csv->setEncoding($this->encoding);

        return $csv;
    }
}
