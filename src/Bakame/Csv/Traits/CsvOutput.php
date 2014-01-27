<?php
/**
* Bakame.csv - A lightweight CSV Coder/Decoder library
*
* @author Ignace Nyamagana Butera <nyamsprod@gmail.com>
* @copyright 2013 Ignace Nyamagana Butera
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
namespace Bakame\Csv\Traits;

use DomDocument;
use SplFileObject;

/**
 *  A Reader to ease CSV parsing in PHP 5.4+
 *
 * @package Bakame.csv
 * @since  3.3.0
 *
 */
trait CsvOutput
{
    /**
     * The CSV file Object
     *
     * @var \SplFileObject
     */
    protected $file;

    /**
     * Return the current associated file
     *
     * @return \SplFileObject
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
    public function toHTML($classname = 'table-csv-data')
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
}
