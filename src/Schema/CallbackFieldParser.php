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

namespace League\Csv\Schema;

use Closure;

/**
 * @template T
 */
final class CallbackFieldParser implements FieldParser
{
    /** @var Closure(mixed): ?T */
    private Closure $callback;

    /**
     * @param (Closure(mixed): ?T)|(callable(mixed): ?T) $callback
     */
    public function __construct(Closure|callable $callback)
    {
        if (!$callback instanceof Closure) {
            $callback = $callback(...);
        }

        $this->callback = $callback;
    }

    /**
     * @returns ?T
     */
    public function parse(mixed $value): mixed
    {
        return ($this->callback)($value);
    }
}
