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

use ReflectionAttribute;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionProperty;
use ValueError;

use function strtolower;
use function substr;

final class TypeCastInfo
{
    public function __construct(
        public readonly int|string $source,
        public readonly TypeCastingTargetType $targetType,
        public readonly string $targetName,
        public readonly ?string $targetMethodName,
    ) {
    }

    public static function fromAccessor(ReflectionMethod|ReflectionProperty|ReflectionParameter $accessor): self
    {
        if ($accessor instanceof ReflectionMethod) {
            $accessor = $accessor->getParameters()[0] ?? null;
            if (null === $accessor) {
                throw new ValueError('The method must contain at least one parameter in its signature.');
            }
        }

        return $accessor instanceof ReflectionProperty
            ? self::fromProperty($accessor)
            : self::fromMethodFirstArgument($accessor);
    }

    public static function fromProperty(ReflectionProperty $accessor): self
    {
        $attributes = $accessor->getAttributes(MapCell::class, ReflectionAttribute::IS_INSTANCEOF);
        $source = [] === $attributes ? $accessor->getName() : ($attributes[0]->newInstance()->column ?? $accessor->getName());

        return new self(
            $source,
            TypeCastingTargetType::PropertyName,
            $accessor->getName(),
            null
        );
    }

    public static function fromMethodFirstArgument(ReflectionParameter $accessor): self
    {
        $method = $accessor->getDeclaringFunction();

        return new self(
            self::resolveSource($method),
            TypeCastingTargetType::MethodFirstArgument,
            $accessor->getName(),
            $method->getName(),
        );
    }

    private static function resolveSource(ReflectionFunctionAbstract $method): int|string
    {
        $attributes = $method->getAttributes(MapCell::class, ReflectionAttribute::IS_INSTANCEOF);
        if ([] === $attributes) {
            return self::getColumnName($method);
        }

        $name = $attributes[0]->newInstance()->column;
        if (null !== $name) {
            return $name;
        }

        return self::getColumnName($method);
    }

    private static function getColumnName(ReflectionFunctionAbstract $method): string
    {
        $name = $method->getName();
        if (!str_starts_with($name, 'set')) {
            throw new ValueError('The method `'.$name.'` has not Mapping information and does not start with `set`.');
        }

        return strtolower($name[3]).substr($name, 4);
    }
}
