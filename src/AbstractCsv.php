<?php
/**
* This file is part of the League.csv library
*
* @license http://opensource.org/licenses/MIT
* @link https://github.com/thephpleague/csv/
* @version 5.5.0
* @package League.csv
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
namespace League\Csv;

use DomDocument;
use JsonSerializable;
use SplFileInfo;
use SplFileObject;
use SplTempFileObject;
use RuntimeException;
use InvalidArgumentException;
use IteratorAggregate;
use LimitIterator;
use CallbackFilterIterator;
use League\Csv\Config\StreamFilter;
use League\Csv\Config\Controls;

/**
 *  An abstract class to enable basic CSV manipulation
 *
 * @package League.csv
 * @since  4.0.0
 *
 */
abstract class AbstractCsv implements JsonSerializable, IteratorAggregate
{
    /**
     *  Stream Filter Trait
     */
    use StreamFilter;

    /**
     *  Controls Trait
     */
    use Controls;

    /**
     * The constructor path
     *
     * @var mixed can be a SplFileInfo object or the path to a file
     */
    protected $path;

    /**
     * The file open mode flag
     *
     * @var string
     */
    protected $open_mode;

    /**
     * The constructor
     *
     * @param mixed  $path      an SplFileInfo object or the path to a file
     * @param string $open_mode the file open mode flag
     */
    public function __construct($path, $open_mode = 'r+')
    {
        if (! is_string($path) && ! $path instanceof SplFileInfo) {
            throw new InvalidArgumentException(
                'path must be a valid string or a `SplFileInfo` object'
            );
        }
        ini_set("auto_detect_line_endings", '1');
        //lazy loading
        $this->path = $path;
        $this->open_mode = strtolower($open_mode);
        $this->initStreamFilter($path);
    }

    /**
     * The destructor
     */
    public function __destruct()
    {
        //in case path is a SplFileObject we need to remove its reference
        $this->path = null;
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
     * Detect the CSV file delimiter
     *
     * @param integer $nb_rows
     * @param array   $delimiters additional delimiters
     *
     * @return string
     *
     * @throws \InvalidArgumentException If $nb_rows value is invalid
     * @throws \RuntimeException         If too many delimiters are found
     */
    public function detectDelimiter($nb_rows = 1, array $delimiters = [])
    {
        $nb_rows = filter_var($nb_rows, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (! $nb_rows) {
            throw new InvalidArgumentException('`$nb_rows` must be a valid positive integer');
        }

        //validate the possible delimiters
        $delimiters = array_filter($delimiters, function ($str) {
            return 1 == mb_strlen($str);
        });
        $delimiters = array_merge([$this->delimiter, ',', ';', "\t"], $delimiters);
        $delimiters = array_unique($delimiters);

        //detect the possible delimiter
        $res = [];
        foreach ($delimiters as $delim) {
            $iterator = $this->getIterator();
            $iterator->setCsvControl($delim, $this->enclosure, $this->escape);
            //"reduce" the csv length to a maximum of $nb_rows
            $iterator = new CallbackFilterIterator(
                new LimitIterator($iterator, 0, $nb_rows),
                function ($row) {
                    return is_array($row) && count($row) > 1;
                }
            );
            $res[$delim] = count(iterator_to_array($iterator, false));
        }

        arsort($res, SORT_NUMERIC);
        $res = array_keys(array_filter($res));
        if (! $res) {
            return null;
        } elseif (1 == count($res)) {
            return $res[0];
        }
        throw new RuntimeException('too many delimiters were found: `'.implode('`,`', $res).'`');
    }

    /**
     * Return the CSV Iterator
     *
     * @return \SplFileObject
     */
    public function getIterator()
    {
        $obj = $this->path;
        if (! $obj instanceof SplTempFileObject) {
            $obj = new SplFileObject($this->getStreamFilterPath(), $this->open_mode);
        }
        $obj->setCsvControl($this->delimiter, $this->enclosure, $this->escape);
        $obj->setFlags($this->flags);

        return $obj;
    }

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
     * Output all data on the CSV file
     *
     * @param string $filename CSV downloaded name if present adds extra headers
     */
    public function output($filename = null)
    {
        $iterator = $this->getIterator();
        //@codeCoverageIgnoreStart
        if (! is_null($filename) && self::isValidString($filename)) {
            $filename = (string) $filename;
            $filename = filter_var($filename, FILTER_UNSAFE_RAW, ['flags' => FILTER_FLAG_STRIP_LOW]);
            header('Content-Type: application/octet-stream');
            header('Content-Transfer-Encoding: binary');
            header('Content-Disposition: attachment; filename="'.$filename.'"');
            if (! $iterator instanceof SplTempFileObject) {
                header('Content-Length: '.$iterator->getSize());
            }
        }
        //@codeCoverageIgnoreEnd
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
            foreach ($row as $value) {
                $content = $doc->createTextNode($value);
                $cell = $doc->createElement($cell_name);
                $cell->appendChild($content);
                $item->appendChild($cell);
            }
            $root->appendChild($item);
        }
        $doc->appendChild($root);

        return $doc;
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
}
