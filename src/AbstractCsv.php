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
use Traversable;
use SplFileInfo;
use SplFileObject;
use SplTempFileObject;
use RuntimeException;
use InvalidArgumentException;
use IteratorAggregate;
use LimitIterator;
use CallbackFilterIterator;
use League\Csv\Iterator\MapIterator;
use League\Csv\Stream\Filter;

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
    use Filter;

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
    protected $encodingFrom = 'UTF-8';

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
     * Instantiate a AbstractCsv extended class
     *
     * @param string $class_name the Class to load {@link Writer} or {@link Reader}
     * @param string $open_mode  the file open mode flag
     *
     * @return \League\Csv\AbstractCSv
     */
    protected function newInstance($class_name, $open_mode)
    {
        $obj = new $class_name($this->path, $open_mode);
        $obj->delimiter = $this->delimiter;
        $obj->enclosure = $this->enclosure;
        $obj->escape = $this->escape;
        $obj->flags = $this->flags;
        $obj->encodingFrom = $this->encodingFrom;

        return $obj;
    }

    /**
     * Instantiate a {@link Writer} class from the current object
     *
     * @param string $open_mode the file open mode flag
     *
     * @return \League\Csv\Writer
     */
    public function newWriter($open_mode = 'r+')
    {
        return $this->newInstance('\League\Csv\Writer', $open_mode);
    }

    /**
     * Instantiate a {@link Reader} class from the current object
     *
     * @param string $open_mode the file open mode flag
     *
     * @return \League\Csv\Reader
     */
    public function newReader($open_mode = 'r+')
    {
        return $this->newInstance('\League\Csv\Reader', $open_mode);
    }

    /**
    * Validate a variable to be stringable
    *
    * @param mixed $str
    *
    * @return boolean
    */
    public static function isValidString($str)
    {
        return (is_scalar($str) || (is_object($str) && method_exists($str, '__toString')));
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

        //detect and validate the possible delimiters
        $delimiters = array_filter($delimiters, function ($str) {
            return 1 == mb_strlen($str);
        });
        $delimiters = array_merge([$this->delimiter, ',', ';', "\t"], $delimiters);
        $delimiters = array_unique($delimiters);

        //detecting the possible delimiter
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
        $iterator = null;
        arsort($res, SORT_NUMERIC);
        $res = array_keys(array_filter($res));
        if (! $res) {
            return null;
        } elseif (count($res) == 1) {
            return $res[0];
        }
        throw new RuntimeException('too many delimiters were found: `'.implode('`,`', $res).'`');
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
     * Return the CSV Iterator
     *
     * @return \SplFileObject
     */
    public function getIterator()
    {
        $obj = $this->path;
        if (! $obj instanceof SplFileObject) {
            $obj = new SplFileObject($this->getStreamFilterPath(), $this->open_mode);
        }
        $obj->setCsvControl($this->delimiter, $this->enclosure, $this->escape);
        $obj->setFlags($this->flags);

        return $obj;
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release
     *
     * @deprecated deprecated since version 5.5
     */
    public function setEncoding($str)
    {
        return $this->setEncodingFrom($str);
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release
     *
     * @deprecated deprecated since version 5.5
     */
    public function getEncoding()
    {
        return $this->getEncodingFrom();
    }

    /**
     * Set the CSV encoding charset
     *
     * @param string $str
     *
     * @return self
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
     * Convert Csv file into UTF-8
     *
     * @return \Traversable
     */
    protected function convertToUtf8(Traversable $iterator)
    {
        if ('UTF-8' == $this->encodingFrom) {
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

    /**
     * JsonSerializable Interface
     *
     * @return array
     */
    public function jsonSerialize()
    {
        $iterator = $this->convertToUtf8($this->getIterator());
        $res = iterator_to_array($iterator, false);
        $iterator = null;

        return $res;
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
        if (! is_null($filename) && AbstractCsv::isValidString($filename)) {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="'.$filename.'"');
            header('Content-Transfer-Encoding: binary');
            if (! $iterator instanceof SplTempFileObject) {
                header('Content-Length: '.$iterator->getSize());
            }
        }
        //@codeCoverageIgnoreEnd
        $iterator->rewind();
        $iterator->fpassthru();
        $iterator = null;
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
        $iterator = null;

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
