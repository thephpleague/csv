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
use Iterator;
use League\Csv\MapIterator;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionProperty;
use Throwable;

use function array_search;
use function array_values;
use function count;
use function is_int;

final class Denormalizer
{
    private static bool $emptyStringAsNull = true;

    private readonly ReflectionClass $class;
    /** @var array<ReflectionProperty> */
    private readonly array $properties;
    /** @var array<PropertySetter> */
    private readonly array $propertySetters;
    /** @var array<ReflectionMethod> */
    private readonly array $postMapCalls;

    /**
     * @param class-string $className
     * @param array<string> $propertyNames
     *
     * @throws MappingFailed
     */
    public function __construct(string $className, array $propertyNames = [])
    {
        $this->class = $this->setClass($className);
        $this->properties = $this->class->getProperties();
        $this->propertySetters = $this->setPropertySetters($propertyNames);
        $this->postMapCalls = $this->setPostMapCalls();
    }

    /**
     * Enable converting empty string to the null value.
     */
    public static function allowEmptyStringAsNull(): void
    {
        self::$emptyStringAsNull = true;
    }

    /**
     * Disable converting empty string to the null value.
     */
    public static function disallowEmptyStringAsNull(): void
    {
        self::$emptyStringAsNull = false;
    }

    /**
     * Register a global type conversion callback to convert a field into a specific type.
     *
     * @throws MappingFailed
     */
    public static function registerType(string $type, Closure $callback): void
    {
        CallbackCasting::register($type, $callback);
    }

    /**
     * Unregister a global type conversion callback to convert a field into a specific type.
     *
     * @param string $type
     *
     * @return bool
     */
    public static function unregisterType(string $type): bool
    {
        return CallbackCasting::unregisterType($type);
    }

    public static function unregisterAllTypes(): void
    {
        CallbackCasting::unregisterTypes();
    }

    /**
     * Register a callback to convert a field into a specific type.
     *
     * @throws MappingFailed
     */
    public static function registerAlias(string $alias, string $type, Closure $callback): void
    {
        CallbackCasting::register($type, $callback, $alias);
    }

    public static function unregisterAlias(string $alias): bool
    {
        return CallbackCasting::unregisterAlias($alias);
    }

    public static function unregisterAllAliases(): void
    {
        CallbackCasting::unregisterAliases();
    }

    public static function unregisterAll(): void
    {
        CallbackCasting::unregisterAll();
    }

    /**
     * @return array<string>
     */
    public static function types(): array
    {
        $default = [...array_column(Type::cases(), 'value'), ...CallbackCasting::types()];

        return array_values(array_unique($default));
    }

    /**
     * @return array<string, string>
     */
    public static function aliases(): array
    {
        return CallbackCasting::aliases();
    }

    public static function supportsAlias(string $alias): bool
    {
        return CallbackCasting::supportsAlias($alias);
    }

    /**
     * @param class-string $className
     * @param array<?string> $record
     *
     * @throws DenormalizationFailed
     * @throws MappingFailed
     * @throws ReflectionException
     * @throws TypeCastingFailed
     */
    public static function assign(string $className, array $record): object
    {
        return (new self($className, array_keys($record)))->denormalize($record);
    }

    /**
     * @param class-string $className
     * @param array<string> $propertyNames
     *
     * @throws MappingFailed
     * @throws TypeCastingFailed
     */
    public static function assignAll(string $className, iterable $records, array $propertyNames = []): Iterator
    {
        return (new self($className, $propertyNames))->denormalizeAll($records);
    }

    public function denormalizeAll(iterable $records): Iterator
    {
        return MapIterator::fromIterable($records, $this->denormalize(...));
    }

    /**
     * @throws DenormalizationFailed
     * @throws ReflectionException
     * @throws TypeCastingFailed
     */
    public function denormalize(array $record): object
    {
        $object = $this->class->newInstanceWithoutConstructor();

        $this->hydrate($object, $record);
        $this->assertObjectIsInValidState($object);

        foreach ($this->postMapCalls as $accessor) {
            $accessor->invoke($object);
        }

        return $object;
    }

    /**
     * @param array<?string> $record
     *
     * @throws ReflectionException
     * @throws TypeCastingFailed
     */
    private function hydrate(object $object, array $record): void
    {
        $record = array_values($record);
        foreach ($this->propertySetters as $propertySetter) {
            $value = $record[$propertySetter->offset];
            if (is_string($value) && '' === trim($value) && self::$emptyStringAsNull) {
                $value = null;
            }

            $propertySetter($object, $value);
        }
    }

    /**
     * @throws DenormalizationFailed
     */
    private function assertObjectIsInValidState(object $object): void
    {
        foreach ($this->properties as $property) {
            if (!$property->isInitialized($object)) {
                throw DenormalizationFailed::dueToUninitializedProperty($property);
            }
        }
    }

    /**
     * @param class-string $className
     *
     * @throws MappingFailed
     */
    private function setClass(string $className): ReflectionClass
    {
        if (!class_exists($className)) {
            throw new MappingFailed('The class `'.$className.'` can not be denormalized; The class does not exist or could not be found.');
        }

        $class = new ReflectionClass($className);
        if ($class->isInternal() && $class->isFinal()) {
            throw new MappingFailed('The class `'.$className.'` can not be denormalized; PHP internal class marked as final can not be instantiated without using the constructor.');
        }

        return $class;
    }

    /**
     * @param array<string> $propertyNames
     *
     * @throws MappingFailed
     *
     * @return array<PropertySetter>
     */
    private function setPropertySetters(array $propertyNames): array
    {
        $propertySetters = [];
        $methodNames = array_map(fn (string|int $propertyName) => is_int($propertyName) ? null : 'set'.ucfirst($propertyName), $propertyNames);
        foreach ([...$this->properties, ...$this->class->getMethods()] as $accessor) {
            $attributes = $accessor->getAttributes(MapCell::class, ReflectionAttribute::IS_INSTANCEOF);
            $propertySetter = match (count($attributes)) {
                0 => $this->autoDiscoverPropertySetter($accessor, $propertyNames, $methodNames),
                1 => $this->findPropertySetter($attributes[0]->newInstance(), $accessor, $propertyNames),
                default => throw new MappingFailed('Using more than one `'.MapCell::class.'` attribute on a class property or method is not supported.'),
            };
            if (null !== $propertySetter) {
                $propertySetters[] = $propertySetter;
            }
        }

        return match ([]) {
            $propertySetters => throw new MappingFailed('No property or method from `'.$this->class->getName().'` could be used for denormalization.'),
            default => $propertySetters,
        };
    }

    private function setPostMapCalls(): array
    {
        $methods = [];
        $attributes = $this->class->getAttributes(AfterMapping::class, ReflectionAttribute::IS_INSTANCEOF);
        $nbAttributes = count($attributes);
        if (0 === $nbAttributes) {
            return $methods;
        }

        if (1 < $nbAttributes) {
            throw new MappingFailed('Using more than one `'.AfterMapping::class.'` attribute on a class property or method is not supported.');
        }

        /** @var AfterMapping $postMap */
        $postMap = $attributes[0]->newInstance();
        foreach ($postMap->methods as $method) {
            try {
                $accessor = $this->class->getMethod($method);
            } catch (ReflectionException $exception) {
                throw new MappingFailed('The method `'.$method.'` is not defined on the `'.$this->class->getName().'` class.', 0, $exception);
            }

            if (0 !== $accessor->getNumberOfRequiredParameters()) {
                throw new MappingFailed('The method `'.$this->class->getName().'::'.$accessor->getName().'` has too many required parameters.');
            }

            $methods[] = $accessor;
        }

        return $methods;
    }

    /**
     * @param array<string> $propertyNames
     * @param array<?string> $methodNames
     *
     * @throws MappingFailed
     */
    private function autoDiscoverPropertySetter(ReflectionMethod|ReflectionProperty $accessor, array $propertyNames, array $methodNames): ?PropertySetter
    {
        if ($accessor->isStatic() || !$accessor->isPublic()) {
            return null;
        }

        if ($accessor instanceof ReflectionMethod) {
            if ($accessor->isConstructor()) {
                return null;
            }

            if ([] === $accessor->getParameters()) {
                return null;
            }

            if (1 < $accessor->getNumberOfRequiredParameters()) {
                return null;
            }
        }

        /** @var int|false $offset */
        /** @var ReflectionParameter|ReflectionProperty $reflectionProperty */
        [$offset, $reflectionProperty] = match (true) {
            $accessor instanceof ReflectionMethod => [array_search($accessor->getName(), $methodNames, true), $accessor->getParameters()[0]],
            $accessor instanceof ReflectionProperty => [array_search($accessor->getName(), $propertyNames, true), $accessor],
        };

        return match (true) {
            false === $offset,
            null === $reflectionProperty->getType() => null,
            default => new PropertySetter(
                $accessor,
                $offset,
                $this->resolveTypeCasting($reflectionProperty)
            ),
        };
    }

    /**
     * @param array<string> $propertyNames
     *
     * @throws MappingFailed
     */
    private function findPropertySetter(MapCell $cell, ReflectionMethod|ReflectionProperty $accessor, array $propertyNames): ?PropertySetter
    {
        if ($cell->ignore) {
            return null;
        }

        $typeCaster = $this->resolveTypeCaster($cell, $accessor);

        $offset = $cell->column ?? match (true) {
            $accessor instanceof ReflectionMethod => $this->getMethodFirstArgument($accessor)->getName(),
            $accessor instanceof ReflectionProperty => $accessor->getName(),
        };

        if (!is_int($offset)) {
            if ([] === $propertyNames) {
                throw new MappingFailed('offset as string are only supported if the property names list is not empty.');
            }

            /** @var int<0, max>|false $index */
            $index = array_search($offset, $propertyNames, true);
            if (false === $index) {
                throw new MappingFailed('The `'.$offset.'` property could not be found in the property names list; Please verify your property names list.');
            }

            $offset = $index;
        }

        $reflectionProperty = match (true) {
            $accessor instanceof ReflectionMethod => $accessor->getParameters()[0],
            $accessor instanceof ReflectionProperty => $accessor,
        };

        return match (true) {
            0 > $offset => throw new MappingFailed('offset integer position can only be positive or equals to 0; received `'.$offset.'`'),
            [] !== $propertyNames && $offset > count($propertyNames) - 1 => throw new MappingFailed('offset integer position can not exceed property names count.'),
            null === $typeCaster => new PropertySetter($accessor, $offset, $this->resolveTypeCasting($reflectionProperty, $cell->options)),
            default => new PropertySetter($accessor, $offset, $this->getTypeCasting($reflectionProperty, $typeCaster, $cell->options)),
        };
    }

    /**
     * @throws MappingFailed
     */
    private function getMethodFirstArgument(ReflectionMethod $reflectionMethod): ReflectionParameter
    {
        $arguments = $reflectionMethod->getParameters();

        return match (true) {
            [] === $arguments => throw new MappingFailed('The method `'.$reflectionMethod->getDeclaringClass()->getName().'::'.$reflectionMethod->getName().'` does not use parameters.'),
            1 < $reflectionMethod->getNumberOfRequiredParameters() => throw new MappingFailed('The method `'.$reflectionMethod->getDeclaringClass()->getName().'::'.$reflectionMethod->getName().'` has too many required parameters.'),
            default => $arguments[0]
        };
    }

    /**
     * @throws MappingFailed
     */
    private function getTypeCasting(
        ReflectionProperty|ReflectionParameter $reflectionProperty,
        string $typeCaster,
        array $options
    ): TypeCasting {
        try {
            /** @var TypeCasting $cast */
            $cast = match (str_starts_with($typeCaster, CallbackCasting::class.'@')) {
                true => new CallbackCasting($reflectionProperty, substr($typeCaster, strlen(CallbackCasting::class))),
                false => new $typeCaster($reflectionProperty),
            };
            $cast->setOptions(...$options);

            return $cast;
        } catch (MappingFailed $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw MappingFailed::dueToInvalidCastingArguments($exception);
        }
    }

    /**
     * @throws MappingFailed
     */
    private function resolveTypeCasting(ReflectionProperty|ReflectionParameter $reflectionProperty, array $options = []): TypeCasting
    {
        $castResolver = function (ReflectionProperty|ReflectionParameter $reflectionProperty, $options): CallbackCasting {
            $cast = new CallbackCasting($reflectionProperty);
            $cast->setOptions(...$options);

            return $cast;
        };

        try {
            return match (true) {
                CallbackCasting::supports($reflectionProperty) => $castResolver($reflectionProperty, $options),
                default => Type::resolve($reflectionProperty, $options),
            };
        } catch (MappingFailed $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw MappingFailed::dueToInvalidCastingArguments($exception);
        }
    }

    public function resolveTypeCaster(MapCell $cell, ReflectionMethod|ReflectionProperty $accessor): ?string
    {
        /** @var ?class-string<TypeCasting> $typeCaster */
        $typeCaster = $cell->cast;
        if (null === $typeCaster) {
            return null;
        }

        if (class_exists($typeCaster)) {
            if (!(new ReflectionClass($typeCaster))->implementsInterface(TypeCasting::class)) {
                throw MappingFailed::dueToInvalidTypeCastingClass($typeCaster);
            }

            return $typeCaster;
        }

        if ($accessor instanceof ReflectionMethod) {
            $accessor = $accessor->getParameters()[0];
        }

        if (!CallbackCasting::supports($accessor, $typeCaster)) {
            throw MappingFailed::dueToInvalidTypeCastingClass($typeCaster);
        }

        return CallbackCasting::class.$typeCaster;
    }
}
