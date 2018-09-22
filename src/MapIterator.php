<?php

/**
 * League.Csv (https://csv.thephpleague.com).
 *
 * @author  Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @license https://github.com/thephpleague/csv/blob/master/LICENSE (MIT License)
 * @version 9.2.0
 * @link    https://github.com/thephpleague/csv
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
     * {@inheritdoc}
     */
    public function current()
    {
        return ($this->callable)(parent::current(), $this->key());
    }
}
