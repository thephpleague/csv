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

use const JSON_THROW_ON_ERROR;

/**
 * @implements TypeCasting<array|null>
 */
final class CastToArray implements TypeCasting
{
    public const TYPE_JSON = 'json';
    public const TYPE_LIST = 'list';
    public const TYPE_CSV = 'csv';

    public static function supports(string $type): bool
    {
        return in_array(ltrim($type, '?'), ['array', 'iterable'], true);
    }

    /**
     * @param 'json'|'csv'|'list' $type
     * @param non-empty-string $delimiter
     * @param int<1, max> $jsonDepth
     *
     * @throws MappingFailed
     */
    public function __construct(
        private readonly string $type,
        private readonly string $delimiter = ',',
        private readonly string $enclosure = '"',
        private readonly int $jsonDepth = 512,
        private readonly int $jsonFlags = 0,
    ) {
        match (true) {
            !in_array($type, [self::TYPE_JSON, self::TYPE_LIST, self::TYPE_CSV], true) => throw new MappingFailed('Unable to resolve the array.'),
            1 > $this->jsonDepth => throw new MappingFailed('the json depth can not be less than 1.'), /* @phpstan-ignore-line */
            1 > strlen($this->delimiter) && self::TYPE_LIST === $this->type => throw new MappingFailed('expects delimiter to be a non-empty string for list conversion; emtpy string given.'),  /* @phpstan-ignore-line */
            1 !== strlen($this->delimiter) && self::TYPE_CSV === $this->type => throw new MappingFailed('expects delimiter to be a single character for CSV conversion; `'.$this->delimiter.'` given.'),
            1 !== strlen($this->enclosure) => throw new MappingFailed('expects enclosire to be a single character; `'.$this->enclosure.'` given.'),
            default => null,
        };
    }

    public function toVariable(?string $value, string $type): ?array
    {
        if (in_array($value, ['', null], true)) {
            return match (true) {
                str_starts_with($type, '?') => null,
                default => throw new TypeCastingFailed('Unable to convert the `null` value.'),
            };
        }

        if (!in_array(ltrim($type, '?'), ['array', 'iterable'], true)) {
            throw new TypeCastingFailed('The property type is not an array or an iterable structure.');
        }

        try {
            $result = match ($this->type) {
                self::TYPE_JSON => json_decode($value, true, $this->jsonDepth, $this->jsonFlags | JSON_THROW_ON_ERROR),
                self::TYPE_LIST => explode($this->delimiter, $value),
                default => str_getcsv($value, $this->delimiter, $this->enclosure, ''),
            };

            if (!is_array($result)) {
                throw new TypeCastingFailed('Unable to cast the given data `'.$value.'` to a PHP array.');
            }

            return $result;

        } catch (JsonException $exception) {
            throw new TypeCastingFailed('Unable to cast the given data `'.$value.'` to a PHP array.', 0, $exception);
        }
    }
}
