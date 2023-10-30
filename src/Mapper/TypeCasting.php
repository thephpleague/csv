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

namespace League\Csv\Mapper;

/**
 * @template TValue
 */
interface TypeCasting
{
    /**
     * @throws TypeCastingFailed
     *
     * @return TValue
     */
    public function toVariable(?string $value, string $type): mixed;
}
