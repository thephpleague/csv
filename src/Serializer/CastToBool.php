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

use function filter_var;

/**
 * @implements TypeCasting<?bool>
 */
final class CastToBool implements TypeCasting
{
    private readonly bool $isNullable;
    private readonly Type $type;

    public function __construct(
        ReflectionProperty|ReflectionParameter $reflectionProperty,
        private readonly ?bool $default = null
    ) {
        [$this->type, $this->isNullable] = $this->init($reflectionProperty);
    }

    /**
     * @throws TypeCastingFailed
     */
    public function toVariable(?string $value): ?bool
    {
        $returnValue = match(true) {
            null !== $value => filter_var($value, Type::Bool->filterFlag()),
            $this->isNullable => $this->default,
            default => throw new TypeCastingFailed('The `null` value can not be cast to a boolean value.'),
        };

        return match (true) {
            Type::True->equals($this->type) && true !== $returnValue && !$this->isNullable,
            Type::False->equals($this->type) && false !== $returnValue && !$this->isNullable => throw new TypeCastingFailed('The value `'.$value.'` could not be cast to `'.$this->type->value.'`.'),
            default => $returnValue,
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

            if (null === $type && $found[0]->isOneOf(Type::Mixed, Type::Bool, Type::True, Type::False)) {
                $type = $found;
            }
        }

        if (null === $type) {
            throw new MappingFailed('`'.$reflectionProperty->getName().'` type is not supported; `mixed` or `bool` type is required.');
        }

        return [$type[0], $isNullable];
    }
}
