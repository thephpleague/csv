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
declare(strict_types=1);

namespace League\Csv\Config;

use League\Csv\StreamIterator;
use LogicException;
use SplFileObject;

/**
 *  An abstract class to enable basic CSV manipulation
 *
 * @package League.csv
 * @since  9.0.0
 * @internal
 */
trait StreamTrait
{
    /**
     * The CSV document
     *
     * can be a StreamIterator object, a SplFileObject object or the string path to a file
     *
     * @var StreamIterator|SplFileObject
     */
    protected $document;

    /**
     * collection of stream filters
     *
     * @var array
     */
    protected $stream_filters = [];

    /**
     * The stream filter mode (read or write)
     */
    protected $stream_filter_mode;

    /**
     * Tells whether the stream filter capabilities can be used
     *
     * @return bool
     */
    public function isStream(): bool
    {
        return $this->document instanceof StreamIterator;
    }

    /**
     * Tell whether the specify stream filter is attach to the current stream
     *
     * @return bool
     */
    public function hasStreamFilter(string $filter_name): bool
    {
        return isset($this->stream_filters[$filter_name]);
    }

    /**
     * append a stream filter
     *
     * @param string $filter_name a string or an object that implements the '__toString' method
     *
     * @return $this
     */
    public function addStreamFilter(string $filter_name): self
    {
        if (!$this->document instanceof StreamIterator) {
            throw new LogicException('The stream filter API can not be used');
        }

        $this->stream_filters[$filter_name][] = $this->document->appendFilter(
            $filter_name,
            $this->stream_filter_mode
        );

        return $this;
    }

    /**
     * Remove all registered stream filter
     */
    protected function clearStreamFilter()
    {
        foreach (array_keys($this->stream_filters) as $filter_name) {
            $this->removeStreamFilter($filter_name);
        }

        $this->stream_filters = [];
    }

    /**
     * Remove all the stream filter with the same name
     *
     * @param string $filter_name the stream filter name
     */
    protected function removeStreamFilter(string $filter_name)
    {
        foreach ($this->stream_filters[$filter_name] as $filter) {
            $this->document->removeFilter($filter);
        }

        unset($this->stream_filters[$filter_name]);
    }
}
