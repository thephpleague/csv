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

use function str_starts_with;

/**
 * @implements TypeCasting<string|null>
 */
final class CastToString implements TypeCasting
{
    private readonly bool $isNullable;
    private readonly Type $type;

    public function __construct(
        string $propertyType,
        private readonly ?string $default = null
    ) {
        $type = Type::tryFromPropertyType($propertyType);
        if (null === $type || !$type->isOneOf(Type::Mixed, Type::String, Type::Null)) {
            throw new MappingFailed('The property type `'.$propertyType.'` is not supported; a `string` or `null` type is required.');
        }

        $this->type = $type;
        $this->isNullable = $type->isOneOf(Type::Mixed, Type::Null) || str_starts_with($propertyType, '?');
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
}
