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

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use Throwable;
use ValueError;

use function is_string;
use function is_subclass_of;
use function trim;

final class DateTimeField extends FieldEvaluator implements Field
{
    /** @var non-empty-string */
    public readonly string $format;
    public readonly DateTimeZone $timezone;
    /** @var class-string<DateTimeImmutable|DateTime> */
    public readonly string $outputClass;

    /** @var list<non-empty-string> */
    private const FORMAT_MACHINES = [
        'Y-m-d',
        'Y-m-d H:i:s',
        'Y-m-d\TH:i:s',
        DateTimeInterface::RFC3339,
        DateTimeInterface::RFC3339_EXTENDED,
        DateTimeInterface::ISO8601_EXPANDED,
        'U',
    ];

    /** @var list<non-empty-string> */
    private const FORMAT_LOCALIZED = [
        // Europe Dates
        'd/m/Y',
        'd-m-Y',
        'd.m.Y',
        // American Dates
        'm/d/Y',
        'm-d-Y',
        'm.d.Y',
    ];

    /**
     * @param non-empty-string $format
     * @param class-string<DateTimeImmutable|DateTime> $outputClass
     */
    public function __construct(
        string $format,
        DateTimeZone|string|null $timezone = null,
        string $outputClass = DateTimeImmutable::class,
        float $confidenceThreshold = 0.8,
    ) {
        $format = trim($format);
        '' !== $format || throw new ValueError('The date field format can not be empty.');
        $timezone = self::filterTimezone($timezone);
        self::filterDateTimeInterfaceClass($outputClass);

        parent::__construct($confidenceThreshold);
        $this->format = $format;
        $this->timezone = $timezone;
        $this->outputClass = $outputClass;
    }

    /**
     * @param class-string<DateTimeImmutable|DateTime> $outputClass
     */
    public static function common(
        DateTimeZone|string|null $timezone = null,
        string $outputClass = DateTimeImmutable::class,
    ): FieldList {
        return self::machine($timezone, $outputClass)->append(self::localized($timezone, $outputClass));
    }

    /**
     * @param class-string<DateTimeImmutable|DateTime> $outputClass
     */
    public static function machine(
        DateTimeZone|string|null $timezone = null,
        string $outputClass = DateTimeImmutable::class,
    ): FieldList {
        return self::fromFormat(self::FORMAT_MACHINES, $timezone, $outputClass, .8);
    }

    /**
     * @param class-string<DateTimeImmutable|DateTime> $outputClass
     */
    public static function localized(
        DateTimeZone|string|null $timezone = null,
        string $outputClass = DateTimeImmutable::class,
    ): FieldList {
        return self::fromFormat(self::FORMAT_LOCALIZED, $timezone, $outputClass, .7);
    }

    /**
     * @param class-string<DateTimeImmutable|DateTime> $outputClass
     */
    public static function timestamp(
        string $outputClass = DateTimeImmutable::class,
        float $confidenceThreshold = .8
    ): self {
        return new self(
            format: 'U',
            timezone: 'UTC',
            outputClass: $outputClass,
            confidenceThreshold: $confidenceThreshold,
        );
    }

    /**
     * @param iterable<non-empty-string> $formats
     * @param class-string<DateTimeImmutable|DateTime> $outputClass
     */
    public static function fromFormat(
        iterable $formats,
        DateTimeZone|string|null $timezone = null,
        string $outputClass = DateTimeImmutable::class,
        float $confidenceThreshold = 0.8,
    ): FieldList {
        $res = [];
        foreach ($formats as $format) {
            $res[] = new self($format, $timezone, $outputClass, $confidenceThreshold);
        }

        return new FieldList(...$res);
    }

    private static function filterDateTimeInterfaceClass(string $className): void
    {
        is_subclass_of($className, DateTimeInterface::class)
        || throw new ValueError('The date field class '.$className.' does not implement the DateTimeInterface interface.');
    }

    private static function filterTimezone(DateTimeZone|string|null $timeZone): DateTimeZone
    {
        if (null === $timeZone) {
            return new DateTimeZone('UTC');
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
        return FieldType::Datetime;
    }

    public function name(): string
    {
        $format = ('U' === $this->format) ? 'timestamp' : $this->format;

        return FieldType::Datetime->value.'(format='.$format.',timezone='.$this->timezone->getName().')';
    }

    public function parse(mixed $value): ?DateTimeInterface
    {
        if ($value instanceof DateTimeInterface) {
            return $value::class === $this->outputClass ? $value : $this->outputClass::createFromInterface($value);
        }

        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);
        if ('' === $value) {
            return null;
        }

        try {
            $value = $this->outputClass::createFromFormat($this->format, $value, $this->timezone);
            if (false === $value) {
                return null;
            }

            $errors = $this->outputClass::getLastErrors();
            if (
                (isset($errors['warning_count']) && 0 < $errors['warning_count']) ||
                (isset($errors['error_count']) && 0 < $errors['error_count'])
            ) {
                return null;
            }

            return $value;
        } catch (Throwable) {
            return null;
        }
    }

    public function metadata(): FieldMetadata
    {
        return new FieldMetadata([
            'format' => $this->format,
            'timezone' => $this->timezone->getName(),
            'class' => $this->outputClass,
        ]);
    }
}
