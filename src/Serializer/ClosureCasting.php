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

use function array_key_exists;
use function class_exists;

/**
 * @template TValue
 */
final class ClosureCasting implements TypeCasting
{
    /** @var array<string, Closure(?string, bool, mixed...): mixed> */
    private static array $casters = [];

    private string $type;
    private readonly bool $isNullable;
    /** @var Closure(?string, bool, mixed...): mixed */
    private Closure $closure;
    private array $options;
    private string $message;

    public function __construct(ReflectionProperty|ReflectionParameter $reflectionProperty)
    {
        [$this->type, $this->isNullable] = self::resolve($reflectionProperty);

        $this->message = match (true) {
            $reflectionProperty instanceof ReflectionParameter => 'The method `'.$reflectionProperty->getDeclaringClass()?->getName().'::'.$reflectionProperty->getDeclaringFunction()->getName().'` argument `'.$reflectionProperty->getName().'` must be typed with a supported type.',
            $reflectionProperty instanceof ReflectionProperty => 'The property `'.$reflectionProperty->getDeclaringClass()->getName().'::'.$reflectionProperty->getName().'` must be typed with a supported type.',
        };

        $this->closure = fn (?string $value, bool $isNullable, mixed ...$arguments): ?string => $value;
    }

    /**
     * @throws MappingFailed
     */
    public function setOptions(string $type = null, mixed ...$options): void
    {
        if (Type::Mixed->value === $this->type && null !== $type) {
            $this->type = $type;
        }

        if (!array_key_exists($this->type, self::$casters)) {
            throw new MappingFailed($this->message);
        }

        $this->closure = self::$casters[$this->type];
        $this->options = $options;
    }

    /**
     * @return TValue
     */
    public function toVariable(?string $value): mixed
    {
        try {
            return ($this->closure)($value, $this->isNullable, ...$this->options);
        } catch (Throwable $exception) {
            if ($exception instanceof TypeCastingFailed) {
                throw $exception;
            }

            if (null === $value) {
                throw TypeCastingFailed::dueToNotNullableType($this->type, $exception);
            }

            throw TypeCastingFailed::dueToInvalidValue(match (true) {
                '' === $value => 'empty string',
                default => $value,
            }, $this->type, $exception);
        }
    }

    /**
     * @param Closure(?string, bool, mixed...): TValue $closure
     */
    public static function register(string $type, Closure $closure): void
    {
        self::$casters[$type] = match (true) {
            class_exists($type),
            interface_exists($type),
            Type::tryFrom($type) instanceof Type => $closure,
            default => throw new MappingFailed('The `'.$type.'` could not be register.'),
        };
    }

    public static function unregister(string $type): bool
    {
        if (!array_key_exists($type, self::$casters)) {
            return false;
        }

        unset(self::$casters[$type]);

        return true;
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
     * @return array{0:string, 1:bool}
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

        return $type instanceof ReflectionNamedType ? [$type->getName(), $isNullable] : throw new MappingFailed(match (true) {
            $reflectionProperty instanceof ReflectionParameter => 'The method `'.$reflectionProperty->getDeclaringClass()?->getName().'::'.$reflectionProperty->getDeclaringFunction()->getName().'` argument `'.$reflectionProperty->getName().'` must be typed with a supported type.',
            $reflectionProperty instanceof ReflectionProperty => 'The property `'.$reflectionProperty->getDeclaringClass()->getName().'::'.$reflectionProperty->getName().'` must be typed with a supported type.',
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
