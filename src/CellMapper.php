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

namespace League\Csv;

use League\Csv\TypeCasting\TypeCasting;
use ReflectionMethod;
use ReflectionProperty;

/**
 * @internal
 */
final class CellMapper
{
    public function __construct(
        public readonly int $offset,
        private readonly ReflectionMethod|ReflectionProperty $accessor,
        private readonly TypeCasting $cast,
    ) {
    }

    public function __invoke(object $object, ?string $value): void
    {
        $type = (string) match (true) {
            $this->accessor instanceof ReflectionMethod => $this->accessor->getParameters()[0]->getType(),
            $this->accessor instanceof ReflectionProperty => $this->accessor->getType(),
        };

        $value = $this->cast->toVariable($value, $type);

        match (true) {
            $this->accessor instanceof ReflectionMethod => $this->accessor->invoke($object, $value),
            $this->accessor instanceof ReflectionProperty => $this->accessor->setValue($object, $value),
        };
    }
}
