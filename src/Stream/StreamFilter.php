<?php
/**
* League.csv - A CSV data manipulation library
*
* @author Ignace Nyamagana Butera <nyamsprod@gmail.com>
* @copyright 2014 Ignace Nyamagana Butera
* @link https://github.com/thephpleague/csv/
* @license http://opensource.org/licenses/MIT
* @version 5.5.0
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

use SplFileInfo;
use SplTempFileObject;

use InvalidArgumentException;
use RuntimeException;

/**
 *  A Trait to add ease manipulation Stream Filters
 *
 * @package League.csv
 * @since  5.5.0
 *
 */
trait StreamFilter
{

    /**
     *the real path
     *
     * @var string the real path to the file
     *
     */
    protected $real_path;

    /**
     * Internal path setter
     *
     * @param mixed $path can be a SplFileInfo object or the path to a file
     *
     * @return self
     *
     * @throws InvalidArgumentException If $path is invalid
     */
    protected function setPath($path)
    {
        if ($path instanceof SplTempFileObject) {
            $this->real_path = null;
            $this->path = $path;

            return $this;
        } elseif ($path instanceof SplFileInfo) {
            $this->path = $path;
            $this->real_path = $path->getPath().'/'.$path->getBasename();

            return $this;
        }
        if (! is_string($path)) {
            throw new InvalidArgumentException(
                'path must be a valid string or a `SplFileInfo` object'
            );
        }
        $this->real_path = trim($path);
        $this->path = $path;

        return $this;
    }

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
     * append a stream filter
     *
     * @param string $filter_name a string or an object that implements the '__toString' method
     *
     * @return self
     *
     * @throws \InvalidArgumentException If what you try to add is invalid
     * @throws \RuntimeException         If adding Stream Filter is not possible
     */
    public function addStreamFilter($filter_name)
    {
        if (is_null($this->real_path) || stripos($this->real_path, 'php://filter/') === 0) {
            throw new RuntimeException(
                'you can not add a stream filter to '.get_class($this).' instance'
            );
        } elseif (! is_string($filter_name)) {
            throw new InvalidArgumentException(
                'you must submit a valid the filtername'
            );
        }

        $filter_name = trim($filter_name);
        $this->stream_filters[] = $filter_name;

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
            return $this->real_path;
        }

        $prefix = '';
        if (STREAM_FILTER_READ == $this->stream_filter_mode) {
            $prefix = 'read=';
        } elseif (STREAM_FILTER_WRITE == $this->stream_filter_mode) {
            $prefix = 'write=';
        }

        return 'php://filter/'.$prefix.implode('|', $this->stream_filters).'/resource='.$this->real_path;
    }
}
