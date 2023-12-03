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

#[Attribute(Attribute::TARGET_CLASS)]
final class AfterMapping
{
    /** @var array<string> $methods */
    public readonly array $methods;

    public function __construct(string ...$methods)
    {
        $this->methods = $methods;
    }
}
