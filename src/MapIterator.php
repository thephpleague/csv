<?php

/**
 * League.Csv (https://csv.thephpleague.com)
 *
 * (c) Ignace Nyamagana Butera <nyamsprod@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace League\Csv;

use IteratorIterator;
use Traversable;

/**
 * Map value from an iterator before yielding.
 *
 * @internal used internally to modify CSV content
 */
class MapIterator extends IteratorIterator
{
    /**
     * The callback to apply on all InnerIterator current value.
     *
     * @var callable
     */
    protected $callable;

    /**
     * New instance.
     */
    public function __construct(Traversable $iterator, callable $callable)
    {
        parent::__construct($iterator);
        $this->callable = $callable;
    }

    /**
     * @return mixed The value of the current element.
     */
    public function current()
    {
        return ($this->callable)(parent::current(), $this->key());
    }
}
