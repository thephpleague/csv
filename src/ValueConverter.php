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

use DateTimeImmutable;

final class ValueConverter
{
    private const REGEXP_INTEGER = '/^[-+]?\d+$/';
    private const REGEXP_FLOAT = '/^[-+]?\d+[.,]\d*([e][+-]?\d+)?$/';
    private const REGEXP_BOOLEAN = '/^(true|false|yes|no|on|off)$/i';

    private function __construct(
        private ?string $dateFormat = null
    ) {
    }

    public static function create(): self
    {
        return new self();
    }

    public static function includeDate(string $dateFormat): self
    {
        return new self($dateFormat);
    }

    public function __invoke(array $record): array
    {
        return array_map($this->convert(...), $record);
    }

    public function convert(?string $value): int|string|float|DateTimeImmutable|null|bool
    {
        return match (true) {
            null === $value => null,
            1 === preg_match(self::REGEXP_BOOLEAN, $value) => in_array(strtolower($value), ['true' , 'yes' , 'on'], true),
            1 === preg_match(self::REGEXP_INTEGER, $value) => (int) $value,
            1 === preg_match(self::REGEXP_FLOAT, $value) => (float) str_replace(',', '.', $value),
            null !== $this->dateFormat && (false !== ($date = DateTimeImmutable::createFromFormat($this->dateFormat, $value))) => $date,
            default => $value,
        };
    }

    public function convertToInteger(?string $value): int|string|null
    {
        return match (true) {
            null === $value => null,
            1 === preg_match(self::REGEXP_INTEGER, $value) => (int) $value,
            default => $value,
        };
    }

    public function convertToBoolean(?string $value): bool|string|null
    {
        return match (true) {
            null === $value => null,
            1 === preg_match(self::REGEXP_BOOLEAN, $value) => in_array(strtolower($value), ['true' , 'yes' , 'on'], true),
            default => $value,
        };
    }

    public function convertToFloat(?string $value): float|string|null
    {
        return match (true) {
            null === $value => null,
            1 === preg_match(self::REGEXP_FLOAT, $value) => (float) str_replace(',', '.', $value),
            default => $value,
        };
    }

    public function convertToDate(?string $value): DateTimeImmutable|string|null
    {
        return match (true) {
            null === $value => null,
            null !== $this->dateFormat && (false !== ($date = DateTimeImmutable::createFromFormat($this->dateFormat, $value))) => $date,
            default => $value,
        };
    }
}
