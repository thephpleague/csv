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

use Iterator;
use League\Csv\Serializer\CastToArray;
use League\Csv\Serializer\CastToBool;
use League\Csv\Serializer\CastToDate;
use League\Csv\Serializer\CastToEnum;
use League\Csv\Serializer\CastToFloat;
use League\Csv\Serializer\CastToInt;
use League\Csv\Serializer\CastToString;
use League\Csv\Serializer\Cell;
use League\Csv\Serializer\MappingFailed;
use League\Csv\Serializer\PropertySetter;
use League\Csv\Serializer\Type;
use League\Csv\Serializer\TypeCasting;
use League\Csv\Serializer\TypeCastingFailed;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionProperty;
use Throwable;

use function array_key_exists;
use function array_reduce;
use function array_search;
use function array_values;
use function count;
use function in_array;
use function is_int;

final class Serializer
{
    private readonly ReflectionClass $class;
    /** @var array<ReflectionProperty> */
    private readonly array $properties;
    /** @var non-empty-array<PropertySetter>  */
    private readonly array $propertySetters;

    /**
     * @param class-string $className
     * @param array<string> $propertyNames
     *
     * @throws MappingFailed
     * @throws ReflectionException
     */
    public function __construct(string $className, array $propertyNames = [])
    {
        $this->class = new ReflectionClass($className);
        $this->properties = $this->class->getProperties();
        $this->propertySetters = $this->findPropertySetters($propertyNames);
    }

    /**
     * @param class-string $className
     * @param array<?string> $record
     *
     * @throws MappingFailed
     * @throws ReflectionException
     * @throws TypeCastingFailed
     */
    public static function assign(string $className, array $record): object
    {
        return (new self($className, array_keys($record)))->deserialize($record);
    }

    /**
     * @param class-string $className
     * @param array<string> $propertyNames
     *
     * @throws MappingFailed
     * @throws ReflectionException
     * @throws TypeCastingFailed
     */
    public static function assignAll(string $className, iterable $records, array $propertyNames = []): Iterator
    {
        return (new self($className, $propertyNames))->deserializeAll($records);
    }

    public function deserializeAll(iterable $records): Iterator
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
     * @throws ReflectionException
     * @throws TypeCastingFailed
     */
    public function deserialize(array $record): object
    {
        $object = $this->class->newInstanceWithoutConstructor();

        $this->hydrate($object, $record);
        $this->assertObjectIsInValidState($object);

        return $object;
    }

    /**
     * @param array<?string> $record
     */
    private function hydrate(object $object, array $record): void
    {
        $record = array_values($record);
        foreach ($this->propertySetters as $propertySetter) {
            $propertySetter($object, $record[$propertySetter->offset]);
        }
    }

    /**
     * @throws TypeCastingFailed
     */
    private function assertObjectIsInValidState(object $object): void
    {
        foreach ($this->properties as $property) {
            if (!$property->isInitialized($object)) {
                throw new TypeCastingFailed('The property '.$this->class->getName().'::'.$property->getName().' is not initialized.');
            }
        }
    }

    /**
     * @param array<string> $propertyNames
     *
     * @throws MappingFailed
     *
     * @return non-empty-array<PropertySetter>
     */
    private function findPropertySetters(array $propertyNames): array
    {
        $propertySetters = [];
        foreach ($this->class->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isStatic()) {
                continue;
            }

            $attribute = $property->getAttributes(Cell::class, ReflectionAttribute::IS_INSTANCEOF);
            if ([] !== $attribute) {
                continue;
            }

            /** @var int|false $offset */
            $offset = array_search($property->getName(), $propertyNames, true);
            if (false === $offset) {
                continue;
            }

            $propertySetters[] = $this->autoDiscoverPropertySetter($property, $offset);
        }

        $propertySetters = [...$propertySetters, ...$this->findPropertySettersByCellAttribute($propertyNames)];
        if ([] === $propertySetters) {
            throw new MappingFailed('No properties or method setters were found eligible on the class `'.$this->class->getName().'` to be used for type casting.');
        }

        return $propertySetters;
    }

    private function autoDiscoverPropertySetter(ReflectionProperty $property, int $offset): PropertySetter
    {
        $cast = $this->resolveTypeCasting($property);
        if (null === $cast) {
            throw new MappingFailed('No built-in `'.TypeCasting::class.'` class can handle `$'.$property->getName().'` type.');
        }

        return new PropertySetter($property, $offset, $cast);
    }

    /**
     * @param array<string> $propertyNames
     *
     * @return array<PropertySetter>
     */
    private function findPropertySettersByCellAttribute(array $propertyNames): array
    {
        $addPropertySetter = function (array $carry, ReflectionProperty|ReflectionMethod $accessor) use ($propertyNames) {
            $propertySetter = $this->findPropertySetter($accessor, $propertyNames);
            if (null === $propertySetter) {
                return $carry;
            }

            $carry[] = $propertySetter;

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
            throw new MappingFailed('Using more than one `'.Cell::class.'` attribute on a class property or method is not supported.');
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
                [] !== $propertyNames && $offset > count($propertyNames) - 1 => throw new MappingFailed('column integer position can not exceed property names count.'),
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
     * @param array<string, array<string|int|float|bool>|string|int|float|bool> $arguments
     *
     * @throws MappingFailed If the arguments do not match the expected TypeCasting class constructor signature
     */
    private function resolveTypeCasting(ReflectionProperty|ReflectionParameter $reflectionProperty, array $arguments = []): ?TypeCasting
    {
        $reflectionType = $reflectionProperty->getType();
        if (null === $reflectionType) {
            throw new MappingFailed('The property `'.$reflectionProperty->getName().'` must be typed.');
        }

        try {
            return match (Type::tryFromReflectionType($reflectionType)) {
                Type::Mixed, Type::Null, Type::String => new CastToString($reflectionProperty, ...$arguments), /* @phpstan-ignore-line */
                Type::Iterable, Type::Array => new CastToArray($reflectionProperty, ...$arguments),            /* @phpstan-ignore-line */
                Type::False, Type::True, Type::Bool => new CastToBool($reflectionProperty, ...$arguments),     /* @phpstan-ignore-line */
                Type::Float => new CastToFloat($reflectionProperty, ...$arguments),                            /* @phpstan-ignore-line */
                Type::Int => new CastToInt($reflectionProperty, ...$arguments),                                /* @phpstan-ignore-line */
                Type::Date => new CastToDate($reflectionProperty, ...$arguments),                              /* @phpstan-ignore-line */
                Type::Enum => new CastToEnum($reflectionProperty, ...$arguments),                              /* @phpstan-ignore-line */
                null => null,
            };
        } catch (Throwable $exception) {
            if ($exception instanceof MappingFailed) {
                throw $exception;
            }

            throw new MappingFailed(message:'Unable to instantiate a casting mechanism. Please verify your casting arguments', previous: $exception);
        }
    }

    /**
     * @throws MappingFailed
     */
    private function getTypeCasting(Cell $cell, ReflectionProperty|ReflectionMethod $accessor): TypeCasting
    {
        if (array_key_exists('reflectionProperty', $cell->castArguments)) {
            throw new MappingFailed('The key `propertyType` can not be used with `castArguments`.');
        }

        $reflectionProperty = match (true) {
            $accessor instanceof ReflectionMethod => $accessor->getParameters()[0],
            $accessor instanceof ReflectionProperty => $accessor,
        };

        $typeCaster = $cell->cast;
        if (null !== $typeCaster) {
            if (!in_array(TypeCasting::class, class_implements($typeCaster), true)) {
                throw new MappingFailed('The class `'.$typeCaster.'` does not implements the `'.TypeCasting::class.'` interface.');
            }

            $arguments = [...$cell->castArguments, ...['reflectionProperty' => $reflectionProperty]];
            /** @var TypeCasting $cast */
            $cast = new $typeCaster(...$arguments);

            return $cast;
        }

        if (null === $reflectionProperty->getType()) {
            throw new MappingFailed(match (true) {
                $reflectionProperty instanceof ReflectionParameter => 'The setter method argument `'.$reflectionProperty->getName().'` must be typed.',
                $reflectionProperty instanceof ReflectionProperty => 'The property `'.$reflectionProperty->getName().'` must be typed.',
            });
        }

        return $this->resolveTypeCasting($reflectionProperty, $cell->castArguments) ?? throw new MappingFailed(match (true) {
            $reflectionProperty instanceof ReflectionParameter => 'No valid type casting was found for the setter method argument `'.$reflectionProperty->getName().'`; it must be typed.',
            $reflectionProperty instanceof ReflectionProperty => 'No valid type casting was found for the property `'.$reflectionProperty->getName().'`; it must be typed.',
        });
    }
}
