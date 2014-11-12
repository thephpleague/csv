<?php
/**
* This file is part of the League.csv library
*
* @license http://opensource.org/licenses/MIT
* @link https://github.com/thephpleague/csv/
* @version 6.0.1
* @package League.csv
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
namespace League\Csv;

use DomDocument;
use InvalidArgumentException;
use IteratorAggregate;
use JsonSerializable;
use League\Csv\Config\Controls;
use League\Csv\Config\StreamFilter;
use League\Csv\Iterator\MapIterator;
use SplFileInfo;
use SplFileObject;
use SplTempFileObject;
use Traversable;

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
     *  Csv Controls Trait
     */
    use Controls;

    /**
     *  Stream Filter API Trait
     */
    use StreamFilter;

    /**
     * The constructor path
     *
     * can be a SplFileInfo object or the string path to a file
     *
     * @var \SplFileObject|string
     */
    protected $path;

    /**
     * The file open mode flag
     *
     * @var string
     */
    protected $open_mode;

    /**
     * Charset Encoding for the CSV
     *
     * @var string
     */
    protected $encodingFrom = 'UTF-8';

    /**
     * Create a new instance
     *
     * The path must be an SplFileInfo object
     * an object that implements the `__toString` method
     * a path to a file
     *
     * @param object|string $path      The file path
     * @param string        $open_mode the file open mode flag
     */
    public function __construct($path, $open_mode = 'r+')
    {
        ini_set("auto_detect_line_endings", '1');

        $this->path = $this->normalizePath($path);
        $this->open_mode = strtolower($open_mode);
        $this->initStreamFilter($this->path);
    }

    /**
     * Return a normalize path which could be a SplFileObject
     * or a string path
     *
     * @param object|string $path the filepath
     *
     * @return \SplFileObject|string
     */
    protected function normalizePath($path)
    {
        if ($path instanceof SplFileObject) {
            return $path;
        } elseif ($path instanceof SplFileInfo) {
            return $path->getPath().'/'.$path->getBasename();
        }

        $path = (string) $path;
        $path = trim($path);

        return $path;
    }

    /**
     * The destructor
     */
    public function __destruct()
    {
        //in case $this->path is a SplFileObject we need to remove its reference
        $this->path = null;
    }

    /**
     * Create a {@link AbstractCsv} from a string
     *
     * The path can be:
     * - an SplFileInfo,
     * - a SplFileObject,
     * - an object that implements the `__toString` method,
     * - a string
     *
     * BUT NOT a SplTempFileObject
     *
     * <code>
     *<?php
     * $csv = new Reader::createFromPath('/path/to/file.csv', 'a+');
     * $csv = new Reader::createFromPath(new SplFileInfo('/path/to/file.csv'));
     * $csv = new Reader::createFromPath(new SplFileObject('/path/to/file.csv'), 'rb');
     *
     * ?>
     * </code>
     *
     * @param object|string $path      file path
     * @param string        $open_mode the file open mode flag
     *
     * @throws \InvalidArgumentException If $path is a \SplTempFileObject object
     *
     * @return static
     */
    public static function createFromPath($path, $open_mode = 'r+')
    {
        if ($path instanceof SplTempFileObject) {
            throw new InvalidArgumentException('an `SplTempFileObject` object does not contain a valid path');
        } elseif ($path instanceof SplFileInfo) {
            $path = $path->getPath().'/'.$path->getBasename();
        }

        $path = (string) $path;
        $path = trim($path);

        return new static($path, $open_mode);
    }

    /**
     * Create a {@link AbstractCsv} from a SplFileObject
     *
     * The path can be:
     * - a SplFileObject,
     * - a SplTempFileObject
     *
     * <code>
     *<?php
     * $csv = new Writer::createFromFileObject(new SplFileInfo('/path/to/file.csv'));
     * $csv = new Writer::createFromFileObject(new SplTempFileObject);
     *
     * ?>
     * </code>
     *
     * @param SplFileObject $obj
     *
     * @return static
     */
    public static function createFromFileObject(SplFileObject $obj)
    {
        return new static($obj);
    }

    /**
     * Create a {@link AbstractCsv} from a string
     *
     * The string must be an object that implements the `__toString` method,
     * or a string
     *
     * @param string|object $str the string
     *
     * @throws \InvalidArgumentException If the data provided is invalid
     *
     * @return static
     */
    public static function createFromString($str)
    {
        if (! self::isValidString($str)) {
            throw new InvalidArgumentException(
                'the submitted data must be a string or an object implementing the `__toString` method'
            );
        }
        $obj = new SplTempFileObject();
        $obj->fwrite((string) $str.PHP_EOL);

        return static::createFromFileObject($obj);
    }

    /**
     * Create a {@link AbstractCsv} instance from another {@link AbstractCsv} object
     *
     * @param string $class_name the class to be instantiated
     * @param string $open_mode  the file open mode flag
     *
     * @return static
     */
    protected function newInstance($class_name, $open_mode)
    {
        $csv = new $class_name($this->path, $open_mode);
        $csv->delimiter = $this->delimiter;
        $csv->enclosure = $this->enclosure;
        $csv->escape = $this->escape;
        $csv->encodingFrom = $this->encodingFrom;

        return $csv;
    }

    /**
     * Create a {@link Writer} instance from a {@link AbstractCsv} object
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
     * Create a {@link Reader} instance from a {@link AbstractCsv} object
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
    * Validate a variable to be stringable
    *
    * @param string $str
    *
    * @return bool
    */
    public static function isValidString($str)
    {
        return is_scalar($str) || (is_object($str) && method_exists($str, '__toString'));
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
