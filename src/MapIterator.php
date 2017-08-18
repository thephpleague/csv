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

namespace League\Csv;

use IteratorIterator;
use Traversable;

/**
 * Map value from an iterator before yielding
 *
 * @package  League.csv
 * @since    3.3.0
 * @author   Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @internal used internally to modify CSV content
 */
class MapIterator extends IteratorIterator
{
    /**
     * The callback to apply on all InnerIterator current value
     *
     * @var callable
     */
    protected $callable;

    /**
     * The Constructor
     *
     * @param Traversable $iterator
     * @param callable    $callable
     */
    public function __construct(Traversable $iterator, callable $callable)
    {
        parent::__construct($iterator);
        $this->callable = $callable;
    }

    /**
     * @inheritdoc
     */
    public function current()
    {
        return ($this->callable)(parent::current(), $this->key());
    }
}
