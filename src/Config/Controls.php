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
namespace League\Csv\Config;

use Traversable;
use SplFileObject;
use InvalidArgumentException;
use League\Csv\Iterator\MapIterator;

/**
 *  A trait to configure and check CSV file and content
 *
 * @package League.csv
 * @since  5.5.0
 *
 */
trait Controls
{
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
     * DEPRECATION WARNING! This method will be removed in the next major point release
     *
     * @deprecated deprecated since version 5.5
     *
     * @param string $str
     *
     * @return self
     */
    public function setEncoding($str)
    {
        return $this->setEncodingFrom($str);
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release
     *
     * @deprecated deprecated since version 5.5
     *
     * @return string
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

    /**
    * Validate a variable to be stringable
    *
    * @param mixed $str
    *
    * @return boolean
    */
    public static function isValidString($str)
    {
        return is_scalar($str) || (is_object($str) && method_exists($str, '__toString'));
    }
}
