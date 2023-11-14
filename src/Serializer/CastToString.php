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

use ReflectionParameter;
use ReflectionProperty;

/**
 * @implements TypeCasting<string|null>
 */
final class CastToString implements TypeCasting
{
    private readonly bool $isNullable;
    private readonly Type $type;

    public function __construct(
        ReflectionProperty|ReflectionParameter $reflectionProperty,
        private readonly ?string $default = null
    ) {
        [$this->type, $this->isNullable] = $this->init($reflectionProperty);
    }

    /**
     * @throws TypeCastingFailed
     */
    public function toVariable(?string $value): ?string
    {
        $returnedValue = match(true) {
            null !== $value => $value,
            $this->isNullable => $this->default,
            default => throw new TypeCastingFailed('The `null` value can not be cast to a string.'),
        };

        return match (true) {
            Type::Null->equals($this->type) && null !== $returnedValue => throw new TypeCastingFailed('The value `'.$value.'` could not be cast to `'.$this->type->value.'`.'),
            default => $returnedValue,
        };
    }

    /**
     * @return array{0:Type, 1:bool}
     */
    private function init(ReflectionProperty|ReflectionParameter $reflectionProperty): array
    {
        $type = null;
        $isNullable = false;
        foreach (Type::list($reflectionProperty) as $found) {
            if (!$isNullable && $found[1]->allowsNull()) {
                $isNullable = true;
            }

            if (null === $type && $found[0]->isOneOf(Type::String, Type::Mixed, Type::Null)) {
                $type = $found;
            }
        }

        if (null === $type) {
            throw new MappingFailed('`'.$reflectionProperty->getName().'` type is not supported; `mixed`, `string` or `null` type is required.');
        }

        return [$type[0], $isNullable];
    }
}
