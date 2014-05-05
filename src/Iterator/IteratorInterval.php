<?php
/**
* This file is part of the League.csv library
*
* @license http://opensource.org/licenses/MIT
* @version 5.5.0
* @package League.csv
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
namespace League\Csv\Iterator;

use InvalidArgumentException;
use Iterator;
use LimitIterator;

/**
 *  A Trait to Set a LimitIterator object
 *
 * @package League.csv
 * @since  4.2.1
 *
 */
trait IteratorInterval
{
    /**
     * iterator Offset
     *
     * @var integer
     */
    protected $iterator_offset = 0;

    /**
     * iterator maximum length
     *
     * @var integer
     */
    protected $iterator_limit = -1;

    /**
     * Set LimitIterator Offset
     *
     * @param $offset
     *
     * @return self
     */
    public function setOffset($offset = 0)
    {
        if (false === filter_var($offset, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]])) {
            throw new InvalidArgumentException('the offset must be a positive integer or 0');
        }
        $this->iterator_offset = $offset;

        return $this;
    }

    /**
     * Set LimitInterator Count
     *
     * @param integer $limit
     *
     * @return self
     */
    public function setLimit($limit = -1)
    {
        if (false === filter_var($limit, FILTER_VALIDATE_INT, ['options' => ['min_range' => -1]])) {
            throw new InvalidArgumentException('the limit must an integer greater or equals to -1');
        }
        $this->iterator_limit = $limit;

        return $this;
    }

    /**
    * Sort the Iterator
    *
    * @param \Iterator $iterator
    *
    * @return \LimitIterator
    */
    protected function applyIteratorInterval(Iterator $iterator)
    {
        if (0 == $this->iterator_offset && -1 == $this->iterator_limit) {
            return $iterator;
        }
        $offset = $this->iterator_offset;
        $limit = $this->iterator_limit;

        $this->iterator_limit = -1;
        $this->iterator_offset = 0;

        return new LimitIterator($iterator, $offset, $limit);
    }
}
