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

use ReflectionException;
use ReflectionMethod;
use ReflectionProperty;

/**
 * @internal
 */
final class PropertySetter
{
    public function __construct(
        private readonly ReflectionMethod|ReflectionProperty $accessor,
        public readonly int $offset,
        private readonly TypeCasting $cast,
    ) {
    }

    /**
     * @throws ReflectionException
     */
    public function __invoke(object $object, ?string $value): void
    {
        $typeCastedValue = $this->cast->toVariable($value);

        match (true) {
            $this->accessor instanceof ReflectionMethod => $this->accessor->invoke($object, $typeCastedValue),
            $this->accessor instanceof ReflectionProperty => $this->accessor->setValue($object, $typeCastedValue),
        };
    }
}
