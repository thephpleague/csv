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

    public static function supports(string $type): bool
    {
        $formattedType = ltrim($type, '?');

        return match (true) {
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
        private readonly ?string $format = null,
        DateTimeZone|string|null $timezone = null,
    ) {
        $this->timezone = match (true) {
            is_string($timezone) => new DateTimeZone($timezone),
            default => $timezone,
        };
    }

    /**
     * @throws TypeCastingFailed
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
        if (!self::supports($type)) {
            throw new TypeCastingFailed('The property type `'.$dateClass.'` does not implement the `'.DateTimeInterface::class.'`.');
        }

        if (DateTimeInterface::class === $dateClass) {
            $dateClass = DateTimeImmutable::class;
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
