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

use LogicException;
use ReflectionParameter;
use ReflectionProperty;
use Throwable;

final class MappingFailed extends LogicException implements SerializationFailed
{
    public static function dueToUnsupportedType(ReflectionProperty|ReflectionParameter $reflectionProperty): self
    {
        $suffix = 'is missing; register it using the `'.Denormalizer::class.'` class.';

        return new self(match (true) {
            $reflectionProperty instanceof ReflectionParameter => 'The type definition for the method `'.$reflectionProperty->getDeclaringClass()?->getName().'::'.$reflectionProperty->getDeclaringFunction()->getName().'` first argument `'.$reflectionProperty->getName().'` '.$suffix,
            $reflectionProperty instanceof ReflectionProperty => 'The property type definition for `'.$reflectionProperty->getDeclaringClass()->getName().'::'.$reflectionProperty->getName().'` '.$suffix,
        });
    }

    public static function dueToTypeCastingUnsupportedType(
        ReflectionProperty|ReflectionParameter $reflectionProperty,
        TypeCasting $typeCasting,
        string ...$types
    ): self {

        $suffix = 'is invalid; `'.implode('` or `', $types).'` type must be used with the `'.$typeCasting::class.'`.';

        return new self(match (true) {
            $reflectionProperty instanceof ReflectionParameter => 'The type for the method `'.$reflectionProperty->getDeclaringClass()?->getName().'::'.$reflectionProperty->getDeclaringFunction()->getName().'` first argument `'.$reflectionProperty->getName().'` '.$suffix,
            $reflectionProperty instanceof ReflectionProperty => 'The property type for `'.$reflectionProperty->getDeclaringClass()->getName().'::'.$reflectionProperty->getName().'` '.$suffix,
        });
    }

    public static function dueToInvalidCastingArguments(?Throwable $exception = null): self
    {
        return new self('Unable to load the casting mechanism. Please verify your casting arguments', 0, $exception);
    }

    public static function dueToInvalidTypeCastingClass(string $typeCaster): self
    {
        return new self('`'.$typeCaster.'` must be an resolvable class implementing the `'.TypeCasting::class.'` interface or a supported alias.');
    }
}
