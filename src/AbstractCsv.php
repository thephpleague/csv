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

use IteratorAggregate;
use JsonSerializable;
use League\Csv\Config\Controls;
use League\Csv\Config\Factory;
use League\Csv\Config\Output;
use League\Csv\Config\StreamFilter;
use SplFileInfo;
use SplFileObject;

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
     *  Csv Factory Trait
     */
    use Factory;

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
        ini_set('auto_detect_line_endings', '1');

        $this->path      = $this->normalizePath($path);
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

        return trim($path);
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
        $csv->delimiter    = $this->delimiter;
        $csv->enclosure    = $this->enclosure;
        $csv->escape       = $this->escape;
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
