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

namespace League\Csv;

use ArrayIterator;
use Iterator;
use League\Csv\Serializer\CastToArray;
use League\Csv\Serializer\CastToBuiltInType;
use League\Csv\Serializer\CastToDate;
use League\Csv\Serializer\CastToEnum;
use League\Csv\Serializer\Cell;
use League\Csv\Serializer\MappingFailed;
use League\Csv\Serializer\PropertySetter;
use League\Csv\Serializer\TypeCasting;
use League\Csv\Serializer\TypeCastingFailed;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionProperty;
use RuntimeException;
use TypeError;

final class Serializer
{
    private readonly ReflectionClass $class;
    /** @var array<PropertySetter>  */
    private readonly array $propertySetters;
    /** @var array<ReflectionProperty> */
    private readonly array $properties;

    /**
     * @param class-string $className
     * @param array<string> $propertyNames
     *
     * @throws MappingFailed
     * @throws RuntimeException
     * @throws TypeError
     */
    public function __construct(string $className, array $propertyNames = [])
    {
        $this->class = new ReflectionClass($className);
        $this->properties = $this->class->getProperties();
        $this->propertySetters = $this->findPropertySetters($propertyNames);

        //if converters is empty it means the Serializer
        //was unable to detect properties to map
        if ([] === $this->propertySetters) {
            throw new MappingFailed('No properties or method setters were found eligible on the class `'.$className.'` to be used for type casting.');
        }
    }

    /**
     * @throws MappingFailed
     * @throws ReflectionException
     * @throws TypeCastingFailed
     */
    public function deserializeAll(iterable $records): Iterator
    {
        $check = true;
        $deserialize = function (array $record) use (&$check): object {
            $object = $this->createInstance($record);
            if ($check) {
                $this->assertObjectIsInValidState($object);

                $check = false;
            }

            return $object;
        };

        return new MapIterator(
            is_array($records) ? new ArrayIterator($records) : $records,
            $deserialize
        );
    }

    /**
     * @throws MappingFailed
     * @throws ReflectionException
     * @throws TypeCastingFailed
     */
    public function deserialize(array $record): object
    {
        $object = $this->createInstance($record);

        $this->assertObjectIsInValidState($object);

        return $object;
    }

    private function createInstance(array $record): object
    {
        $object = $this->class->newInstanceWithoutConstructor();

        $record = array_values($record);
        foreach ($this->propertySetters as $propertySetter) {
            $propertySetter->setValue($object, $record[$propertySetter->offset]);
        }

        return $object;
    }


    /**
     * @throws MappingFailed
     */
    private function assertObjectIsInValidState(object $object): void
    {
        foreach ($this->properties as $property) {
            if (!$property->isInitialized($object)) {
                throw new MappingFailed('The property '.$this->class->getName().'::'.$property->getName().' is not initialized.');
            }
        }
    }

    /**
     * @param class-string $className
     *
     * @throws MappingFailed
     * @throws ReflectionException
     * @throws TypeCastingFailed
     */
    public static function map(string $className, array $record): object
    {
        return (new self($className, array_keys($record)))->deserialize($record);
    }

    /**
     * @param array<string> $propertyNames
     *
     * @throws MappingFailed
     *
     * @return array<string, PropertySetter>
     */
    private function findPropertySetters(array $propertyNames): array
    {
        $check = [];
        $propertySetters = [];
        foreach ($this->class->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $propertyName = $property->getName();

            /** @var int|false $offset */
            $offset = array_search($propertyName, $propertyNames, true);
            if (false === $offset) {
                //the property is not in the record header
                //we can not throw as it may be set via a
                //setter method using the Cell attribute
                continue;
            }

            $type = $property->getType();
            if (!$type instanceof ReflectionNamedType) {
                throw new MappingFailed('The property `'.$propertyName.'` must be typed.');
            }

            $cast = $this->resolveTypeCasting($type->getName());
            if (null === $cast) {
                //the property can not be automatically cast
                //we can not throw yet as casting may be set
                //using the Cell attribute
                $check['property:'.$propertyName] = $propertyName;

                continue;
            }

            $propertySetters['property:'.$propertyName] = new PropertySetter($property, $offset, $cast);
        }

        $propertySetters = [...$propertySetters, ...$this->findPropertySettersByCellAttributes($propertyNames)];
        foreach ($check as $key => $propertyName) {
            //if we still can not find how to cast the property we must throw
            if (!isset($propertySetters[$key])) {
                throw new MappingFailed('No valid type casting was found for property `'.$propertyName.'`.');
            }
        }

        return $propertySetters;
    }

    /**
     * @param array<string> $propertyNames
     *
     * @return array<string, PropertySetter>
     */
    private function findPropertySettersByCellAttributes(array $propertyNames): array
    {
        $addPropertySetter = function (array $carry, ReflectionProperty|ReflectionMethod $accessor) use ($propertyNames) {
            $propertySetter = $this->findPropertySetter($accessor, $propertyNames);
            if (null === $propertySetter) {
                return $carry;
            }

            $type = $accessor instanceof ReflectionProperty ? 'property' : 'method';
            $carry[$type.':'.$accessor->getName()] = $propertySetter;

            return $carry;
        };

        return array_reduce(
            [...$this->properties, ...$this->class->getMethods(ReflectionMethod::IS_PUBLIC)],
            $addPropertySetter,
            []
        );
    }

    /**
     * @param array<string> $propertyNames
     *
     * @throws MappingFailed
     */
    private function findPropertySetter(ReflectionProperty|ReflectionMethod $accessor, array $propertyNames): ?PropertySetter
    {
        $attributes = $accessor->getAttributes(Cell::class, ReflectionAttribute::IS_INSTANCEOF);
        if ([] === $attributes) {
            return null;
        }

        if (1 < count($attributes)) {
            throw new MappingFailed('Using more than one '.Cell::class.' attribute on a class property or method is not supported.');
        }

        /** @var Cell $cell */
        $cell = $attributes[0]->newInstance();
        $offset = $cell->offset ?? match (true) {
            $accessor instanceof ReflectionMethod => $accessor->getParameters()[0]->getName(),
            default => $accessor->getName(),
        };

        $cast = $this->getTypeCasting($cell, $accessor);
        if (is_int($offset)) {
            return match (true) {
                0 > $offset => throw new MappingFailed('column integer position can only be positive or equals to 0; received `'.$offset.'`'),
                [] !== $propertyNames && $offset > count($propertyNames) - 1 => throw new MappingFailed('column integer position can not exceed header cell count.'),
                default => new PropertySetter($accessor, $offset, $cast),
            };
        }

        if ([] === $propertyNames) {
            throw new MappingFailed('Column name as string are only supported if the tabular data has a non-empty header.');
        }

        /** @var int<0, max>|false $index */
        $index = array_search($offset, $propertyNames, true);
        if (false === $index) {
            throw new MappingFailed('The offset `'.$offset.'` could not be found in the header; Pleaser verify your header data.');
        }

        return new PropertySetter($accessor, $index, $cast);
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function resolveTypeCasting(string $propertyType, array $arguments = []): ?TypeCasting
    {
        return match (true) {
            CastToBuiltInType::supports($propertyType) => new CastToBuiltInType($propertyType),
            CastToDate::supports($propertyType) => new CastToDate($propertyType, ...$arguments), /* @phpstan-ignore-line */
            CastToArray::supports($propertyType) => new CastToArray($propertyType, ...$arguments), /* @phpstan-ignore-line */
            CastToEnum::supports($propertyType) => new CastToEnum($propertyType),
            default => null,
        };
    }

    /**
     * @throws TypeError
     * @throws MappingFailed
     */
    private function getTypeCasting(Cell $cell, ReflectionProperty|ReflectionMethod $accessor): TypeCasting
    {
        $type = match (true) {
            $accessor instanceof ReflectionMethod => $accessor->getParameters()[0]->getType(),
            $accessor instanceof ReflectionProperty => $accessor->getType(),
        };

        $typeCaster = $cell->cast;
        if (null !== $typeCaster) {
            /** @var TypeCasting $cast */
            $cast = new $typeCaster((string) $type, ...$cell->castArguments);

            return $cast;
        }

        if (!$type instanceof ReflectionNamedType) {
            throw new MappingFailed(match (true) {
                $accessor instanceof ReflectionMethod => 'The setter method argument `'.$accessor->getParameters()[0]->getName().'` must be typed.',
                $accessor instanceof ReflectionProperty => 'The property `'.$accessor->getName().'` must be typed.',
            });
        }

        return $this->resolveTypeCasting($type->getName(), $cell->castArguments) ?? throw new MappingFailed(match (true) {
            $accessor instanceof ReflectionMethod => 'No valid type casting was found for the setter method argument `'.$accessor->getParameters()[0]->getName().'` must be typed.',
            $accessor instanceof ReflectionProperty => 'No valid type casting was found for the property `'.$accessor->getName().'` must be typed.',
        });
    }
}
