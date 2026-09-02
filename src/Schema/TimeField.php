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

use DateTimeInterface;
use ValueError;

use function array_map;
use function ctype_digit;
use function implode;
use function is_string;
use function preg_match;
use function preg_quote;
use function strlen;
use function trim;

final class TimeField extends FieldEvaluator implements Field
{
    /** @var non-empty-string */
    private readonly string $pattern;

    private function __construct(
        public readonly string $separator,
        public readonly TimePrecision $precision,
        public readonly TimePadding $padding,
        float $confidenceThreshold = 0.8
    ) {
        (1 === strlen($separator) && !ctype_digit($this->separator)) || throw new ValueError('The separator character must be a non-empty single byte string.');

        parent::__construct($confidenceThreshold);

        $this->pattern = $this->generatePattern();
    }

    public static function seconds(string $separator = ':', TimePadding $padding = TimePadding::Padded, float $confidenceThreshold = 0.8): self
    {
        return new self($separator, TimePrecision::HoursMinutesSeconds, $padding, $confidenceThreshold);
    }

    public static function minutes(string $separator = ':', TimePadding $padding = TimePadding::Padded, float $confidenceThreshold = 0.8): self
    {
        return new self($separator, TimePrecision::HoursMinutes, $padding, $confidenceThreshold);
    }

    public static function hours(string $separator = ':', TimePadding $padding = TimePadding::Padded, float $confidenceThreshold = 0.8): self
    {
        return new self($separator, TimePrecision::Hours, $padding, $confidenceThreshold);
    }

    public function type(): FieldType
    {
        return FieldType::Time;
    }

    public function metadata(): FieldMetadata
    {
        return new FieldMetadata();
    }

    public function name(): string
    {
        $precision = match ($this->precision) {
            TimePrecision::Hours => 'hours',
            TimePrecision::HoursMinutes => 'hours_minutes',
            TimePrecision::HoursMinutesSeconds => 'hours_minutes_seconds',
        };

        $paddingMode = match ($this->padding) {
            TimePadding::Unpadded => 'un_padded',
            TimePadding::Padded => 'padded',
        };

        return FieldType::Time->value.'(precision='.$precision.',padding='.$paddingMode.',separator='.$this->separator.')';
    }

    public function parse(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('H:i:s');
        }

        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);
        if (1 !== preg_match($this->pattern, $value, $found)) {
            return null;
        }

        $hour = (int) $found['hour'];
        $minute = (int) ($found['minute'] ?? 0);
        $second = (int) ($found['second'] ?? 0);

        return ($hour > 23 || $minute > 59 || $second > 59)
            ? null
            : $this->formatTimePart($hour)
            .$this->separator
            .$this->formatTimePart($minute)
            .$this->separator
            .$this->formatTimePart($second);
    }

    private function formatTimePart(int $value): string
    {
        return ($value < 10 ? '0' : '').$value;
    }

    /**
     * @return non-empty-string
     */
    private function generatePattern(): string
    {
        $digit = fn () => TimePadding::Padded === $this->padding ? '\d{2}' : '\d{1,2}';

        $patternParts = array_map(
            fn (string $part): string => "(?<{$part}>".$digit().')',
            match ($this->precision) {
                TimePrecision::Hours => ['hour'],
                TimePrecision::HoursMinutes => ['hour', 'minute'],
                TimePrecision::HoursMinutesSeconds => ['hour', 'minute', 'second'],
            }
        );

        return '/^'.implode(preg_quote($this->separator, '/'), $patternParts).'$/';
    }
}
