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

use InvalidArgumentException;
use SplFileInfo;
use League\Csv\AbstractCsv;

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
     * prepend a stream filter
     *
     * @param mixed $filter_name a string or an object that implements the '__toString' method
     *
     * @return self
     *
     * @throws \InvalidArgumentException If what you try to add is invalid
     */
    public function prependStreamFilter($filter_name)
    {
        if (AbstractCsv::isValidString($filter_name)) {
            array_unshift($this->stream_filters, (string) $filter_name);

            return $this;
        }

        throw new InvalidArgumentException(
            'you must submit a string, or a method that implements the `__toString method`'
        );
    }

    /**
     * append a stream filter
     *
     * @param mixed $filter_name a string or an object that implements the '__toString' method
     *
     * @return self
     *
     * @throws \InvalidArgumentException If what you try to add is invalid
     */
    public function appendStreamFilter($filter_name)
    {
        if (AbstractCsv::isValidString($filter_name)) {
            $this->stream_filters[] = (string) $filter_name;

            return $this;
        }

        throw new InvalidArgumentException(
            'you must submit a string, or a method that implements the `__toString method`'
        );
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
     * @param mixed $path a SplFileInfo object or a string
     *
     * @return string
     */
    protected function getStreamFilterPath($path)
    {
        if ($path instanceof SplFileInfo) {
            $path = $path->getRealPath();
        }
        $path = trim($path);
        if (! is_string($path) || empty($path)) {
            return null;
        } elseif (! $this->stream_filters) {
            return $path;
        }

        $prefix = '';
        if (STREAM_FILTER_READ == $this->stream_filter_mode) {
            $prefix = 'read=';
        } elseif (STREAM_FILTER_WRITE == $this->stream_filter_mode) {
            $prefix = 'write=';
        }

        return 'php://filter/'.$prefix.implode('|', $this->stream_filters).'/resource='.$path;
    }
}
