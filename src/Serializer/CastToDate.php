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
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use Throwable;

use function is_string;

/**
 * @implements TypeCasting<DateTimeImmutable|DateTime|null>
 */
final class CastToDate implements TypeCasting
{
    private readonly ?DateTimeZone $timezone;
    private readonly string $class;
    private readonly bool $isNullable;
    private readonly DateTimeImmutable|DateTime|null $default;

    /**
     * @throws MappingFailed
     */
    public function __construct(
        ReflectionProperty|ReflectionParameter $reflectionProperty,
        ?string $default = null,
        private readonly ?string $format = null,
        DateTimeZone|string|null $timezone = null,
    ) {
        [$type, $reflection, $this->isNullable] = $this->init($reflectionProperty);
        $class = $reflection->getName();
        if (Type::Mixed->equals($type) || DateTimeInterface::class === $class) {
            $class = DateTimeImmutable::class;
        }

        $this->class = $class;
        try {
            $this->timezone = is_string($timezone) ? new DateTimeZone($timezone) : $timezone;
            $this->default = (null !== $default) ? $this->cast($default) : $default;
        } catch (Throwable $exception) {
            throw new MappingFailed(message: 'The configuration option for `'.self::class.'` are invalid.', previous: $exception);
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

            throw new TypeCastingFailed(message: 'Unable to cast the given data `'.$value.'` to a PHP DateTime related object.', previous: $exception);
        }

        return $date;
    }

    /**
     * @throws MappingFailed
     *
     * @return array{0:Type, 1:ReflectionNamedType, 2:bool}
     */
    private function init(ReflectionProperty|ReflectionParameter $reflectionProperty): array
    {
        $type = null;
        $isNullable = false;
        foreach (Type::list($reflectionProperty) as $found) {
            if (!$isNullable && $found[1]->allowsNull()) {
                $isNullable = true;
            }

            if (null === $type && $found[0]->isOneOf(Type::Mixed, Type::Date)) {
                $type = $found;
            }
        }

        if (null === $type) {
            throw new MappingFailed('`'.$reflectionProperty->getName().'` type is not supported; a class implementing the `'.DateTimeInterface::class.'` interface or `mixed` is required.');
        }

        return [...$type, $isNullable];
    }
}
