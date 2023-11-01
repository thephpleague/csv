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

namespace League\Csv\Serializer\Attribute;

use Attribute as PhpAttribute;

#[PhpAttribute(PhpAttribute::TARGET_METHOD | PhpAttribute::TARGET_PROPERTY)]
final class Cell
{
    /**
     * @param ?class-string $cast
     */
    public function __construct(
        public readonly string|int|null $offset = null,
        public readonly ?string $cast = null,
        public readonly array $castArguments = []
    ) {
    }
}
