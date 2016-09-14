<?php
/**
* This file is part of the League.csv library
*
* @license http://opensource.org/licenses/MIT
* @link https://github.com/thephpleague/csv/
* @version 9.0.0
* @package League.csv
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
namespace League\Csv\Config;

use InvalidArgumentException;
use LogicException;

/**
 *  A Trait to ease PHP Stream Filters manipulation
 *  with a SplFileObject
 *
 * @package League.csv
 * @since  6.0.0
 *
 */
trait StreamFilter
{
    /**
     * collection of stream filters
     *
     * @var array
     */
    protected $stream_filters = [];

    /**
     * Stream filtering mode to apply on all filters
     *
     * @var int
     */
    protected $stream_filter_mode = STREAM_FILTER_ALL;

    /**
     *the real path
     *
     * @var string the real path to the file
     *
     */
    protected $stream_uri;

    /**
     * PHP Stream Filter Regex
     *
     * @var string
     */
    protected $stream_regex = ',^
        php://filter/
        (?P<mode>:?read=|write=)?  # The resource open mode
        (?P<filters>.*?)           # The resource registered filters
        /resource=(?P<resource>.*) # The resource path
        $,ix';

    /**
     * Charset Encoding for the CSV
     *
     * @var string
     */
    protected $input_encoding = 'UTF-8';

    /**
     * Gets the source CSV encoding charset
     *
     * @return string
     */
    public function getInputEncoding()
    {
        return $this->input_encoding;
    }

    /**
     * Sets the CSV encoding charset
     *
     * @param string $str
     *
     * @return static
     */
    public function setInputEncoding($str)
    {
        $str = str_replace('_', '-', $str);
        $str = filter_var($str, FILTER_SANITIZE_STRING, ['flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH]);
        $str = trim($str);
        if ('' === $str) {
            throw new InvalidArgumentException('you should use a valid charset');
        }
        $this->input_encoding = strtoupper($str);

        return $this;
    }

    /**
     * stream filter mode getter
     *
     * @return int
     */
    public function getStreamFilterMode()
    {
        $this->assertStreamable();

        return $this->stream_filter_mode;
    }

    /**
     * Internal path setter
     *
     * The path must be an SplFileInfo object
     * an object that implements the `__toString` method
     * a path to a file
     *
     * @param \SplFileObject|string $path The file path
     */
    protected function initStreamFilter($path)
    {
        $this->stream_filters = [];
        if (!is_string($path)) {
            $this->stream_uri = null;
            return;
        }

        if (!preg_match($this->stream_regex, $path, $matches)) {
            $this->stream_uri = $path;
            return;
        }

        $this->stream_uri = $matches['resource'];
        $this->stream_filters = [];
        $filter_mode = $this->fetchStreamModeAsInt($matches['mode']);
        if (in_array($filter_mode, [STREAM_FILTER_ALL, $this->stream_filter_mode])) {
            $this->stream_filters = array_map('urldecode', explode('|', $matches['filters']));
        }
    }

    /**
     * Get the stream mode
     *
     * @param string $mode
     *
     * @return int
     */
    protected function fetchStreamModeAsInt($mode)
    {
        $mode = strtolower($mode);
        $mode = rtrim($mode, '=');
        if ('write' == $mode) {
            return STREAM_FILTER_WRITE;
        }

        if ('read' == $mode) {
            return STREAM_FILTER_READ;
        }

        return STREAM_FILTER_ALL;
    }

    /**
     * Check if the trait methods can be used
     *
     * @throws LogicException If the API can not be use
     */
    protected function assertStreamable()
    {
        if (!is_string($this->stream_uri)) {
            throw new LogicException('The stream filter API can not be used');
        }
    }

    /**
     * Tells whether the stream filter capabilities can be used
     *
     * @return bool
     */
    public function isActiveStreamFilter()
    {
        return is_string($this->stream_uri);
    }

    /**
     * append a stream filter
     *
     * @param string $filter_name a string or an object that implements the '__toString' method
     *
     * @return $this
     */
    public function appendStreamFilter($filter_name)
    {
        $this->assertStreamable();
        $this->stream_filters[] = $this->sanitizeStreamFilter($filter_name);

        return $this;
    }

    /**
     * prepend a stream filter
     *
     * @param string $filter_name a string or an object that implements the '__toString' method
     *
     * @return $this
     */
    public function prependStreamFilter($filter_name)
    {
        $this->assertStreamable();
        array_unshift($this->stream_filters, $this->sanitizeStreamFilter($filter_name));

        return $this;
    }

    /**
     * Sanitize the stream filter name
     *
     * @param string $filter_name the stream filter name
     *
     * @return string
     */
    protected function sanitizeStreamFilter($filter_name)
    {
        return urldecode($this->validateString($filter_name));
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
    abstract protected function validateString($str);

    /**
     * Return the filter path
     *
     * @return string
     */
    protected function getStreamFilterPath()
    {
        $this->assertStreamable();
        $filters = $this->getStreamFilters();
        if (empty($filters)) {
            return $this->stream_uri;
        }

        return 'php://filter/'
            .$this->getStreamFilterPrefix()
            .implode('|', array_map('urlencode', $filters))
            .'/resource='.$this->stream_uri;
    }

    /**
     * Return the registered stream filters
     *
     * @return string[]
     */
    protected function getStreamFilters()
    {
        if (STREAM_FILTER_READ === $this->stream_filter_mode
            && in_array('convert.iconv.*', stream_get_filters(), true)
            && 'UTF-8' !== $this->input_encoding
        ) {
            $filters = $this->stream_filters;
            $filters[] = $this->sanitizeStreamFilter('convert.iconv.'.$this->input_encoding.'/UTF-8//TRANSLIT');
            return $filters;
        }

        return $this->stream_filters;
    }

    /**
     * Return PHP stream filter prefix
     *
     * @return string
     */
    protected function getStreamFilterPrefix()
    {
        if (STREAM_FILTER_READ === $this->stream_filter_mode) {
            return 'read=';
        }

        return 'write=';
    }
}
