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

use function explode;
use function is_array;
use function json_decode;
use function ltrim;
use function str_getcsv;
use function str_starts_with;
use function strlen;

use const FILTER_REQUIRE_ARRAY;
use const JSON_THROW_ON_ERROR;

/**
 * @implements TypeCasting<array|null>
 */
final class CastToArray implements TypeCasting
{
    public const SHAPE_JSON = 'json';
    public const SHAPE_LIST = 'list';
    public const SHAPE_CSV = 'csv';

    private readonly string $class;
    private readonly bool $isNullable;
    private readonly int $filterFlag;
    private readonly ArrayShape $shape;

    /**
     * @param non-empty-string $delimiter
     * @param int<1, max> $jsonDepth
     *
     * @throws MappingFailed
     */
    public function __construct(
        string $propertyType,
        private readonly ?array $default = null,
        ArrayShape|string $shape = 'list',
        private readonly string $delimiter = ',',
        private readonly string $enclosure = '"',
        private readonly int $jsonDepth = 512,
        private readonly int $jsonFlags = 0,
        BasicType|string $type = BasicType::String,
    ) {
        $baseType = BasicType::tryFromPropertyType($propertyType);
        if (null === $baseType || !$baseType->isOneOf(BasicType::Mixed, BasicType::Array, BasicType::Iterable)) {
            throw new MappingFailed('The property type `'.$propertyType.'` is not supported; an `array` or an `iterable` structure is required.');
        }

        $this->class = ltrim($propertyType, '?');
        $this->isNullable = str_starts_with($propertyType, '?');

        if (!$shape instanceof ArrayShape) {
            $shape = ArrayShape::tryFrom($shape) ?? throw new MappingFailed('Unable to resolve the array shape; Verify your cast arguments.');
        }

        $this->shape = $shape;
        $this->filterFlag = match (true) {
            1 > $this->jsonDepth && $this->shape->equals(ArrayShape::Json) => throw new MappingFailed('the json depth can not be less than 1.'),
            1 > strlen($this->delimiter) && $this->shape->equals(ArrayShape::List) => throw new MappingFailed('expects delimiter to be a non-empty string for list conversion; emtpy string given.'),
            1 !== strlen($this->delimiter) && $this->shape->equals(ArrayShape::Csv) => throw new MappingFailed('expects delimiter to be a single character for CSV conversion; `'.$this->delimiter.'` given.'),
            1 !== strlen($this->enclosure) && $this->shape->equals(ArrayShape::Csv) => throw new MappingFailed('expects enclosure to be a single character; `'.$this->enclosure.'` given.'),
            default => $this->resolveFilterFlag($type),
        };
    }

    public function toVariable(?string $value): ?array
    {
        if (null === $value) {
            return match (true) {
                $this->isNullable,
                BasicType::tryFrom($this->class)?->equals(BasicType::Mixed) => $this->default,
                default => throw new TypeCastingFailed('The `null` value can not be cast to an `array`; the property type is not nullable.'),
            };
        }

        if ('' === $value) {
            return [];
        }

        try {
            $result = match ($this->shape) {
                ArrayShape::Json => json_decode($value, true, $this->jsonDepth, $this->jsonFlags | JSON_THROW_ON_ERROR),
                ArrayShape::List => filter_var(explode($this->delimiter, $value), $this->filterFlag, FILTER_REQUIRE_ARRAY),
                ArrayShape::Csv => filter_var(str_getcsv($value, $this->delimiter, $this->enclosure, ''), $this->filterFlag, FILTER_REQUIRE_ARRAY),
            };

            if (!is_array($result)) {
                throw new TypeCastingFailed('Unable to cast the given data `'.$value.'` to a PHP array.');
            }

            return $result;

        } catch (JsonException $exception) {
            throw new TypeCastingFailed('Unable to cast the given data `'.$value.'` to a PHP array.', 0, $exception);
        }
    }

    /**
     * @throws MappingFailed if the type is not supported
     */
    private function resolveFilterFlag(BasicType|string $type): int
    {
        if ($this->shape->equals(ArrayShape::Json)) {
            return BasicType::String->filterFlag();
        }

        if (!$type instanceof BasicType) {
            $type = BasicType::tryFrom($type);
        }

        return match (true) {
            !$type instanceof BasicType,
            !$type->isScalar() => throw new MappingFailed('Only scalar type are supported for `array` value casting.'),
            default => $type->filterFlag(),
        };
    }
}
