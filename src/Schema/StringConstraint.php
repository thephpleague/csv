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

interface StringConstraint
{
    public function apply(string $value): ?string;
    /**
     * @return non-empty-string
     */
    public function fieldTypeName(): string;
}
