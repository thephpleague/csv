<?php
/**
* This file is part of the League.csv library
*
* @license http://opensource.org/licenses/MIT
* @link https://github.com/thephpleague/csv/
* @version 8.0.0
* @package League.csv
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
namespace League\Csv\Config;

use DomDocument;
use InvalidArgumentException;
use Iterator;
use League\Csv\AbstractCsv;
use League\Csv\Modifier\MapIterator;
use SplFileObject;

/**
 *  A trait to output CSV
 *
 * @package League.csv
 * @since  6.3.0
 *
 */
trait Output
{
    /**
     * Charset Encoding for the CSV
     *
     * @var string
     */
    protected $inputEncoding = 'UTF-8';

    /**
     * The Input file BOM character
     * @var string
     */
    protected $inputBom;

    /**
     * The Output file BOM character
     * @var string
     */
    protected $outputBom = '';

    /**
     * Sets the CSV encoding charset
     *
     * @param string $str
     *
     * @return static
     */
    public function setInputEncoding($str)
    {
        $str = str_replace('_', '-', $str);
        $str = filter_var($str, FILTER_SANITIZE_STRING, ['flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH]);
        if (empty($str)) {
            throw new InvalidArgumentException('you should use a valid charset');
        }
        $this->inputEncoding = strtoupper($str);

        return $this;
    }

    /**
     * Sets the CSV encoding charset
     *
     * DEPRECATION WARNING! This method will be removed in the next major point release
     *
     * @deprecated deprecated since version 4.1
     *
     * @param string $str
     *
     * @return static
     */
    public function setEncodingFrom($str)
    {
        return $this->setInputEncoding($str);
    }

    /**
     * Gets the source CSV encoding charset
     *
     * @return string
     */
    public function getInputEncoding()
    {
        return $this->inputEncoding;
    }

    /**
     * Gets the source CSV encoding charset
     *
     * DEPRECATION WARNING! This method will be removed in the next major point release
     *
     * @deprecated deprecated since version 4.1
     *
     * @return string
     */
    public function getEncodingFrom()
    {
        return $this->inputEncoding;
    }

    /**
     * Sets the BOM sequence to prepend the CSV on output
     *
     * @param string $str The BOM sequence
     *
     * @return static
     */
    public function setOutputBOM($str)
    {
        if (empty($str)) {
            $this->outputBom = '';

            return $this;
        }

        $this->outputBom = (string) $str;

        return $this;
    }

    /**
     * Returns the BOM sequence in use on Output methods
     *
     * @return string
     */
    public function getOutputBOM()
    {
        return $this->outputBom;
    }

    /**
     * Returns the BOM sequence of the given CSV
     *
     * @return string
     */
    public function getInputBOM()
    {
        if (null === $this->inputBom) {
            $bom = [
                AbstractCsv::BOM_UTF32_BE, AbstractCsv::BOM_UTF32_LE,
                AbstractCsv::BOM_UTF16_BE, AbstractCsv::BOM_UTF16_LE, AbstractCsv::BOM_UTF8,
            ];
            $csv = $this->getIterator();
            $csv->setFlags(SplFileObject::READ_CSV);
            $csv->rewind();
            $line = $csv->fgets();
            $res  = array_filter($bom, function ($sequence) use ($line) {
                return strpos($line, $sequence) === 0;
            });

            $this->inputBom = (string) array_shift($res);
        }

        return $this->inputBom;
    }

    /**
     * @inheritdoc
     */
    abstract public function getIterator();

    /**
     * Outputs all data on the CSV file. 
     * The CSV data  will be written to the output buffer. Returning 
     * the result of this function (or anything else) may append 
     * unwanted data to the output buffer (CSV file).
     * See fpassthru (php docs) for more info.
     *
     * @param string $filename CSV downloaded name if present adds extra headers
     *
     * @return int Returns the number of characters read from the handle
     *             and passed through to the output.
     */
    public function output($filename = null)
    {
        if (null !== $filename) {
            $filename = filter_var($filename, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW);
            header('Content-Type: application/octet-stream');
            header('Content-Transfer-Encoding: binary');
            header("Content-Disposition: attachment; filename=\"$filename\"");
        }

        return $this->fpassthru();
    }

    /**
     * Outputs all data from the CSV
     *
     * @return int Returns the number of characters read from the handle
     *             and passed through to the output.
     */
    protected function fpassthru()
    {
        $bom = '';
        $inputBom = $this->getInputBOM();
        if ($this->outputBom && $inputBom != $this->outputBom) {
            $bom = $this->outputBom;
        }
        $csv = $this->getIterator();
        $csv->setFlags(SplFileObject::READ_CSV);
        $csv->rewind();
        if (!empty($bom)) {
            $csv->fseek(mb_strlen($inputBom));
        }
        echo $bom;
        $res = $csv->fpassthru();

        return $res + strlen($bom);
    }

    /**
     * Retrieves the CSV content
     *
     * @return string
     */
    public function __toString()
    {
        ob_start();
        $this->fpassthru();

        return ob_get_clean();
    }

    /**
     * @inheritdoc
     */
    public function jsonSerialize()
    {
        return iterator_to_array($this->convertToUtf8($this->getQueryIterator()), false);
    }

    /**
     * Returns the CSV Iterator
     *
     * @return Iterator
     */
    abstract protected function getQueryIterator();

    /**
     * Convert Csv file into UTF-8
     *
     * @param Iterator $iterator
     *
     * @return Iterator
     */
    protected function convertToUtf8(Iterator $iterator)
    {
        if (stripos($this->inputEncoding, 'UTF-8') !== false) {
            return $iterator;
        }

        $convertCell = function ($value) {
            return mb_convert_encoding($value, 'UTF-8', $this->inputEncoding);
        };

        $convertRow = function (array $row) use ($convertCell) {
            return array_map($convertCell, $row);
        };

        return new MapIterator($iterator, $convertRow);
    }

    /**
     * Returns a HTML table representation of the CSV Table
     *
     * @param string $class_attr optional classname
     *
     * @return string
     */
    public function toHTML($class_attr = 'table-csv-data')
    {
        $doc = $this->toXML('table', 'tr', 'td');
        $doc->documentElement->setAttribute('class', $class_attr);

        return $doc->saveHTML($doc->documentElement);
    }

    /**
     * Transforms a CSV into a XML
     *
     * @param string $rootName XML root node name
     * @param string $rowName  XML row node name
     * @param string $cellName XML cell node name
     *
     * @return DomDocument
     */
    public function toXML($rootName = 'csv', $rowName = 'row', $cellName = 'cell')
    {
        $doc = new DomDocument('1.0', 'UTF-8');
        $root = $doc->createElement($rootName);
        foreach ($this->convertToUtf8($this->getQueryIterator()) as $row) {
            $rowElement = $doc->createElement($rowName);
            array_walk($row, function ($value) use (&$rowElement, $doc, $cellName) {
                $content = $doc->createTextNode($value);
                $cell = $doc->createElement($cellName);
                $cell->appendChild($content);
                $rowElement->appendChild($cell);
            });
            $root->appendChild($rowElement);
        }
        $doc->appendChild($root);

        return $doc;
    }
}
