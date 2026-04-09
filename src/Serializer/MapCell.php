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
use Closure;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_PROPERTY)]
final readonly class MapCell
{
    /**
     * @param Closure|class-string|string|null $cast
     */
    public function __construct(
        public string|int|null $column = null,
        public Closure|string|null $cast = null,
        public array $options = [],
        public bool $ignore = false,
        public ?bool $convertEmptyStringToNull = null,
        public ?bool $trimFieldValueBeforeCasting = false,
    ) {
    }
}
