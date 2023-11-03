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
use Exception;
use Throwable;

/**
 * @implements TypeCasting<DateTimeImmutable|DateTime|null>
 */
final class CastToDate implements TypeCasting
{
    private readonly ?DateTimeZone $timezone;
    private readonly string $class;
    private readonly bool $isNullable;

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
     * @throws Exception
     */
    public function __construct(
        string $propertyType,
        private readonly ?string $format = null,
        DateTimeZone|string|null $timezone = null,
    ) {
        if (!self::supports($propertyType)) {
            throw new TypeCastingFailed('The property type `'.$propertyType.'` does not implement the `'.DateTimeInterface::class.'`.');
        }

        $class = ltrim($propertyType, '?');
        if (BuiltInType::Mixed->value === $class || DateTimeInterface::class === $class) {
            $class = DateTimeImmutable::class;
        }

        $this->class = $class;
        $this->isNullable = str_starts_with($propertyType, '?');
        $this->timezone = match (true) {
            is_string($timezone) => new DateTimeZone($timezone),
            default => $timezone,
        };
    }

    /**
     * @throws TypeCastingFailed
     */
    public function toVariable(?string $value): DateTimeImmutable|DateTime|null
    {
        if (null === $value || '' === $value) {
            return match (true) {
                $this->isNullable,
                $this->class === BuiltInType::Mixed->value => null,
                default => throw new TypeCastingFailed('Unable to convert the `null` value.'),
            };
        }

        try {
            $date = null !== $this->format ?
                ($this->class)::createFromFormat($this->format, $value, $this->timezone) :
                new ($this->class)($value, $this->timezone);
            if (false === $date) {
                throw new TypeCastingFailed('Unable to cast the given data `'.$value.'` to a PHP DateTime related object.');
            }
        } catch (Throwable $exception) {
            if (!$exception instanceof TypeCastingFailed) {
                $exception = new TypeCastingFailed('Unable to cast the given data `'.$value.'` to a PHP DateTime related object.', 0, $exception);
            }

            throw $exception;
        }

        return $date;
    }
}
