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

/**
 * @template T
 */
interface FieldParser
{
    /**
     * Try to parse and normalize the value according
     * to the detector handled type. If the value can
     * not be parse null is returned.
     *
     * @return ?T
     */
    public function parse(mixed $value): mixed;
}
