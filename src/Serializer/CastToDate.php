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
use RuntimeException;
use Throwable;

/**
 * @implements TypeCasting<DateTimeImmutable|DateTime|null>
 */
final class CastToDate implements TypeCasting
{
    private readonly ?DateTimeZone $timezone;

    /**
     * @throws Exception
     */
    public function __construct(
        private readonly ?string $format = null,
        DateTimeZone|string|null $timezone = null,
    ) {
        $this->timezone = match (true) {
            is_string($timezone) => new DateTimeZone($timezone),
            default => $timezone,
        };
    }

    /**
     * @throws RuntimeException
     */
    public function toVariable(?string $value, string $type): DateTimeImmutable|DateTime|null
    {
        if (in_array($value, ['', null], true)) {
            return match (true) {
                str_starts_with($type, '?') => null,
                default => throw new TypeCastingFailed('Unable to convert the `null` value.'),
            };
        }

        $dateClass = ltrim($type, '?');
        if (DateTimeInterface::class === $dateClass) {
            $dateClass = DateTimeImmutable::class;
        }

        $implementedClasses = class_implements($dateClass);
        if (false === $implementedClasses || !in_array(DateTimeInterface::class, $implementedClasses, true)) {
            throw new TypeCastingFailed('The property type `'.$dateClass.'` does not implement the `'.DateTimeInterface::class.'`.');
        }

        try {
            $date = null !== $this->format ?
                $dateClass::createFromFormat($this->format, $value, $this->timezone) :
                new $dateClass($value, $this->timezone);
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
