<?php
/**
* This file is part of the League.csv library
*
* @license http://opensource.org/licenses/MIT
* @link https://github.com/thephpleague/csv/
* @version 6.0.0
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
 * @since  5.5.0
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
     * @var integer
     */
    protected $stream_filter_mode = STREAM_FILTER_ALL;

    /**
     *the real path
     *
     * @var string the real path to the file
     *
     */
    protected $stream_real_path;

    /**
     * Internal path setter
     *
     * The path must be an SplFileInfo object
     * an object that implements the `__toString` method
     * a path to a file
     *
     * @param \SplFileObject|string $path The file path
     *
     * @return void
     */
    protected function initStreamFilter($path)
    {
        $this->stream_filters = [];
        $this->stream_real_path = null;
        if (! is_string($path)) {
            return;
        }

        $this->stream_real_path = $path;

        //if we are submitting a filter meta wrapper
        //we extract and inject the mode, the filter and the path
        if (preg_match(
            ',^php://filter/(?P<mode>:?read=|write=)?(?P<filters>.*?)/resource=(?P<resource>.*)$,i',
            $path,
            $matches
        )) {
            $matches['mode'] = strtolower($matches['mode']);
            $mode = STREAM_FILTER_ALL;
            if ('write=' == $matches['mode']) {
                $mode = STREAM_FILTER_WRITE;
            } elseif ('read=' == $matches['mode']) {
                $mode = STREAM_FILTER_READ;
            }
            $this->stream_filter_mode = $mode;
            $this->stream_real_path = $matches['resource'];
            $this->stream_filters = explode('|', $matches['filters']);
        }

    }

    /**
     * Check if the trait methods can be used
     *
     * @return void
     *
     * @throws \LogicException If the API can not be use
     */
    protected function checkStreamApiAvailability()
    {
        if (! is_string($this->stream_real_path)) {
            throw new LogicException('The stream filter API can not be used');
        }
    }

    /**
     * Tells whether the stream filter capabilities can be used
     *
     * @return boolean
     */
    public function isActiveStreamFilter()
    {
        return is_string($this->stream_real_path);
    }

    /**
     * stream filter mode Setter
     *
     * Set the new Stream Filter mode and remove all
     * previously attached stream filters
     *
     * @param integer $mode
     *
     * @return static
     *
     * @throws \OutOfBoundsException If the mode is invalid
     */
    public function setStreamFilterMode($mode)
    {
        $this->checkStreamApiAvailability();
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
     * @return integer
     */
    public function getStreamFilterMode()
    {
        $this->checkStreamApiAvailability();

        return $this->stream_filter_mode;
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
        $this->checkStreamApiAvailability();
        $filter_name = (string) $filter_name;

        return trim($filter_name);
    }

    /**
     * append a stream filter
     *
     * @param string $filter_name a string or an object that implements the '__toString' method
     *
     * @return static
     */
    public function appendStreamFilter($filter_name)
    {
        $this->checkStreamApiAvailability();
        $this->stream_filters[] = $this->sanitizeStreamFilter($filter_name);

        return $this;
    }

    /**
     * prepend a stream filter
     *
     * @param string $filter_name a string or an object that implements the '__toString' method
     *
     * @return static
     */
    public function prependStreamFilter($filter_name)
    {
        $this->checkStreamApiAvailability();
        array_unshift($this->stream_filters, $this->sanitizeStreamFilter($filter_name));

        return $this;
    }

    /**
     * Detect if the stream filter is already present
     *
     * @param string $filter_name
     *
     * @return boolean
     */
    public function hasStreamFilter($filter_name)
    {
        $this->checkStreamApiAvailability();

        return false !== array_search($filter_name, $this->stream_filters, true);
    }

    /**
     * Remove a filter from the collection
     *
     * @param string $filter_name
     *
     * @return static
     */
    public function removeStreamFilter($filter_name)
    {
        $this->checkStreamApiAvailability();
        $res = array_search($filter_name, $this->stream_filters, true);
        if (false !== $res) {
            unset($this->stream_filters[$res]);
        }

        return $this;
    }

    /**
     * Remove all registered stream filter
     *
     * @return static
     */
    public function clearStreamFilter()
    {
        $this->checkStreamApiAvailability();
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
        $this->checkStreamApiAvailability();
        if (! $this->stream_filters) {
            return $this->stream_real_path;
        }

        $prefix = '';
        if (STREAM_FILTER_READ == $this->stream_filter_mode) {
            $prefix = 'read=';
        } elseif (STREAM_FILTER_WRITE == $this->stream_filter_mode) {
            $prefix = 'write=';
        }

        return 'php://filter/'.$prefix.implode('|', $this->stream_filters).'/resource='.$this->stream_real_path;
    }
}
