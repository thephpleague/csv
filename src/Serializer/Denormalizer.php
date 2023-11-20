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

use function array_key_exists;
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
    }

    public static function allowEmptyStringAsNull(): void
    {
        self::$emptyStringAsNull = true;
    }

    public static function disallowEmptyStringAsNull(): void
    {
        self::$emptyStringAsNull = false;
    }

    /**
     * @throws MappingFailed
     */
    public static function registerType(string $type, Closure $closure): void
    {
        ClosureCasting::register($type, $closure);
    }

    public static function unregisterType(string $type): bool
    {
        return ClosureCasting::unregister($type);
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
        $check = true;
        $assign = function (array $record) use (&$check) {
            $object = $this->class->newInstanceWithoutConstructor();
            $this->hydrate($object, $record);

            if ($check) {
                $check = false;
                $this->assertObjectIsInValidState($object);
            }

            return $object;
        };

        return MapIterator::fromIterable($records, $assign);
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
            if ('' === $value && self::$emptyStringAsNull) {
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
            $attributes = $accessor->getAttributes(Cell::class, ReflectionAttribute::IS_INSTANCEOF);
            $propertySetter = match (count($attributes)) {
                0 => $this->autoDiscoverPropertySetter($accessor, $propertyNames, $methodNames),
                1 => $this->findPropertySetter($attributes[0]->newInstance(), $accessor, $propertyNames),
                default => throw new MappingFailed('Using more than one `'.Cell::class.'` attribute on a class property or method is not supported.'),
            };
            if (null !== $propertySetter) {
                $propertySetters[] = $propertySetter;
            }
        }

        return match ([]) {
            $propertySetters => throw new MappingFailed('No property or method from `'.$this->class->getName().'` could be used for deserialization.'),
            default => $propertySetters,
        };
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
        [$offset, $reflectionProperty] = match (true) {
            $accessor instanceof ReflectionMethod => [array_search($accessor->getName(), $methodNames, true), $accessor->getParameters()[0]],
            $accessor instanceof ReflectionProperty => [array_search($accessor->getName(), $propertyNames, true), $accessor],
        };

        return match (false) {
            $offset => null,
            default => new PropertySetter(
                $accessor,
                $offset,
                $this->resolveTypeCasting($reflectionProperty, ['reflectionProperty' => $reflectionProperty])
            ),
        };
    }

    /**
     * @param array<string> $propertyNames
     *
     * @throws MappingFailed
     */
    private function findPropertySetter(Cell $cell, ReflectionMethod|ReflectionProperty $accessor, array $propertyNames): PropertySetter
    {
        if (array_key_exists('reflectionProperty', $cell->castArguments)) {
            throw MappingFailed::dueToForbiddenCastArgument();
        }

        /** @var ?class-string<TypeCasting> $typeCaster */
        $typeCaster = $cell->cast;
        if (null !== $typeCaster && (!class_exists($typeCaster) || !(new ReflectionClass($typeCaster))->implementsInterface(TypeCasting::class))) {
            throw MappingFailed::dueToInvalidTypeCastingClass($typeCaster);
        }

        $offset = $cell->offset ?? match (true) {
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

        $arguments = [...$cell->castArguments, ...['reflectionProperty' => $reflectionProperty = match (true) {
            $accessor instanceof ReflectionMethod => $accessor->getParameters()[0],
            $accessor instanceof ReflectionProperty => $accessor,
        }]];

        return match (true) {
            0 > $offset => throw new MappingFailed('offset integer position can only be positive or equals to 0; received `'.$offset.'`'),
            [] !== $propertyNames && $offset > count($propertyNames) - 1 => throw new MappingFailed('offset integer position can not exceed property names count.'),
            null === $typeCaster => new PropertySetter($accessor, $offset, $this->resolveTypeCasting($reflectionProperty, $arguments)),
            default => new PropertySetter($accessor, $offset, $this->getTypeCasting($typeCaster, $arguments)),
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
     * @param class-string<TypeCasting> $typeCaster
     *
     * @throws MappingFailed
     */
    private function getTypeCasting(string $typeCaster, array $arguments): TypeCasting
    {
        try {
            return new $typeCaster(...$arguments);
        } catch (Throwable $exception) { /* @phpstan-ignore-line */
            throw $exception instanceof MappingFailed ? $exception : MappingFailed::dueToInvalidCastingArguments($exception);
        }
    }

    /**
     * @throws MappingFailed
     */
    private function resolveTypeCasting(ReflectionProperty|ReflectionParameter $reflectionProperty, array $arguments): TypeCasting
    {
        try {
            return match (true) {
                ClosureCasting::supports($reflectionProperty) => new ClosureCasting(...$arguments),
                default => Type::resolve($reflectionProperty, $arguments),
            };
        } catch (MappingFailed $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw MappingFailed::dueToInvalidCastingArguments($exception);
        }
    }
}
