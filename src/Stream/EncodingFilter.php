<?php
/**
* League.csv - A lightweight CSV Coder/Decoder library
*
* @author Ignace Nyamagana Butera <nyamsprod@gmail.com>
* @copyright 2014 Ignace Nyamagana Butera
* @link https://github.com/nyamsprod/League.csv
* @license http://opensource.org/licenses/MIT
* @version 5.4.0
* @package League.csv
*
* MIT LICENSE
*
* Permission is hereby granted, free of charge, to any person obtaining
* a copy of this software and associated documentation files (the
* "Software"), to deal in the Software without restriction, including
* without limitation the rights to use, copy, modify, merge, publish,
* distribute, sublicense, and/or sell copies of the Software, and to
* permit persons to whom the Software is furnished to do so, subject to
* the following conditions:
*
* The above copyright notice and this permission notice shall be
* included in all copies or substantial portions of the Software.
*
* THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
* EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
* MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
* NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
* LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
* OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
* WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/
namespace League\Csv\Stream;

use php_user_filter;
use InvalidArgumentException;

/**
 *  A Stream Filter Plugin to handle charset conversion
 *
 * @package League.csv
 * @since  5.4.0
 *
 */
class EncodingFilter extends php_user_filter implements FilterInterface
{
    /**
     * Filter encoding name
     * @var string
     */
    private static $name = 'csv.content.converter';

    /**
     * Check if the stream is already registered
     *
     * @var boolean
     */
    private static $is_registered = false;

    /**
     * The source encoding
     *
     * @var string
     */
    private $encodingFrom;

    /**
     * The encoding to convert the source to
     *
     * @var string
     */
    private $encodingTo;

    /**
     * Detect if the locale is already stored
     *
     * @var boolean
     */
    private static $is_locale_stored = false;

    /**
     * Server current locale
     *
     * @var string
     */
    private $locale;

    /**
     * The constructor
     */
    public function __construct()
    {
        if (self::isRegistered()) {
            return;
        }
        stream_filter_register(self::$name.'.*', __CLASS__);
        self::$is_registered = true;
    }

    /**
     * Tell if the locale is stored by the library or not
     *
     * @return boolean
     */
    public static function isLocaleStored()
    {
        return self::$is_locale_stored;
    }

    /**
     * Store the current server locale (not thread safe)
     *
     * @return self
     */
    private function storeLocale()
    {
        if (self::isLocaleStored()) {
            return $this;
        }
        self::$is_locale_stored = true;
        $this->locale = setlocale(LC_CTYPE, '0');
        if (stripos($this->locale, 'UTF-8') !== false) {
            return $this;
        }
        setlocale(LC_CTYPE, 'en_US.UTF-8');

        return $this;
    }

    /**
     * Restor the server locale setting (not thread safe)
     *
     * @return self
     */
    private function restoreLocale()
    {
        if (!self::isLocaleStored()) {
            return $this;
        }
        self::$is_locale_stored = false;
        setlocale(LC_CTYPE, $this->locale);

        return $this;
    }

    /**
    * EncodingFrom Charset Setter
    *
    * @param string $charset The charset to encode from
    *
    * @return self
    *
    * @throws \InvalidArgumentException If The charset name is not valid
    */
    public function setEncodingFrom($encoding)
    {
        $encoding = $this->filterValidateEncoding($encoding);
        if (! $encoding) {
            throw new InvalidArgumentException('The encoding must be a valid string');
        }
        $this->encodingFrom = $encoding;

        return $this;
    }

    /**
    * EncodingFrom Charset Getter
    *
    * @return string The charset to encode from
    */
    public function getEncodingFrom()
    {
        return $this->encodingFrom;
    }

    /**
    * EncodingTo Charset Setter
    *
    * @param string $charset The charset to encode to
    *
    * @return self
    *
    * @throws \InvalidArgumentException If The charset name is not valid
    */
    public function setEncodingTo($encoding)
    {
        $encoding = $this->filterValidateEncoding($encoding);
        if (! $encoding) {
            throw new InvalidArgumentException('The encoding must be a valid string');
        }
        $this->encodingTo = $encoding;

        return $this;
    }

    /**
    * EncodingTo Charset Getter
    *
    * @return string The charset to encode to
    */
    public function getEncodingTo()
    {
        return $this->encodingTo;
    }

    /**
    * Validate a charset name
    *
    * @param string $str the charset
    *
    * @return mixed
    */
    private function filterValidateEncoding($str)
    {
        $str = trim($str);
        $str = str_replace('_', '-', $str);
        $str = filter_var($str, FILTER_SANITIZE_STRING, ['flags' => FILTER_FLAG_STRIP_LOW|FILTER_FLAG_STRIP_HIGH]);

        if (empty($str)) {
            return false;
        }

        return strtoupper($str);
    }

    /**
     * {@inheritdoc}
     */
    public static function isRegistered()
    {
        return self::$is_registered;
    }

    /**
     * {@inheritdoc}
     */
    public static function getName()
    {
        return self::$name;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchPath($path)
    {
        $stream_path = 'php://filter/read='.self::$name.'.'.$this->encodingFrom;
        if (! is_null($this->encodingTo)) {
            $stream_path .= ':'.$this->encodingTo;
        }

        return $stream_path .= '/resource='.$path;
    }

    /**
     * {@inheritdoc}
     */
    public function onCreate()
    {
        if (strpos($this->filtername, self::$name.'.') !== 0) {
            return false;
        }

        $params = substr($this->filtername, strlen(self::$name)+1);
        if (! preg_match('/^([-\w]+)(:([-\w]+))?$/', $params, $matches)) {
            return false;
        }

        $this->encodingFrom = 'auto';
        if (isset($matches[1])) {
            $this->setEncodingFrom($matches[1]);
        }

        $this->encodingTo = mb_internal_encoding();
        if (isset($matches[3])) {
            $this->setEncodingTo($matches[3]);
        }

        $this->storeLocale();

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function onClose()
    {
        $this->restoreLocale();

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function filter($in, $out, &$consumed, $closing)
    {
        while ($res = stream_bucket_make_writeable($in)) {
            $res->data = mb_convert_encoding($res->data, $this->encodingTo, $this->encodingFrom);
            $consumed += $res->datalen;
            stream_bucket_append($out, $res);
        }

        return PSFS_PASS_ON;
    }
}
