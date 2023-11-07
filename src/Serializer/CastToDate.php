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

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Throwable;

/**
 * @implements TypeCasting<DateTimeImmutable|DateTime|null>
 */
final class CastToDate implements TypeCasting
{
    private readonly ?DateTimeZone $timezone;
    private readonly string $class;
    private readonly bool $isNullable;
    private readonly DateTimeImmutable|DateTime|null $default;

    public static function supports(string $propertyType): bool
    {
        $formattedType = ltrim($propertyType, '?');

        return match (true) {
            BuiltInType::Mixed->value === $formattedType,
            DateTimeInterface::class === $formattedType => true,
            !class_exists($formattedType),
            false === ($foundInterfaces = class_implements($formattedType)),
            !in_array(DateTimeInterface::class, $foundInterfaces, true) => false,
            default => true,
        };
    }

    /**
     * @throws MappingFailed
     */
    public function __construct(
        string $propertyType,
        ?string $default = null,
        private readonly ?string $format = null,
        DateTimeZone|string|null $timezone = null,
    ) {
        if (!self::supports($propertyType)) {
            throw new MappingFailed('The property type `'.$propertyType.'` does not implement the `'.DateTimeInterface::class.'`.');
        }

        $class = ltrim($propertyType, '?');
        if (BuiltInType::Mixed->value === $class || DateTimeInterface::class === $class) {
            $class = DateTimeImmutable::class;
        }

        $this->class = $class;
        $this->isNullable = str_starts_with($propertyType, '?');
        try {
            $this->timezone = is_string($timezone) ? new DateTimeZone($timezone) : $timezone;
            $this->default = (null !== $default) ? $this->cast($default) : $default;
        } catch (Throwable $exception) {
            throw new MappingFailed('The configuration option for `'.self::class.'` are invalid.', 0, $exception);
        }
    }

    /**
     * @throws TypeCastingFailed
     */
    public function toVariable(?string $value): DateTimeImmutable|DateTime|null
    {
        return match (true) {
            null !== $value && '' !== $value => $this->cast($value),
            $this->isNullable => $this->default,
            default => throw new TypeCastingFailed('Unable to convert the `null` value.'),
        };
    }

    /**
     * @throws TypeCastingFailed
     */
    private function cast(string $value): DateTimeImmutable|DateTime
    {
        try {
            $date = null !== $this->format ?
                ($this->class)::createFromFormat($this->format, $value, $this->timezone) :
                new ($this->class)($value, $this->timezone);
            if (false === $date) {
                throw new TypeCastingFailed('Unable to cast the given data `'.$value.'` to a PHP DateTime related object.');
            }
        } catch (Throwable $exception) {
            if ($exception instanceof TypeCastingFailed) {
                throw $exception;
            }

            throw new TypeCastingFailed('Unable to cast the given data `'.$value.'` to a PHP DateTime related object.', 0, $exception);
        }

        return $date;
    }
}
