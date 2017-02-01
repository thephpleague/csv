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
     * The CSV stream filter mode
     */
    protected $stream_filter_mode;

    /**
     * collection of stream filters
     *
     * @var array
     */
    protected $stream_filters = [];

    /**
     * Check if the trait methods can be used
     *
     * @throws LogicException If the API can not be use
     */
    protected function assertStreamable()
    {
        if (!$this->isActiveStreamFilter()) {
            throw new LogicException('The stream filter API can not be used');
        }
    }

    /**
     * Tells whether the stream filter capabilities can be used
     *
     * @return bool
     */
    public function isActiveStreamFilter(): bool
    {
        return $this->document instanceof StreamIterator;
    }

    /**
     * stream filter mode getter
     *
     * @return int
     */
    public function getStreamFilterMode(): int
    {
        return $this->stream_filter_mode;
    }

    /**
     * append a stream filter
     *
     * @param string $filter_name a string or an object that implements the '__toString' method
     *
     * @return $this
     */
    public function appendStreamFilter(string $filter_name): self
    {
        $this->assertStreamable();
        $this->stream_filters[] = $this->document->appendFilter($filter_name, $this->stream_filter_mode);

        return $this;
    }

    /**
     * prepend a stream filter
     *
     * @param string $filter_name a string or an object that implements the '__toString' method
     *
     * @return $this
     */
    public function prependStreamFilter(string $filter_name): self
    {
        $this->assertStreamable();
        $this->stream_filters[] = $this->document->prependFilter($filter_name, $this->stream_filter_mode);

        return $this;
    }

    /**
     * Remove all registered stream filter
     *
     * @return $this
     */
    public function clearStreamFilter(): self
    {
        if (!$this->isActiveStreamFilter()) {
            return $this;
        }

        foreach ($this->stream_filters as $filter) {
            $this->document->removeFilter($filter);
        }

        $this->stream_filters = [];

        return $this;
    }
}
