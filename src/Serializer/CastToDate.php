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
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use Throwable;

use function class_exists;
use function is_string;

/**
 * @implements TypeCasting<DateTimeImmutable|DateTime|null>
 */
final class CastToDate implements TypeCasting
{
    private readonly ?DateTimeZone $timezone;
    /** @var class-string */
    private readonly string $class;
    private readonly bool $isNullable;
    private readonly DateTimeImmutable|DateTime|null $default;

    /**
     * @param ?class-string $dateClass
     * @throws MappingFailed
     */
    public function __construct(
        ReflectionProperty|ReflectionParameter $reflectionProperty,
        ?string $default = null,
        private readonly ?string $format = null,
        DateTimeZone|string|null $timezone = null,
        ?string $dateClass = null
    ) {
        [$type, $reflection, $this->isNullable] = $this->init($reflectionProperty);
        /** @var class-string $class */
        $class = $reflection->getName();
        $this->class = match (true) {
            DateTimeInterface::class !== $class && !Type::Mixed->equals($type) => $class,
            null === $dateClass => DateTimeImmutable::class,
            class_exists($dateClass) && (new ReflectionClass($dateClass))->implementsInterface(DateTimeInterface::class) => $dateClass,
            default => throw new MappingFailed('`'.$reflectionProperty->getName().'` type is `mixed` and the specified class via the `$dateClass` argument is invalid or could not be found.'),
        };

        try {
            $this->timezone = is_string($timezone) ? new DateTimeZone($timezone) : $timezone;
            $this->default = (null !== $default) ? $this->cast($default) : $default;
        } catch (Throwable $exception) {
            throw new MappingFailed('The `timezone` and/or `format` options used for `'.self::class.'` are invalud.', 0, $exception);
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
            default => throw TypeCastingFailed::dueToNotNullableType($this->class),
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
                throw TypeCastingFailed::dueToInvalidValue($value, $this->class);
            }
        } catch (Throwable $exception) {
            if ($exception instanceof TypeCastingFailed) {
                throw $exception;
            }

            throw TypeCastingFailed::dueToInvalidValue($value, $this->class, $exception);
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
            throw throw MappingFailed::dueToTypeCastingUnsupportedType($reflectionProperty, $this, DateTimeInterface::class, 'mixed');
        }

        return [...$type, $isNullable];
    }
}
