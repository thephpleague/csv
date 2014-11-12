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
namespace League\Csv\Config;

use LogicException;
use OutOfBoundsException;
use SplFileInfo;

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
        if (! is_string($path)) {
            $this->stream_uri     = null;
            $this->stream_filters = [];

            return;
        }

        $this->extractStreamSettings($path);
    }

    /**
     * Extract Available stream settings from $path
     *
     * @param string $path the file path
     */
    protected function extractStreamSettings($path)
    {
        if (! preg_match(
            ',^php://filter/(?P<mode>:?read=|write=)?(?P<filters>.*?)/resource=(?P<resource>.*)$,i',
            $path,
            $matches
        )) {
            $this->stream_uri     = $path;
            $this->stream_filters = [];

            return;
        }
        $matches['mode'] = strtolower($matches['mode']);
        $mode = STREAM_FILTER_ALL;
        if ('write=' == $matches['mode']) {
            $mode = STREAM_FILTER_WRITE;
        } elseif ('read=' == $matches['mode']) {
            $mode = STREAM_FILTER_READ;
        }
        $this->stream_filter_mode = $mode;
        $this->stream_uri         = $matches['resource'];
        $this->stream_filters     = explode('|', $matches['filters']);
    }

    /**
     * Check if the trait methods can be used
     *
     * @throws \LogicException If the API can not be use
     */
    protected function assertStreamable()
    {
        if (! is_string($this->stream_uri)) {
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
     * @throws \OutOfBoundsException If the mode is invalid
     *
     * @return $this
     */
    public function setStreamFilterMode($mode)
    {
        $this->assertStreamable();
        if (! in_array($mode, [STREAM_FILTER_ALL, STREAM_FILTER_READ, STREAM_FILTER_WRITE])) {
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
        $this->assertStreamable();
        $filter_name = (string) $filter_name;

        return trim($filter_name);
    }

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

        return false !== array_search($filter_name, $this->stream_filters, true);
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
        $res = array_search($filter_name, $this->stream_filters, true);
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
        if (! $this->stream_filters) {
            return $this->stream_uri;
        }

        $prefix = '';
        if (STREAM_FILTER_READ == $this->stream_filter_mode) {
            $prefix = 'read=';
        } elseif (STREAM_FILTER_WRITE == $this->stream_filter_mode) {
            $prefix = 'write=';
        }

        return 'php://filter/'.$prefix.implode('|', $this->stream_filters).'/resource='.$this->stream_uri;
    }
}
