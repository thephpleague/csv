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

use Closure;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;
use Throwable;

final class ClosureCasting implements TypeCasting
{
    /** @var array<string, Closure> */
    private static array $casters = [];

    private readonly string $type;
    private readonly bool $isNullable;
    private readonly Closure $closure;
    private readonly array $arguments;

    public function __construct(ReflectionProperty|ReflectionParameter $reflectionProperty, mixed ...$arguments)
    {
        [$type, $this->isNullable] = self::resolve($reflectionProperty);
        $this->type = $type->getName();
        $this->closure = self::$casters[$this->type];
        $this->arguments = $arguments;
    }

    public function toVariable(?string $value): mixed
    {
        try {
            return ($this->closure)($value, $this->isNullable, ...$this->arguments);
        } catch (Throwable $exception) {
            if ($exception instanceof TypeCastingFailed) {
                throw $exception;
            }

            $message = match (true) {
                '' === $value => 'Unable to cast the empty string to `'.$this->type.'`.',
                null === $value => 'Unable to cast the `null` value to `'.$this->type.'`.',
                default => 'Unable to cast the given string `'.$value.'` to `'.$this->type.'`',
            };

            throw new TypeCastingFailed(message: $message, previous: $exception);
        }
    }

    public static function register(string $type, Closure $closure): void
    {
        if (!class_exists($type) && !(Type::tryFrom($type)?->isBuiltIn() ?? false)) {
            throw new MappingFailed('The `'.$type.'` could not be register.');
        }

        self::$casters[$type] = $closure;
    }

    public static function unregister(string $type): void
    {
        unset(self::$casters[$type]);
    }

    public static function supports(ReflectionParameter|ReflectionProperty $reflectionProperty): bool
    {
        foreach (self::getTypes($reflectionProperty->getType()) as $type) {
            if (array_key_exists($type->getName(), self::$casters)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @throws MappingFailed
     *
     * @return array{0:ReflectionNamedType, 1:bool}
     */
    private static function resolve(ReflectionParameter|ReflectionProperty $reflectionProperty): array
    {
        $type = null;
        $isNullable = false;
        foreach (self::getTypes($reflectionProperty->getType()) as $foundType) {
            if (!$isNullable && $foundType->allowsNull()) {
                $isNullable = true;
            }

            if (null === $type && array_key_exists($foundType->getName(), self::$casters)) {
                $type = $foundType;
            }
        }

        return $type instanceof ReflectionNamedType ? [$type, $isNullable] : throw new MappingFailed(match (true) {
            $reflectionProperty instanceof ReflectionParameter => 'The setter method argument `'.$reflectionProperty->getName().'` must be typed with a supported type.',
            $reflectionProperty instanceof ReflectionProperty => 'The property `'.$reflectionProperty->getName().'` must be typed with a supported type.',
        });
    }

    /**
     * @return array<ReflectionNamedType>
     */
    private static function getTypes(?ReflectionType $type): array
    {
        return match (true) {
            $type instanceof ReflectionNamedType => [$type],
            $type instanceof ReflectionUnionType => array_filter(
                $type->getTypes(),
                fn (ReflectionType $innerType) => $innerType instanceof ReflectionNamedType
            ),
            default => [],
        };
    }
}
