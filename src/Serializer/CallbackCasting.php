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
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;
use Throwable;

use function array_key_exists;
use function class_exists;

/**
 * @internal Container for registering Closure as type and/or type alias casting
 * @template TValue
 */
final class CallbackCasting implements TypeCasting
{
    /** @var array<string, Closure(mixed, bool, mixed...): mixed> */
    private static array $types = [];
    /** @var array<string, array<string, Closure(mixed, bool, mixed...): mixed>> */
    private static array $aliases = [];

    private string $type;
    private readonly bool $isNullable;
    /** @var Closure(mixed, bool, mixed...): mixed */
    private Closure $callback;
    private array $options = [];
    private string $message;

    public function __construct(
        ReflectionProperty|ReflectionParameter $reflectionProperty,
        private readonly ?string $alias = null
    ) {
        [$this->type, $this->isNullable] = self::resolve($reflectionProperty);

        $this->message = match (true) {
            $reflectionProperty instanceof ReflectionParameter => 'The method `'.$reflectionProperty->getDeclaringClass()?->getName().'::'.$reflectionProperty->getDeclaringFunction()->getName().'` argument `'.$reflectionProperty->getName().'` must be typed with a supported type.',
            $reflectionProperty instanceof ReflectionProperty => 'The property `'.$reflectionProperty->getDeclaringClass()->getName().'::'.$reflectionProperty->getName().'` must be typed with a supported type.',
        };

        $this->callback = fn (mixed $value, bool $isNullable, mixed ...$arguments): mixed => $value;
    }

    /**
     * @throws MappingFailed
     */
    public function setOptions(?string $type = null, mixed ...$options): void
    {
        if (null === $this->alias) {
            if (Type::Mixed->value === $this->type && null !== $type) {
                $this->type = $type;
            }

            try {
                $this->callback = self::resolveTypeCallback($this->type); /* @phpstan-ignore-line */
                $this->options = $options;

                return;
            } catch (Throwable) {

            }

            throw new MappingFailed($this->message);
        }

        if (Type::Mixed->value === $this->type) {
            $this->type = self::aliases()[$this->alias];
        }

        $this->callback = self::resolveAliasCallback($this->type, $this->alias);
        $this->options = $options;
    }

    /**
     * @return TValue
     */
    public function toVariable(mixed $value): mixed
    {
        try {
            return ($this->callback)($value, $this->isNullable, ...$this->options);
        } catch (Throwable $exception) {
            ! $exception instanceof TypeCastingFailed || throw $exception;
            null !== $value || throw TypeCastingFailed::dueToNotNullableType($this->type, $exception);

            throw TypeCastingFailed::dueToInvalidValue(match (true) {
                '' === $value => 'empty string',
                default => $value,
            }, $this->type, $exception);
        }
    }

    /**
     * @param Closure(mixed, bool, mixed...): TValue $callback
     */
    public static function register(string $type, Closure $callback, ?string $alias = null): void
    {
        if (null === $alias) {
            self::$types[$type] = match (true) {
                class_exists($type),
                interface_exists($type),
                Type::tryFrom($type) instanceof Type => $callback,
                default => throw new MappingFailed('The `'.$type.'` could not be register.'),
            };

            return;
        }

        1 === preg_match('/^@\w+$/', $alias) || throw new MappingFailed("The alias `$alias` is invalid. It must start with an `@` character and contain alphanumeric (letters, numbers, regardless of case) plus underscore (_).");

        foreach (self::$aliases as $aliases) {
            foreach ($aliases as $registeredAlias => $__) {
                $alias !== $registeredAlias || throw new MappingFailed("The alias `$alias` is already registered. Please choose another name.");
            }
        }

        self::$aliases[$type][$alias] = match (true) {
            class_exists($type),
            interface_exists($type),
            Type::tryFrom($type) instanceof Type => $callback,
            default => throw new MappingFailed('The `'.$type.'` could not be register.'),
        };
    }

    public static function unregisterType(string $type): bool
    {
        if (!array_key_exists($type, self::$types)) {
            return false;
        }

        unset(self::$types[$type]);

        return true;
    }

    public static function unregisterTypes(): void
    {
        self::$types = [];
    }

    public static function unregisterAlias(string $alias): bool
    {
        if (1 !== preg_match('/^@\w+$/', $alias)) {
            return false;
        }

        foreach (self::$aliases as $type => $aliases) {
            foreach ($aliases as $registeredAlias => $__) {
                if ($registeredAlias === $alias) {
                    unset(self::$aliases[$type][$registeredAlias]);

                    return true;
                }
            }
        }

        return false;
    }

    public static function unregisterAliases(): void
    {
        self::$aliases = [];
    }

    public static function unregisterAll(): void
    {
        self::unregisterTypes();
        self::unregisterAliases();
    }

    public static function supportsAlias(?string $alias): bool
    {
        return null !== $alias && array_key_exists($alias, self::aliases());
    }

    public static function supportsType(?string $type): bool
    {
        if (null === $type) {
            return false;
        }

        try {
            self::resolveTypeCallback($type);  /* @phpstan-ignore-line */

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return array<string>
     */
    public static function types(): array
    {
        return array_keys(self::$types);
    }

    /**
     * @return array<string, string>
     */
    public static function aliases(): array
    {
        $res = [];
        foreach (self::$aliases as $registeredType => $aliases) {
            foreach ($aliases as $registeredAlias => $__) {
                $res[$registeredAlias] = $registeredType;
            }
        }

        return $res;
    }

    public static function supports(ReflectionParameter|ReflectionProperty $reflectionProperty, ?string $alias = null): bool
    {
        $propertyTypeList = self::getTypes($reflectionProperty->getType());
        if ([] === $propertyTypeList && self::supportsAlias($alias)) {
            return true;
        }

        foreach ($propertyTypeList as $propertyType) {
            $type = $propertyType->getName();
            if (null === $alias) {
                if (self::supportsType($type)) {
                    return true;
                }

                continue;
            }

            if (self::aliasSupportsType($type) || (Type::Mixed->value === $type && self::supportsAlias($alias))) {
                return true;
            }
        }

        return false;
    }

    private static function aliasSupportsType(string $type): bool
    {
        foreach (self::aliases() as $registeredType) {
            if ($type === $registeredType) {
                return true;
            }

            try {
                if ((new ReflectionClass($type))->implementsInterface($registeredType)) {  /* @phpstan-ignore-line */
                    return true;
                }
            } catch (Throwable) {
            }
        }

        return false;
    }

    /**
     * @param class-string $type
     */
    private static function resolveTypeCallback(string $type): Closure
    {
        foreach (self::$types as $registeredType => $callback) {
            if ($type === $registeredType) {
                return $callback;
            }

            try {
                $reflType = new ReflectionClass($type);
                if ($reflType->implementsInterface($registeredType)) {
                    return $callback;
                }
            } catch (Throwable) {
            }
        }

        throw new MappingFailed('The `'.$type.'` could not be resolved.');
    }

    private static function resolveAliasCallback(string $type, string $alias): Closure
    {
        $rType = self::aliases()[$alias] ?? null;
        if (isset($rType)) {
            return self::$aliases[$rType][$alias];
        }

        foreach (self::aliases() as $aliasName => $registeredType) {
            try {
                $reflType = new ReflectionClass($type); /* @phpstan-ignore-line */
                if ($reflType->implementsInterface($registeredType)) {
                    return self::$aliases[$registeredType][$aliasName];
                }
            } catch (Throwable) {
            }
        }

        throw new MappingFailed('The `'.$type.'` could not be resolved.');
    }

    /**
     * @throws MappingFailed
     *
     * @return array{0:string, 1:bool}
     */
    private static function resolve(ReflectionParameter|ReflectionProperty $reflectionProperty): array
    {
        if (null === $reflectionProperty->getType()) {
            return [Type::Mixed->value, true];
        }

        $types = self::getTypes($reflectionProperty->getType());

        $type = null;
        $isNullable = false;
        $hasMixed = false;
        foreach ($types as $foundType) {
            if (!$isNullable && $foundType->allowsNull()) {
                $isNullable = true;
            }

            if (null === $type) {
                $instanceName = $foundType->getName();
                if (self::supportsType($instanceName) || array_key_exists($instanceName, self::$aliases)) {
                    $type = $foundType;
                }

                if (true !== $hasMixed && Type::Mixed->value === $instanceName) {
                    $hasMixed = true;
                }
            }
        }

        return match (true) {
            $type instanceof ReflectionNamedType => [$type->getName(), $isNullable],
            $hasMixed => [Type::Mixed->value, true],
            default => throw new MappingFailed(match (true) {
                $reflectionProperty instanceof ReflectionParameter => 'The method `'.$reflectionProperty->getDeclaringClass()?->getName().'::'.$reflectionProperty->getDeclaringFunction()->getName().'` argument `'.$reflectionProperty->getName().'` must be typed with a supported type.',
                $reflectionProperty instanceof ReflectionProperty => 'The property `'.$reflectionProperty->getDeclaringClass()->getName().'::'.$reflectionProperty->getName().'` must be typed with a supported type.',
            }),
        };
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

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @deprecated since version 9.13.0
     * @see CallbackCasting::unregisterType()
     * @codeCoverageIgnore
     */
    public static function unregister(string $type): bool
    {
        return self::unregisterType($type);
    }
}
