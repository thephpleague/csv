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

use function filter_var;
use function str_starts_with;

final class CastToBool implements TypeCasting
{
    private readonly bool $isNullable;
    private readonly Type $type;

    public function __construct(
        string $propertyType,
        private readonly ?bool $default = null
    ) {
        $type = Type::tryFromPropertyType($propertyType);
        if (null === $type || !$type->isOneOf(Type::Mixed, Type::Bool, Type::True, Type::False)) {
            throw new MappingFailed('The property type `'.$propertyType.'` is not supported; a `bool` type is required.');
        }

        $this->type = $type;
        $this->isNullable = Type::Mixed->equals($type) || str_starts_with($propertyType, '?');
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
}
