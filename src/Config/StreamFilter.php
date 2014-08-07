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

use SplFileInfo;
use SplTempFileObject;
use InvalidArgumentException;
use RuntimeException;
use OutOfBoundsException;

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
     * @param \SplFileInfo|string $path can be a SplFileInfo object or the path to a file
     *
     * @return self
     *
     * @throws InvalidArgumentException If $path is invalid
     */
    protected function initStreamFilter($path)
    {
        if ($path instanceof SplTempFileObject) {
            $this->stream_real_path = null;

            return $this;

        } elseif ($path instanceof SplFileInfo) {
            //$path->getRealPath() returns false for php stream wrapper
            $path = $path->getPath().'/'.$path->getBasename();
        }

        $path = trim($path);
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

            return $this;
        }

        $this->stream_real_path = $path;
        $this->stream_filters = [];

        return $this;
    }

    /**
     * stream filter mode Setter
     *
     * Set the new Stream Filter mode and remove all
     * previously attached stream filters
     *
     * @param integer $mode
     *
     * @return self
     */
    public function setStreamFilterMode($mode)
    {
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
        return $this->stream_filter_mode;
    }

    /**
     * Sanitize the stream filter name
     *
     * @param string $filter_name the stream filter name
     *
     * @return string
     *
     * @throws \InvalidArgumentException If $filter_name is not a string
     */
    protected function sanitizeStreamFilter($filter_name)
    {
        if (! is_string($filter_name)) {
            throw new InvalidArgumentException(
                'the filtername variable must be a string'
            );
        }

        return trim($filter_name);
    }

    /**
     * append a stream filter
     *
     * @param string $filter_name a string or an object that implements the '__toString' method
     *
     * @return self
     *
     * @throws \InvalidArgumentException If what you try to add is invalid
     * @throws \RuntimeException         If adding Stream Filter is not possible
     */
    public function appendStreamFilter($filter_name)
    {
        if (is_null($this->stream_real_path)) {
            throw new RuntimeException(
                'no stream path found, you can not append a stream filter'
            );
        }
        $this->stream_filters[] = $this->sanitizeStreamFilter($filter_name);

        return $this;
    }

    /**
     * prepend a stream filter
     *
     * @param string $filter_name a string or an object that implements the '__toString' method
     *
     * @return self
     *
     * @throws \InvalidArgumentException If what you try to add is invalid
     * @throws \RuntimeException         If adding Stream Filter is not possible
     */
    public function prependStreamFilter($filter_name)
    {
        if (is_null($this->stream_real_path)) {
            throw new RuntimeException(
                'no stream path found, you can not prepend a stream filter'
            );
        }

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
        return false !== array_search($filter_name, $this->stream_filters, true);
    }

    /**
     * Remove a filter from the collection
     *
     * @param string $filter_name
     *
     * @return self
     */
    public function removeStreamFilter($filter_name)
    {
        $res = array_search($filter_name, $this->stream_filters, true);
        if (false !== $res) {
            unset($this->stream_filters[$res]);
        }

        return $this;
    }

    /**
     * Remove all registered stream filter
     *
     * @return self
     */
    public function clearStreamFilter()
    {
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
