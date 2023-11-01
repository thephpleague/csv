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

use DateTimeInterface;
use Exception;
use League\Csv\Serializer\Attribute\Cell;
use League\Csv\Serializer\Attribute\Record;
use League\Csv\Serializer\CastToDate;
use League\Csv\Serializer\CastToEnum;
use League\Csv\Serializer\CastToScalar;
use League\Csv\Serializer\MappingFailed;
use League\Csv\Serializer\PropertySetter;
use League\Csv\Serializer\TypeCasting;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionEnum;
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
    private readonly array $converters;

    /**
     * @param class-string $className
     * @param array<string> $header
     *
     * @throws TypeError
     * @throws RuntimeException
     * @throws ReflectionException
     */
    public function __construct(string $className, array $header = [])
    {
        $this->class = new ReflectionClass($className);
        $this->converters = $this->buildConvertersFromClass($header);
    }

    /**
     * @throws ReflectionException
     */
    public function deserialize(array $record): object
    {
        $record = array_values($record);
        $object = $this->class->newInstanceWithoutConstructor();
        foreach ($this->converters as $converter) {
            $converter->setValue($object, $record[$converter->offset]);
        }

        return $object;
    }

    /**
     * @param class-string $className
     *
     * @throws ReflectionException
     */
    public static function map(string $className, array $record): object
    {
        return (new self($className, array_keys($record)))->deserialize($record);
    }

    /**
     * @param array<string> $header
     *
     * @return array<string, PropertySetter>
     */
    private function buildConvertersFromClass(array $header): array
    {
        $attributes = $this->class->getAttributes(Record::class, ReflectionAttribute::IS_INSTANCEOF);
        if ([] === $attributes) {
            return $this->buildConvertersFromAccessors($header);
        }

        if (1 < count($attributes)) {
            throw new MappingFailed('Using more than one '.Record::class.' attribute on a class is not supported.');
        }

        $converters = [];
        foreach ($this->class->getProperties() as $property) {
            $propertyName = $property->getName();

            /** @var int|false $offset */
            $offset = array_search($propertyName, $header, true);
            if (false === $offset) {
                continue;
            }

            $type = $property->getType();
            if (!$type instanceof ReflectionNamedType) {
                continue;
            }

            $caster = $this->resolveCaster($type);
            if (null === $caster) {
                continue;
            }

            $converters['property:'.$propertyName] = new PropertySetter($property, $offset, $caster);
        }

        if ([] === $converters) {
            throw new MappingFailed('No properties were found elligible to be used for type casting.');
        }

        return [...$converters, ...$this->buildConvertersFromAccessors($header)];
    }

    /**
     * @param array<string, mixed> $arguments
     *
     * @throws Exception
     */
    private function resolveCaster(ReflectionNamedType $type, array $arguments = []): ?TypeCasting
    {
        $formattedType = ltrim($type->getName(), '?');
        if (in_array($formattedType, ['int', 'float', 'string', 'bool', 'true', 'false', 'null'], true)) {
            return new CastToScalar();
        }

        if (DateTimeInterface::class === $formattedType) {
            return new CastToDate(...$arguments); /* @phpstan-ignore-line */
        }

        $foundInterfaces = class_implements($formattedType);
        if (false !== $foundInterfaces && in_array(DateTimeInterface::class, $foundInterfaces, true)) {
            return new CastToDate(...$arguments); /* @phpstan-ignore-line */
        }

        try {
            new ReflectionEnum($formattedType);

            return new CastToEnum();
        } catch (ReflectionException) {
            return null;
        }
    }

    /**
     * @param array<string> $header
     *
     * @return array<string, PropertySetter>
     */
    private function buildConvertersFromAccessors(array $header): array
    {
        $addConverter = function (array $carry, ReflectionProperty|ReflectionMethod $accessor) use ($header) {
            [$offset, $caster] = $this->getArguments($accessor, $header);
            $type = $accessor instanceof ReflectionProperty ? 'property' : 'method';
            if (null !== $offset) {
                $carry[$type.':'.$accessor->getName()] = new PropertySetter($accessor, $offset, $caster);
            }

            return $carry;
        };

        return array_reduce(
            [...$this->class->getProperties(), ...$this->class->getMethods(ReflectionMethod::IS_PUBLIC)],
            $addConverter,
            []
        );
    }


    /**
     * @param array<string> $header
     *
     * @throws MappingFailed
     *
     * @return array{0:int<0, max>|null, 1:TypeCasting}
     */
    private function getArguments(ReflectionProperty|ReflectionMethod $accessor, array $header): array
    {
        $attributes = $accessor->getAttributes(Cell::class, ReflectionAttribute::IS_INSTANCEOF);
        if ([] === $attributes) {
            return [null, new CastToScalar()];
        }

        if (1 < count($attributes)) {
            throw new MappingFailed('Using more than one '.Cell::class.' attribute on a class property or method is not supported.');
        }

        /** @var Cell $column */
        $column = $attributes[0]->newInstance();
        $offset = $column->offset;

        if (null === $offset) {
            $offset = match (true) {
                $accessor instanceof ReflectionMethod => $accessor->getParameters()[0]->getName(),
                default => $accessor->getName(),
            };
        }

        $cast = $this->getTypeCasting($column, $accessor);
        if (is_int($offset)) {
            return match (true) {
                0 > $offset => throw new MappingFailed('column integer position can only be positive or equals to 0; received `'.$offset.'`'),
                [] !== $header && $offset > count($header) - 1 => throw new MappingFailed('column integer position can not exceed header cell count.'),
                default => [$offset, $cast],
            };
        }

        if ([] === $header) {
            throw new MappingFailed('Column name as string are only supported if the tabular data has a non-empty header.');
        }

        /** @var int<0, max>|false $index */
        $index = array_search($offset, $header, true);
        if (false === $index) {
            throw new MappingFailed('The offset `'.$offset.'` could not be found in the header; Pleaser verify your header data.');
        }

        return [$index, $cast];
    }

    private function getTypeCasting(Cell $cell, ReflectionProperty|ReflectionMethod $accessor): TypeCasting
    {
        $typeCaster = $cell->cast;
        if (null !== $typeCaster) {
            /** @var TypeCasting $cast */
            $cast = new $typeCaster(...$cell->castArguments);

            return $cast;
        }

        $type = match (true) {
            $accessor instanceof ReflectionMethod => $accessor->getParameters()[0]->getType(),
            $accessor instanceof ReflectionProperty => $accessor->getType(),
        };

        if (!$type instanceof ReflectionNamedType) {
            return new CastToScalar();
        }

        return $this->resolveCaster($type, $cell->castArguments) ?? new CastToScalar();
    }
}
