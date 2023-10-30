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

use League\Csv\Attribute\Column;
use League\Csv\TypeCasting\CastToScalar;
use League\Csv\TypeCasting\TypeCasting;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;
use TypeError;

/**
 * @template TValue
 */
final class RecordMapper
{
    /** @var array<CellMapper>  */
    public readonly array $mappers;

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
        $addMapper = function (array $mapper, ReflectionProperty|ReflectionMethod $accessor) use ($header) {
            [$offset, $caster] = $this->getColumn($accessor, $header);

            return match ($offset) {
                null => $mapper,
                default => [...$mapper, new CellMapper($offset, $accessor, $caster)],
            };
        };

        $class = new ReflectionClass($this->className);

        $this->mappers = array_reduce(
            [...$class->getProperties(), ...$class->getMethods(ReflectionMethod::IS_PUBLIC)],
            $addMapper,
            []
        );
    }

    public function __invoke(array $record): mixed
    {
        $record = array_values($record);
        $object = (new ReflectionClass($this->className))->newInstanceWithoutConstructor();
        foreach ($this->mappers as $mapper) {
            ($mapper)($object, $record[$mapper->offset]);
        }

        return $object;
    }

    /**
     * @param array<string> $header
     *
     * @throws RuntimeException
     *
     * @return array{0:int<0, max>|null, 1:TypeCasting}
     */
    private function getColumn(ReflectionProperty|ReflectionMethod $target, array $header): array
    {
        $attributes = $target->getAttributes(Column::class, ReflectionAttribute::IS_INSTANCEOF);
        if ([] === $attributes) {
            return [null, new CastToScalar()];
        }

        if (1 < count($attributes)) {
            throw new RuntimeException('Using multiple '.Column::class.' attributes on '.$target->getDeclaringClass()->getName().'::'.$target->getName().' is not supported.');
        }

        /** @var Column $column */
        $column = $attributes[0]->newInstance();
        $offset = $column->offset;
        $cast = $this->getCast($column);
        if (is_int($offset)) {
            return match (true) {
                0 > $offset => throw new RuntimeException(__CLASS__.' can only use 0 or positive column indices.'),
                default => [$offset, $cast],
            };
        }

        if ([] === $header) {
            throw new RuntimeException(__CLASS__.' can only use named column if the tabular data has a non-empty header.');
        }

        /** @var int<0, max>|false $index */
        $index = array_search($offset, $header, true);
        if (false === $index) {
            throw new RuntimeException(__CLASS__.' cound not find the offset `'.$offset.'` in the header; Pleaser verify your header data.');
        }

        return [$index, $cast];
    }

    private function getCast(Column $column): TypeCasting
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
