<?php
/**
* This file is part of the League.csv library
*
* @license http://opensource.org/licenses/MIT
* @link https://github.com/thephpleague/csv/
* @version 6.3.0
* @package League.csv
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
namespace League\Csv\Config;

use DomDocument;
use InvalidArgumentException;
use League\Csv\Iterator\MapIterator;
use Traversable;

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
    protected $encodingFrom = 'UTF-8';

    /**
     * BOM sequence for Outputting the CSV
     * @var string
     */
    protected $bom;

    /**
     * Return the CSV Iterator
     *
     * @return \SplFileObject
     */
    abstract public function getIterator();

    /**
     * JsonSerializable Interface
     *
     * @return array
     */
    public function jsonSerialize()
    {
        return iterator_to_array($this->convertToUtf8($this->getIterator()), false);
    }

    /**
     * Set the CSV encoding charset
     *
     * @param string $str
     *
     * @return static
     */
    public function setEncodingFrom($str)
    {
        $str = str_replace('_', '-', $str);
        $str = filter_var($str, FILTER_SANITIZE_STRING, ['flags' => FILTER_FLAG_STRIP_LOW|FILTER_FLAG_STRIP_HIGH]);
        if (empty($str)) {
            throw new InvalidArgumentException('you should use a valid charset');
        }
        $this->encodingFrom = strtoupper($str);

        return $this;
    }

    /**
     * Get the source CSV encoding charset
     *
     * @return string
     */
    public function getEncodingFrom()
    {
        return $this->encodingFrom;
    }

    /**
     * Set the BOM sequence to prepend the CSV on output
     *
     * @param string $str  The BOM sequence
     *
     * @return static
     */
    public function setOutputBOM($str = null)
    {
        if (empty($str)) {
            $this->bom = null;
            return $this;
        }
        $str = (string) $str;
        $str = trim($str);
        $this->bom = $str;

        return $this;
    }

    /**
     * Returns the BOM sequence in use on Output methods
     *
     * @return string
     */
    public function getOutputBOM()
    {
        return $this->bom;
    }

    /**
     * Output all data on the CSV file
     *
     * @param string $filename CSV downloaded name if present adds extra headers
     */
    public function output($filename = null)
    {
        $iterator = $this->getIterator();
        $iterator->rewind();
        //@codeCoverageIgnoreStart
        if (! is_null($filename) && self::isValidString($filename)) {
            $filename = trim($filename);
            $filename = filter_var($filename, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW);
            header("Content-Type: application/octet-stream");
            header("Content-Transfer-Encoding: binary");
            header('Content-Disposition: attachment; filename="'.$filename);
        }
        //@codeCoverageIgnoreEnd
        echo $this->bom;
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
     * @param string $class_name optional classname
     *
     * @return string
     */
    public function toHTML($class_name = 'table-csv-data')
    {
        $doc = $this->toXML('table', 'tr', 'td');
        $doc->documentElement->setAttribute('class', $class_name);

        return $doc->saveHTML($doc->documentElement);
    }

    /**
     * transform a CSV into a XML
     *
     * @param string $root_name XML root node name
     * @param string $row_name  XML row node name
     * @param string $cell_name XML cell node name
     *
     * @return \DomDocument
     */
    public function toXML($root_name = 'csv', $row_name = 'row', $cell_name = 'cell')
    {
        $doc = new DomDocument('1.0', 'UTF-8');
        $root = $doc->createElement($root_name);
        $iterator = $this->convertToUtf8($this->getIterator());
        foreach ($iterator as $row) {
            $item = $doc->createElement($row_name);
            array_walk($row, function ($value) use (&$item, $doc, $cell_name) {
                $content = $doc->createTextNode($value);
                $cell    = $doc->createElement($cell_name);
                $cell->appendChild($content);
                $item->appendChild($cell);
            });
            $root->appendChild($item);
        }
        $doc->appendChild($root);

        return $doc;
    }

    /**
     * Convert Csv file into UTF-8
     *
     * @return \Traversable
     */
    protected function convertToUtf8(Traversable $iterator)
    {
        if (strpos($this->encodingFrom, 'UTF-8') !== false) {
            return $iterator;
        }

        return new MapIterator($iterator, function ($row) {
            foreach ($row as &$value) {
                $value = mb_convert_encoding($value, 'UTF-8', $this->encodingFrom);
            }
            unset($value);

            return $row;
        });
    }
}
