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

namespace League\Csv\Mapper;

use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;
use TypeError;

final class Serializer
{
    /** @var array<CellConverter>  */
    public readonly array $converters;

    /**
     * @param class-string $className
     * @param array<string> $header
     *
     * @throws TypeError
     * @throws RuntimeException
     * @throws ReflectionException
     */
    public function __construct(public readonly string $className, array $header = [])
    {
        $addConverter = function (array $carry, ReflectionProperty|ReflectionMethod $accessor) use ($header) {
            [$offset, $caster] = $this->getArguments($accessor, $header);

            return match ($offset) {
                null => $carry,
                default => [...$carry, new CellConverter($accessor, $offset, $caster)],
            };
        };

        $class = new ReflectionClass($this->className);

        $this->converters = array_reduce(
            [...$class->getProperties(), ...$class->getMethods(ReflectionMethod::IS_PUBLIC)],
            $addConverter,
            []
        );
    }

    /**
     * @throws ReflectionException
     */
    public function deserialize(array $record): object
    {
        $record = array_values($record);
        $object = (new ReflectionClass($this->className))->newInstanceWithoutConstructor();
        foreach ($this->converters as $converter) {
            $converter->setValue($object, $record[$converter->offset]);
        }

        return $object;
    }

    /**
     * @param array<string> $header
     *
     * @throws CellMappingFailed
     *
     * @return array{0:int<0, max>|null, 1:TypeCasting}
     */
    private function getArguments(ReflectionProperty|ReflectionMethod $target, array $header): array
    {
        $attributes = $target->getAttributes(Cell::class, ReflectionAttribute::IS_INSTANCEOF);
        if ([] === $attributes) {
            return [null, new CastToScalar()];
        }

        if (1 < count($attributes)) {
            throw new CellMappingFailed('Using more than one '.Cell::class.' attribute on a class property or method is not supported.');
        }

        /** @var Cell $column */
        $column = $attributes[0]->newInstance();
        $offset = $column->offset;
        $cast = $this->getTypeCasting($column);
        if (is_int($offset)) {
            return match (true) {
                0 > $offset,
                [] !== $header && $offset > count($header) - 1 => throw new CellMappingFailed('cell integer position can only be positive or equals to 0; received `'.$offset.'`'),
                default => [$offset, $cast],
            };
        }

        if ([] === $header) {
            throw new CellMappingFailed(__CLASS__.' can only use named column if the tabular data has a non-empty header.');
        }

        /** @var int<0, max>|false $index */
        $index = array_search($offset, $header, true);
        if (false === $index) {
            throw new CellMappingFailed(__CLASS__.' cound not find the offset `'.$offset.'` in the header; Pleaser verify your header data.');
        }

        return [$index, $cast];
    }

    private function getTypeCasting(Cell $column): TypeCasting
    {
        $caster = $column->cast;
        if (null === $caster) {
            return new CastToScalar();
        }

        /** @var TypeCasting $cast */
        $cast = new $caster(...$column->castArguments);

        return $cast;
    }
}
