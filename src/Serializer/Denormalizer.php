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

    public static function unregisterType(string $type): void
    {
        ClosureCasting::unregister($type);
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
     * @param class-string $className
     *
     * @throws MappingFailed
     */
    private function setClass(string $className): ReflectionClass
    {
        if (!class_exists($className)) {
            throw new MappingFailed('The class `'.$className.'` does not exist or was not found.');
        }

        $class = new ReflectionClass($className);
        if ($class->isInternal() && $class->isFinal()) {
            throw new MappingFailed('The class `'.$className.'` can not be deserialize using `'.self::class.'`.');
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
        foreach ([...$this->properties, ...$this->class->getMethods(ReflectionMethod::IS_PUBLIC)] as $accessor) {
            $propertySetter = $this->findPropertySetter($accessor, $propertyNames);
            if (null !== $propertySetter) {
                $propertySetters[] = $propertySetter;
            }
        }

        return match ([]) {
            $propertySetters => throw new MappingFailed('No property or method from `'.$this->class->getName().'` can be used for deserialization.'),
            default => $propertySetters,
        };
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
            if (!$accessor instanceof ReflectionProperty || $accessor->isStatic() || !$accessor->isPublic()) {
                return null;
            }

            /** @var int|false $offset */
            $offset = array_search($accessor->getName(), $propertyNames, true);

            return match (false) {
                $offset => null,
                default => new PropertySetter($accessor, $offset, $this->resolveTypeCasting($accessor)),
            };
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

        return match (false) {
            $index => throw new MappingFailed('The offset `'.$offset.'` could not be found in the header; Pleaser verify your header data.'),
            default => new PropertySetter($accessor, $index, $cast),
        };
    }

    /**
     * @throws MappingFailed
     */
    private function getTypeCasting(Cell $cell, ReflectionProperty|ReflectionMethod $accessor): TypeCasting
    {
        if (array_key_exists('reflectionProperty', $cell->castArguments)) {
            throw new MappingFailed('The key `reflectionProperty` can not be used with `castArguments`.');
        }

        $reflectionProperty = match (true) {
            $accessor instanceof ReflectionMethod => $accessor->getParameters()[0],
            $accessor instanceof ReflectionProperty => $accessor,
        };

        $typeCaster = $cell->cast;
        if (null === $typeCaster) {
            return $this->resolveTypeCasting($reflectionProperty, $cell->castArguments);
        }

        if (!class_exists($typeCaster) || !(new ReflectionClass($typeCaster))->implementsInterface(TypeCasting::class)) {
            throw new MappingFailed('`'.$typeCaster.'` must be an resolvable class implementing the `'.TypeCasting::class.'` interface.');
        }

        try {
            /** @var TypeCasting $cast */
            $cast = new $typeCaster(...$cell->castArguments, ...['reflectionProperty' => $reflectionProperty]);

            return $cast;
        } catch (MappingFailed $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new MappingFailed('Unable to load the casting mechanism. Please verify your casting arguments', 0, $exception);
        }
    }

    private function resolveTypeCasting(ReflectionProperty|ReflectionParameter $reflectionProperty, array $arguments = []): TypeCasting
    {
        $exception = new MappingFailed(match (true) {
            $reflectionProperty instanceof ReflectionParameter => 'The setter method argument `'.$reflectionProperty->getName().'` must be typed with a supported type.',
            $reflectionProperty instanceof ReflectionProperty => 'The property `'.$reflectionProperty->getName().'` must be typed with a supported type.',
        });

        $reflectionType = $reflectionProperty->getType() ?? throw $exception;

        try {
            $arguments['reflectionProperty'] = $reflectionProperty;

            return ClosureCasting::supports($reflectionProperty) ?
                new ClosureCasting(...$arguments) :
                match (Type::tryFromReflectionType($reflectionType)) {
                    Type::Mixed, Type::Null, Type::String => new CastToString(...$arguments),
                    Type::Iterable, Type::Array => new CastToArray(...$arguments),
                    Type::False, Type::True, Type::Bool => new CastToBool(...$arguments),
                    Type::Float => new CastToFloat(...$arguments),
                    Type::Int => new CastToInt(...$arguments),
                    Type::Date => new CastToDate(...$arguments),
                    Type::Enum => new CastToEnum(...$arguments),
                    default => throw $exception,
                };
        } catch (MappingFailed $mappingFailed) {
            throw $mappingFailed;
        } catch (Throwable $exception) {
            throw new MappingFailed('Unable to load the casting mechanism. Please verify your casting arguments', 0, $exception);
        }
    }
}
