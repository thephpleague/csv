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

namespace League\Csv\Serializer;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_PROPERTY)]
final class MapCell
{
    /**
     * @param class-string|string|null $cast
     */
    public function __construct(
        public readonly string|int|null $column = null,
        public readonly ?string $cast = null,
        public readonly array $options = [],
        public readonly bool $ignore = false,
        public readonly ?bool $convertEmptyStringToNull = null,
        public readonly ?bool $trimFieldValueBeforeCasting = false,
    ) {
    }
}
