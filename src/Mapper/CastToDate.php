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

namespace League\Csv\Mapper;

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
        DateTimeZone|string|null $timezone = null
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

        try {
            $date = match (ltrim($type, '?')) {
                DateTimeImmutable::class,
                DateTimeInterface::class => null !== $this->format ? DateTimeImmutable::createFromFormat($this->format, $value, $this->timezone) : new DateTimeImmutable($value, $this->timezone),
                DateTime::class => null !== $this->format ? DateTime::createFromFormat($this->format, $value, $this->timezone) : new DateTime($value, $this->timezone),
                default => throw new TypeCastingFailed('Unable to cast the given data to a PHP DateTime related object.'),
            };

            if (false === $date) {
                throw new TypeCastingFailed('Unable to cast the given data to a PHP DateTime related object.');
            }
        } catch (Throwable $exception) {
            if (! $exception instanceof TypeCastingFailed) {
                $exception = new TypeCastingFailed('Unable to cast the given data to a PHP DateTime related object.', 0, $exception);
            }

            throw $exception;
        }

        return $date;
    }
}
