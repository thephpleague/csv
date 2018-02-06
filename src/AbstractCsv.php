<?php
/**
* This file is part of the League.csv library
*
* @license http://opensource.org/licenses/MIT
* @link https://github.com/thephpleague/csv/
* @version 8.2.3
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
use League\Csv\Modifier\StreamIterator;
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
    const BOM_UTF32_LE = "\xFF\xFE\x00\x00";

    /**
     * The path
     *
     * can be a StreamIterator object, a SplFileObject object or the string path to a file
     *
     * @var StreamIterator|SplFileObject|string
     */
    protected $path;

    /**
     * The file open mode flag
     *
     * @var string
     */
    protected $open_mode;

    /**
     * Creates a new instance
     *
     * The path must be an SplFileInfo object
     * an object that implements the `__toString` method
     * a path to a file
     *
     * @param StreamIterator|SplFileObject|string $path      The file path
     * @param string                              $open_mode The file open mode flag
     */
    protected function __construct($path, $open_mode = 'r+')
    {
        $this->open_mode = strtolower($open_mode);
        $this->path = $path;
        $this->initStreamFilter($this->path);
    }

    /**
     * The destructor
     */
    public function __destruct()
    {
        $this->path = null;
    }

    /**
     * Return a new {@link AbstractCsv} from a SplFileObject
     *
     * @param SplFileObject $file
     *
     * @return static
     */
    public static function createFromFileObject(SplFileObject $file)
    {
        $csv = new static($file);
        $controls = $file->getCsvControl();
        $csv->setDelimiter($controls[0]);
        $csv->setEnclosure($controls[1]);
        if (isset($controls[2])) {
            $csv->setEscape($controls[2]);
        }

        return $csv;
    }

    /**
     * Return a new {@link AbstractCsv} from a PHP resource stream or a StreamIterator
     *
     * @param resource $stream
     *
     * @return static
     */
    public static function createFromStream($stream)
    {
        return new static(new StreamIterator($stream));
    }

    /**
     * Return a new {@link AbstractCsv} from a string
     *
     * The string must be an object that implements the `__toString` method,
     * or a string
     *
     * @param string $str the string
     *
     * @return static
     */
    public static function createFromString($str)
    {
        $file = new SplTempFileObject();
        $file->fwrite(static::validateString($str));

        return new static($file);
    }

    /**
     * validate a string
     *
     * @param mixed $str the value to evaluate as a string
     *
     * @throws InvalidArgumentException if the submitted data can not be converted to string
     *
     * @return string
     */
    protected static function validateString($str)
    {
        if (is_string($str) || (is_object($str) && method_exists($str, '__toString'))) {
            return (string) $str;
        }
        throw new InvalidArgumentException('Expected data must be a string or stringable');
    }

    /**
     * Return a new {@link AbstractCsv} from a file path
     *
     * @param mixed  $path      file path
     * @param string $open_mode the file open mode flag
     *
     * @throws InvalidArgumentException If $path is a SplTempFileObject object
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

        return new static(static::validateString($path), $open_mode);
    }

    /**
     * Return a new {@link AbstractCsv} instance from another {@link AbstractCsv} object
     *
     * @param string $class     the class to be instantiated
     * @param string $open_mode the file open mode flag
     *
     * @return static
     */
    protected function newInstance($class, $open_mode)
    {
        $csv = new $class($this->path, $open_mode);
        $csv->delimiter = $this->delimiter;
        $csv->enclosure = $this->enclosure;
        $csv->escape = $this->escape;
        $csv->input_encoding = $this->input_encoding;
        $csv->input_bom = $this->input_bom;
        $csv->output_bom = $this->output_bom;
        $csv->newline = $this->newline;

        return $csv;
    }

    /**
     * Return a new {@link Writer} instance from a {@link AbstractCsv} object
     *
     * @param string $open_mode the file open mode flag
     *
     * @return Writer
     */
    public function newWriter($open_mode = 'r+')
    {
        return $this->newInstance(Writer::class, $open_mode);
    }

    /**
     * Return a new {@link Reader} instance from a {@link AbstractCsv} object
     *
     * @param string $open_mode the file open mode flag
     *
     * @return Reader
     */
    public function newReader($open_mode = 'r+')
    {
        return $this->newInstance(Reader::class, $open_mode);
    }

    /**
     * Returns the inner CSV Document Iterator object
     *
     * @return StreamIterator|SplFileObject
     */
    public function getIterator()
    {
        $iterator = $this->setIterator();
        $iterator->setCsvControl($this->delimiter, $this->enclosure, $this->escape);
        $iterator->setFlags(SplFileObject::READ_CSV | SplFileObject::READ_AHEAD | SplFileObject::SKIP_EMPTY);

        return $iterator;
    }

    /**
     * Set the Inner Iterator
     *
     * @return StreamIterator|SplFileObject
     */
    protected function setIterator()
    {
        if ($this->path instanceof StreamIterator || $this->path instanceof SplFileObject) {
            return $this->path;
        }

        return new SplFileObject($this->getStreamFilterPath(), $this->open_mode);
    }
}
