<?php
/**
* This file is part of the League.csv library
*
* @license http://opensource.org/licenses/MIT
* @link https://github.com/thephpleague/csv/
* @version 6.X.X
* @package League.csv
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
namespace League\Csv\Config;

use InvalidArgumentException;
use SplFileInfo;
use SplFileObject;
use SplTempFileObject;

/**
 *  A trait to facilate class instantiation
 *
 * @package League.csv
 * @since  6.4.0
 *
 */
trait Factory
{
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

        return new static(trim($path), $open_mode);
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
     * @param string        $newline the newline character
     *
     * @throws \InvalidArgumentException If the data provided is invalid
     *
     * @return static
     */
    public static function createFromString($str, $newline = PHP_EOL)
    {
        if (! self::isValidString($str)) {
            throw new InvalidArgumentException(
                'the submitted data must be a string or an object implementing the `__toString` method'
            );
        }

        $obj = new SplTempFileObject();
        $obj->fwrite(rtrim($str).$newline);

        $res = static::createFromFileObject($obj);
        if ('League\Csv\Writer' == get_class($res)) {
            $res->setNewline($newline);
        }

        return $res;
    }
}
