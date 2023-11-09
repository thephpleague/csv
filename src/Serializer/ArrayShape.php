<?php

/**
 * League.Csv (https://csv.thephpleague.com)
 *
 * (c) Ignace Nyamagana Butera <nyamsprod@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace League\Csv\Serializer;

enum ArrayShape: string
{
    case List = 'list';
    case Csv = 'csv';
    case Json = 'json';

    public function equals(mixed $value): bool
    {
        return $value instanceof self
            && $value === $this;
    }

    public function isOneOf(self ...$types): bool
    {
        return in_array($this, $types, true);
    }
}
