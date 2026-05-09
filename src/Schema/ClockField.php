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

use ValueError;

use function array_map;
use function ctype_digit;
use function implode;
use function is_string;
use function preg_match;
use function strlen;
use function trim;

final class ClockField extends FieldEvaluator implements Field
{
    /** @var non-empty-string */
    private readonly string $pattern;

    private function __construct(
        public readonly string $separator,
        public readonly ClockPrecision $clockPrecision,
        public readonly ClockStyle $clockStyle,
        float $confidenceThreshold = 0.8
    ) {
        (1 === strlen($separator) && !ctype_digit($this->separator)) || throw new ValueError('The separator character must be a non-empty single byte string.');

        parent::__construct($confidenceThreshold);

        $this->pattern = $this->generatePattern();
    }

    public static function seconds(string $separator = ':', ClockStyle $clockStyle = ClockStyle::Padded, float $confidenceThreshold = 0.8): self
    {
        return new self($separator, ClockPrecision::HoursMinutesSeconds, $clockStyle, $confidenceThreshold);
    }

    public static function minutes(string $separator = ':', ClockStyle $clockStyle = ClockStyle::Padded, float $confidenceThreshold = 0.8): self
    {
        return new self($separator, ClockPrecision::HoursMinutes, $clockStyle, $confidenceThreshold);
    }

    public static function hours(string $separator = ':', ClockStyle $clockStyle = ClockStyle::Padded, float $confidenceThreshold = 0.8): self
    {
        return new self($separator, ClockPrecision::Hours, $clockStyle, $confidenceThreshold);
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
        $precision = match ($this->clockPrecision) {
            ClockPrecision::Hours => 'hours',
            ClockPrecision::HoursMinutes => 'hours_minutes',
            ClockPrecision::HoursMinutesSeconds => 'hours_minutes_seconds',
        };

        $style = match ($this->clockStyle) {
            ClockStyle::NonPadded => 'non_padded',
            ClockStyle::Padded => 'padded',
        };

        return FieldType::Time->value.'(precision='.$precision.',style='.$style.',separator='.$this->separator.')';
    }

    public function parse(mixed $value): ?string
    {
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
        $digit = fn () => ClockStyle::Padded === $this->clockStyle ? '\d{2}' : '\d{1,2}';

        $patternParts = array_map(
            fn (string $part): string => "(?<{$part}>".$digit().')',
            match ($this->clockPrecision) {
                ClockPrecision::Hours => ['hour'],
                ClockPrecision::HoursMinutes => ['hour', 'minute'],
                ClockPrecision::HoursMinutesSeconds => ['hour', 'minute', 'second'],
            }
        );

        return '/^'.implode($this->separator, $patternParts).'$/';
    }
}
