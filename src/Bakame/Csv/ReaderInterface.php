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

/**
 *
 * Interface of CSV Reading
 *
 * @package Bakame.csv
 *
 */
interface ReaderInterface
{
    /**
     * Fetch on line of a CSV file as an array
     *
     * @param integer $rowIndex
     *
     * @return array
     *
     * @throws InvalidArgumentException If the $rowIndex is negative
     */
    public function fetchOne($rowIndex);

    /**
     * Return a single value from a given CSV line
     *
     * @param integer $rowIndex
     * @param integer $fieldIndex
     *
     * @return string
     */
    public function fetchValue($rowIndex, $fieldIndex);

    /**
     * Return a sequential array of all CSV lines
     *
     * @param callable $callable a callable function to be applied to each row to be return
     *
     * @return array
     */
    public function fetchAll(callable $callable = null);

    /**
     * Return a sequential array of all CSV lines; the rows are presented as associated arrays
     *
     * @param array $keys the name for each key member
     *
     * @param callable $callable a callable function to be applied to each row to be return
     *
     * @return array
     */
    public function fetchAssoc(array $keys, callable $callable = null);

    /**
     * Return a single column from the CSV data
     *
     * @param integer $fieldIndex field Index
     *
     * @param callable $callable a callable function to be applied to each value to be return
     *
     * @return array
     */
    public function fetchCol($fieldIndex, callable $callable = null);
}
