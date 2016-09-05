<?php
/**
* This file is part of the League.csv library
*
* @license http://opensource.org/licenses/MIT
* @link https://github.com/thephpleague/csv/
* @version 8.1.1
* @package League.csv
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
namespace League\Csv\Modifier;

use LogicException;
use OutOfBoundsException;

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
        $this->stream_filters = array_map('urldecode', explode('|', $matches['filters']));
        $this->stream_filter_mode = $this->fetchStreamModeAsInt($matches['mode']);
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
     * stream filter mode Setter
     *
     * Set the new Stream Filter mode and remove all
     * previously attached stream filters
     *
     * @param int $mode
     *
     * @throws OutOfBoundsException If the mode is invalid
     *
     * @return $this
     */
    public function setStreamFilterMode($mode)
    {
        $this->assertStreamable();
        if (!in_array($mode, [STREAM_FILTER_ALL, STREAM_FILTER_READ, STREAM_FILTER_WRITE])) {
            throw new OutOfBoundsException('the $mode should be a valid `STREAM_FILTER_*` constant');
        }

        $this->stream_filter_mode = $mode;
        $this->stream_filters = [];

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
     * @inheritdoc
     */
    abstract public function validateString($str);

    /**
     * Detect if the stream filter is already present
     *
     * @param string $filter_name
     *
     * @return bool
     */
    public function hasStreamFilter($filter_name)
    {
        $this->assertStreamable();

        return false !== array_search(urldecode($filter_name), $this->stream_filters, true);
    }

    /**
     * Remove a filter from the collection
     *
     * @param string $filter_name
     *
     * @return $this
     */
    public function removeStreamFilter($filter_name)
    {
        $this->assertStreamable();
        $res = array_search(urldecode($filter_name), $this->stream_filters, true);
        if (false !== $res) {
            unset($this->stream_filters[$res]);
        }

        return $this;
    }

    /**
     * Remove all registered stream filter
     *
     * @return $this
     */
    public function clearStreamFilter()
    {
        $this->assertStreamable();
        $this->stream_filters = [];

        return $this;
    }

    /**
     * Return the filter path
     *
     * @return string
     */
    protected function getStreamFilterPath()
    {
        $this->assertStreamable();
        if (!$this->stream_filters) {
            return $this->stream_uri;
        }

        return 'php://filter/'
            .$this->getStreamFilterPrefix()
            .implode('|', array_map('urlencode', $this->stream_filters))
            .'/resource='.$this->stream_uri;
    }

    /**
     * Return PHP stream filter prefix
     *
     * @return string
     */
    protected function getStreamFilterPrefix()
    {
        if (STREAM_FILTER_READ == $this->stream_filter_mode) {
            return 'read=';
        }

        if (STREAM_FILTER_WRITE == $this->stream_filter_mode) {
            return 'write=';
        }

        return '';
    }
}
