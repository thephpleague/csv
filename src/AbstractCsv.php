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
namespace League\Csv;

use InvalidArgumentException;
use IteratorAggregate;
use JsonSerializable;
use League\Csv\Config\Controls;
use League\Csv\Config\Output;
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
     *  UTF-8 BOM sequence
     */
    const BOM_UTF8 = "\xEF\xBB\xBF";

    /**
     * UTF-16 BE BOM sequence
     */
    const BOM_UTF16_BE = "\xFE\xFF";

    /**
     * UTF-16 LE BOM sequence
     */
    const BOM_UTF16_LE = "\xFF\xFE";

    /**
     * UTF-16 BE BOM sequence
     */
    const BOM_UTF32_BE = "\x00\x00\xFE\xFF";

    /**
     * UTF-16 LE BOM sequence
     */
    const BOM_UTF32_LE = "\x00\x00\xFF\xFE";

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
     *  Csv Controls Trait
     */
    use Controls;

    /**
     * Csv Ouputting Trait
     */
    use Output;

    /**
     *  Stream Filter API Trait
     */
    use StreamFilter;

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
        $obj->fwrite(rtrim($str)."\n");

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
        $csv->escape    = $this->escape;
        $csv->encodingFrom = $this->encodingFrom;
        $csv->bom          = $this->bom;

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
    * Validate a variable to be stringable
    *
    * @param object|string $str
    *
    * @return bool
    */
    public static function isValidString($str)
    {
        return is_scalar($str) || (is_object($str) && method_exists($str, '__toString'));
    }
}
