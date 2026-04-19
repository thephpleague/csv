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

namespace League\Csv\Schema;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use ValueError;

use function array_map;
use function array_values;
use function is_string;
use function iterator_to_array;
use function trim;

final class DateField extends FieldEvaluator implements Field
{
    /** @var non-empty-string */
    public readonly string $format;
    public readonly ?DateTimeZone $timezone;

    public function __construct(string $format, DateTimeZone|string|null $timezone = null, float $confidenceThreshold = 0.8)
    {
        $format = trim($format);
        '' !== $format || throw new ValueError('The date field format can not be empty.');
        $timezone = self::filterTimezone($timezone);

        parent::__construct($confidenceThreshold);
        $this->format = $format;
        $this->timezone = $timezone;
    }

    public static function common(DateTimeZone|string|null $timezone = null): FieldList
    {
        return self::machine($timezone)->append(self::localized($timezone));
    }

    public static function machine(DateTimeZone|string|null $timezone = null): FieldList
    {
        $formats = [
            'Y-m-d',
            'Y-m-d H:i:s',
            'Y-m-d\TH:i:s',
            DateTimeInterface::RFC3339,
            DateTimeInterface::RFC3339_EXTENDED,
            DateTimeInterface::ISO8601_EXPANDED,
        ];

        return self::fromFormat($formats, $timezone, .8);
    }

    public static function localized(DateTimeZone|string|null $timezone = null): FieldList
    {
        $formats = [
            // Europe Dates
            'd/m/Y',
            'd-m-Y',
            'd.m.Y',
            // American Dates
            'm/d/Y',
            'm-d-Y',
            'm.d.Y',
        ];

        return self::fromFormat($formats, $timezone, .7);
    }

    /**
     * @param iterable<non-empty-string> $formats
     */
    public static function fromFormat(
        iterable $formats,
        DateTimeZone|string|null $timezone = null,
        float $confidenceThreshold = 0.8
    ): FieldList {
        return new FieldList(...array_values(array_map(
            fn (string $format): DateField => new DateField($format, $timezone, $confidenceThreshold),
            iterator_to_array($formats)
        )));
    }

    private static function filterTimezone(DateTimeZone|string|null $timeZone): ?DateTimeZone
    {
        if (null === $timeZone) {
            return null;
        }

        if ($timeZone instanceof DateTimeZone) {
            return $timeZone;
        }

        try {
            return new DateTimeZone($timeZone);
        } catch (Exception $exception) {
            throw new ValueError('The date field timezone value `'.$timeZone.'` is invalid.', previous: $exception);
        }
    }

    public function type(): FieldType
    {
        return FieldType::Date;
    }

    public function name(): string
    {
        return FieldType::Date->value;
    }

    public function parse(mixed $value): ?DateTimeImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return $value instanceof DateTimeImmutable ? $value : DateTimeImmutable::createFromInterface($value);
        }

        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);
        if ('' === $value) {
            return null;
        }

        try {
            $value = DateTimeImmutable::createFromFormat($this->format, $value, $this->timezone);
            if (false === $value) {
                return null;
            }

            $errors = DateTimeImmutable::getLastErrors();
            if (
                (isset($errors['warning_count']) && 0 < $errors['warning_count']) ||
                (isset($errors['error_count']) && 0 < $errors['error_count'])
            ) {
                return null;
            }

            return $value;
        } catch (ValueError) {
            return null;
        }
    }

    public function metadata(): FieldMetadata
    {
        return new FieldMetadata([
            'format' => $this->format,
            'timezone' => $this->timezone?->getName(),
        ]);
    }
}
