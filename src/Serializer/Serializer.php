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
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;
use TypeError;

final class Serializer
{
    /** @var array<PropertySetter>  */
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
                default => [...$carry, new PropertySetter($accessor, $offset, $caster)],
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
     * @throws MappingFailed
     *
     * @return array{0:int<0, max>|null, 1:TypeCasting}
     */
    private function getArguments(ReflectionProperty|ReflectionMethod $target, array $header): array
    {
        $attributes = $target->getAttributes(Column::class, ReflectionAttribute::IS_INSTANCEOF);
        if ([] === $attributes) {
            return [null, new CastToScalar()];
        }

        if (1 < count($attributes)) {
            throw new MappingFailed('Using more than one '.Column::class.' attribute on a class property or method is not supported.');
        }

        /** @var Column $cell */
        $cell = $attributes[0]->newInstance();
        $offset = $cell->offset;
        $cast = $this->getTypeCasting($cell);
        if (is_int($offset)) {
            return match (true) {
                0 > $offset => throw new MappingFailed('cell integer position can only be positive or equals to 0; received `'.$offset.'`'),
                [] !== $header && $offset > count($header) - 1 => throw new MappingFailed('cell integer position can not exceed header cell count.'),
                default => [$offset, $cast],
            };
        }

        if ([] === $header) {
            throw new MappingFailed('Cell name as string are only supported if the tabular data has a non-empty header.');
        }

        /** @var int<0, max>|false $index */
        $index = array_search($offset, $header, true);
        if (false === $index) {
            throw new MappingFailed('The offset `'.$offset.'` could not be found in the header; Pleaser verify your header data.');
        }

        return [$index, $cast];
    }

    private function getTypeCasting(Column $cell): TypeCasting
    {
        $caster = $cell->cast;
        if (null === $caster) {
            return new CastToScalar();
        }

        /** @var TypeCasting $cast */
        $cast = new $caster(...$cell->castArguments);

        return $cast;
    }
}
