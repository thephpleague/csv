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

use JsonException;
use League\Csv\Exception;
use League\Csv\Reader;
use ReflectionParameter;
use ReflectionProperty;

use function array_map;
use function explode;
use function filter_var;
use function is_array;
use function json_decode;
use function strlen;

use const FILTER_REQUIRE_ARRAY;
use const JSON_THROW_ON_ERROR;

/**
 * @implements TypeCasting<array|null>
 */
final class CastToArray implements TypeCasting
{
    private readonly Type $type;
    private readonly bool $isNullable;
    private ArrayShape $shape;
    private int $filterFlag;
    /** @var non-empty-string */
    private string $separator = ',';
    private string $delimiter = '';
    private string $enclosure = '"';
    /** @var int<1, max> $depth */
    private int $depth = 512;
    private int $flags = 0;
    private ?array $default = null;
    private bool $trimElementValueBeforeCasting = false;
    private ?int $headerOffset = null;
    private readonly TypeCastingInfo $info;

    /**
     * @throws MappingFailed
     */
    public function __construct(ReflectionProperty|ReflectionParameter $reflectionProperty)
    {
        [$this->type, $this->isNullable] = $this->init($reflectionProperty);
        $this->shape = ArrayShape::List;
        $this->filterFlag = Type::String->filterFlag();
        $this->info = TypeCastingInfo::fromAccessor($reflectionProperty);
    }

    public function info(): TypeCastingInfo
    {
        return $this->info;
    }

    /**
     * @param non-empty-string $delimiter
     * @param non-empty-string $separator
     * @param int<1, max> $depth
     *
     * @throws MappingFailed
     */
    public function setOptions(
        ?array $default = null,
        ArrayShape|string $shape = ArrayShape::List,
        string $separator = ',',
        string $delimiter = ',',
        string $enclosure = '"',
        int $depth = 512,
        int $flags = 0,
        Type|string $type = Type::String,
        bool $trimElementValueBeforeCasting = false,
        ?int $headerOffset = null,
    ): void {
        if (!$shape instanceof ArrayShape) {
            $shape = ArrayShape::tryFrom($shape) ?? throw new MappingFailed('Unable to resolve the array shape; Verify your options arguments.');
        }

        if (!$type instanceof Type) {
            $type = Type::tryFrom($type) ?? throw new MappingFailed('Unable to resolve the array value type; Verify your options arguments.');
        }

        $this->shape = $shape;
        $this->depth = $depth;
        $this->separator = $separator;
        $this->delimiter = $delimiter;
        $this->enclosure = $enclosure;
        $this->flags = $flags;
        $this->default = $default;
        $this->filterFlag = match (true) {
            1 > $this->depth && $this->shape->equals(ArrayShape::Json) => throw new MappingFailed('the json depth can not be less than 1.'),
            1 > strlen($this->separator) && $this->shape->equals(ArrayShape::List) => throw new MappingFailed('expects separator to be a non-empty string for list conversion; empty string given.'),
            1 !== strlen($this->delimiter) && $this->shape->equals(ArrayShape::Csv) => throw new MappingFailed('expects delimiter to be a single character for CSV conversion; `'.$this->delimiter.'` given.'),
            1 !== strlen($this->enclosure) && $this->shape->equals(ArrayShape::Csv) => throw new MappingFailed('expects enclosure to be a single character; `'.$this->enclosure.'` given.'),
            default => $this->resolveFilterFlag($type),
        };
        $this->trimElementValueBeforeCasting = $trimElementValueBeforeCasting;
        $this->headerOffset = $headerOffset;
    }

    public function toVariable(mixed $value): ?array
    {
        if (null === $value) {
            return match (true) {
                $this->isNullable,
                Type::Mixed->equals($this->type) => $this->default,
                default => throw TypeCastingFailed::dueToNotNullableType($this->type->value, info: $this->info),
            };
        }

        if ('' === $value) {
            return [];
        }

        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value)) {
            throw TypeCastingFailed::dueToInvalidValue($value, $this->type->value, info: $this->info);
        }

        if ($this->shape->equals(ArrayShape::Json)) {
            try {
                $data = json_decode($value, true, $this->depth, $this->flags | JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw TypeCastingFailed::dueToInvalidValue($value, $this->type->value, $exception, $this->info);
            }

            if (!is_array($data)) {
                throw TypeCastingFailed::dueToInvalidValue($value, $this->type->value, info: $this->info);
            }

            return $data;
        }

        if ($this->shape->equals(ArrayShape::Csv)) {
            try {
                $data = Reader::createFromString($value);
                $data->setDelimiter($this->delimiter);
                $data->setEnclosure($this->enclosure);
                $data->setEscape('');
                $data->setHeaderOffset($this->headerOffset);
                if ($this->trimElementValueBeforeCasting) {
                    $data->addFormatter($this->trimString(...));
                }
                $data->addFormatter($this->filterElement(...));

                return [...$data];
            } catch (Exception $exception) {
                throw TypeCastingFailed::dueToInvalidValue($value, $this->type->value, $exception, $this->info);
            }
        }

        $data = explode($this->separator, $value);

        return $this->filterElement(match (true) {
            $this->trimElementValueBeforeCasting => $this->trimString($data),
            default => $data,
        });
    }

    private function trimString(array $record): array
    {
        return array_map(
            fn (mixed $value): mixed => is_string($value) ? trim($value) : $value,
            $record
        );
    }

    private function filterElement(array $record): array
    {
        return filter_var($record, $this->filterFlag, FILTER_REQUIRE_ARRAY);
    }

    /**
     * @throws MappingFailed if the type is not supported
     */
    private function resolveFilterFlag(?Type $type): int
    {
        return match (true) {
            $this->shape->equals(ArrayShape::Json) => Type::String->filterFlag(),
            $type instanceof Type && $type->isOneOf(Type::Bool, Type::True, Type::False, Type::String, Type::Float, Type::Int) => $type->filterFlag(),
            default => throw new MappingFailed('Only scalar type are supported for `array` value casting.'),
        };
    }

    /**
     * @return array{0:Type, 1:bool}
     */
    private function init(ReflectionProperty|ReflectionParameter $reflectionProperty): array
    {
        if (null === $reflectionProperty->getType()) {
            return [Type::Mixed, true];
        }

        $type = null;
        $isNullable = false;
        foreach (Type::list($reflectionProperty) as $found) {
            if (!$isNullable && $found[1]->allowsNull()) {
                $isNullable = true;
            }

            if (null === $type && $found[0]->isOneOf(Type::Mixed, Type::Array, Type::Iterable)) {
                $type = $found;
            }
        }

        if (null === $type) {
            throw MappingFailed::dueToTypeCastingUnsupportedType($reflectionProperty, $this, 'array', 'iterable', 'mixed');
        }

        return [$type[0], $isNullable];
    }
}
