<?php
/**
* This file is part of the League.csv library
*
* @license http://opensource.org/licenses/MIT
* @link https://github.com/thephpleague/csv/
* @version 7.2.0
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
use League\Csv\Modifier\QueryFilter;
use League\Csv\Modifier\StreamFilter;
use SplFileInfo;
use SplFileObject;
use SplTempFileObject;

/**
 *  An abstract class to enable basic CSV manipulation
 *
 * @package League.csv
 * @since  4.0.0
 *
 */
abstract class AbstractCsv implements JsonSerializable, IteratorAggregate
{
    use Controls;

    use Output;

    use QueryFilter;

    use StreamFilter;

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
     * UTF-32 BE BOM sequence
     */
    const BOM_UTF32_BE = "\x00\x00\xFE\xFF";

    /**
     * UTF-32 LE BOM sequence
     */
    const BOM_UTF32_LE = "\x00\x00\xFF\xFE";

    /**
     * The constructor path
     *
     * can be a SplFileInfo object or the string path to a file
     *
     * @var SplFileObject|string
     */
    protected $path;

    /**
     * The file open mode flag
     *
     * @var string
     */
    protected $open_mode;

    /**
     * Default SplFileObject flags settings
     *
     * @var int
     */
    protected $defaultFlags;

    /**
     * Creates a new instance
     *
     * The path must be an SplFileInfo object
     * an object that implements the `__toString` method
     * a path to a file
     *
     * @param SplFileObject|string $path      The file path
     * @param string               $open_mode the file open mode flag
     */
    protected function __construct($path, $open_mode = 'r+')
    {
        $this->defaultFlags = SplFileObject::READ_CSV | SplFileObject::READ_AHEAD | SplFileObject::SKIP_EMPTY;
        $this->flags = $this->defaultFlags;
        $this->open_mode = strtolower($open_mode);
        $this->path = $this->normalizePath($path);
        $this->initStreamFilter($this->path);
    }

    /**
     * Returns a normalize path which could be a SplFileObject
     * or a string path
     *
     * @param SplFileObject|string $path the filepath
     *
     * @return SplFileObject|string
     */
    protected function normalizePath($path)
    {
        if ($path instanceof SplFileObject) {
            return $path;
        }

        return $path;
    }

    /**
     * The destructor
     */
    public function __destruct()
    {
        $this->path = null;
    }

    /**
     * Returns the CSV Iterator
     *
     * @return SplFileObject
     */
    public function getIterator()
    {
        $iterator = $this->path;
        if (!$iterator instanceof SplFileObject) {
            $iterator = new SplFileObject($this->getStreamFilterPath(), $this->open_mode);
        }
        $iterator->setCsvControl($this->delimiter, $this->enclosure, $this->escape);
        $iterator->setFlags($this->flags);

        return $iterator;
    }

    /**
     * Returns the CSV Iterator for conversion
     *
     * @return \Iterator
     */
    protected function getConversionIterator()
    {
        $this->addFilter(function ($row) {
            return is_array($row) && [null] != $row;
        });
        $iterator = $this->getIterator();
        $iterator->setFlags($this->defaultFlags);
        $iterator = $this->applyBomStripping($iterator);
        $iterator = $this->applyIteratorFilter($iterator);
        $iterator = $this->applyIteratorSortBy($iterator);

        return $this->applyIteratorInterval($iterator);
    }

    /**
     * Creates a {@link AbstractCsv} from a string
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
     * @throws InvalidArgumentException If $path is a \SplTempFileObject object
     *
     * @return static
     */
    public static function createFromPath($path, $open_mode = 'r+')
    {
        if ($path instanceof SplTempFileObject) {
            throw new InvalidArgumentException('an `SplTempFileObject` object does not contain a valid path');
        }

        if ($path instanceof SplFileInfo) {
            $path = $path->getPath().'/'.$path->getBasename();
        }

        return new static($path, $open_mode);
    }

    /**
     * Creates a {@link AbstractCsv} from a SplFileObject
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
     * Creates a {@link AbstractCsv} from a string
     *
     * The string must be an object that implements the `__toString` method,
     * or a string
     *
     * @param string|object $str     the string
     * @param string        $newline the newline character
     *
     * @return static
     */
    public static function createFromString($str, $newline = "\n")
    {
        $file = new SplTempFileObject();
        $file->fwrite($str);

        $obj = static::createFromFileObject($file);
        $obj->setNewline($newline);

        return $obj;
    }

    /**
     * Creates a {@link AbstractCsv} instance from another {@link AbstractCsv} object
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
        $csv->flags = $this->flags;
        $csv->input_bom = $this->input_bom;
        $csv->output_bom = $this->output_bom;
        $csv->newline = $this->newline;

        return $csv;
    }

    /**
     * Creates a {@link Writer} instance from a {@link AbstractCsv} object
     *
     * @param string $open_mode the file open mode flag
     *
     * @return Writer
     */
    public function newWriter($open_mode = 'r+')
    {
        return $this->newInstance('\League\Csv\Writer', $open_mode);
    }

    /**
     * Creates a {@link Reader} instance from a {@link AbstractCsv} object
     *
     * @param string $open_mode the file open mode flag
     *
     * @return Reader
     */
    public function newReader($open_mode = 'r+')
    {
        return $this->newInstance('\League\Csv\Reader', $open_mode);
    }

    /**
     * Validate the submitted integer
     *
     * @param int    $int
     * @param int    $min
     * @param string $message
     *
     * @throws InvalidArgumentException If the value is invalid
     *
     * @return int
     */
    protected function filterInteger($int, $min, $message)
    {
        if (false === ($int = filter_var($int, FILTER_VALIDATE_INT, ['options' => ['min_range' => $min]]))) {
            throw new InvalidArgumentException($message);
        }

        return $int;
    }
}
