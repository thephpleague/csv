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

use BackedEnum;
use Closure;
use ReflectionEnum;
use Stringable;
use UnitEnum;
use ValueError;

use function array_map;
use function filter_var;
use function is_bool;
use function is_float;
use function is_int;
use function is_scalar;
use function is_string;
use function trim;

use const FILTER_VALIDATE_INT;

/**
 * @template T of UnitEnum|BackedEnum
 */
final class EnumField extends FieldEvaluator implements Field
{
    /** @var list<T> */
    private readonly array $cases;
    /** @var class-string<T> */
    public readonly string $enumClass;
    private Closure $find;

    /**
     * @param class-string<T> $enumClass
     */
    public function __construct(
        string $enumClass,
        float $confidenceThreshold = 0.8
    ) {
        enum_exists($enumClass) || throw new ValueError($enumClass.' does not exist or could not be found.');
        $this->cases = $enumClass::cases();
        $this->enumClass = $enumClass;

        parent::__construct($confidenceThreshold);

        $this->find = (new ReflectionEnum($enumClass))->isBacked() /* @phpstan-ignore-line */
            ? self::findBackedEnum(...)
            : self::findEnum(...);
    }

    /**
     * @return ?T
     */
    private function findBackedEnum(mixed $value): ?UnitEnum
    {
        if ($value instanceof $this->enumClass) {
            return $value;
        }

        if ($value instanceof UnitEnum || is_float($value) || is_bool($value) || null === $value) {
            return null;
        }

        if ($value instanceof Stringable) {
            $value = (string) $value;
        }

        if (!is_string($value) && !is_int($value)) {
            return null;
        }

        $intValue = is_int($value) ? $value : filter_var($value, FILTER_VALIDATE_INT);
        $value = (string) $value;
        foreach ($this->cases as $case) {
            /* @phpstan-ignore-next-line  */
            if ($intValue === $case->value || $value === $case->value) {
                return $case;
            }
        }

        return null;
    }

    /**
     * @return ?T
     */
    private function findEnum(mixed $value): ?UnitEnum
    {
        if ($value instanceof $this->enumClass) {
            return $value;
        }

        if ($value instanceof UnitEnum || (is_scalar($value) && !is_string($value)) || null === $value) {
            return null;
        }

        if ($value instanceof Stringable) {
            $value = (string) $value;
        }

        foreach ($this->cases as $case) {
            if ($case->name === $value) {
                return $case;
            }
        }

        return null;
    }

    public function type(): FieldType
    {
        return FieldType::Enum;
    }

    public function name(): string
    {
        return FieldType::Enum->value.'('.$this->enumClass.')';
    }

    public function parse(mixed $value): ?UnitEnum
    {
        if ($value instanceof $this->enumClass) {
            return $value;
        }

        if ($value instanceof UnitEnum) {
            return null;
        }

        if ($value instanceof Stringable) {
            $value = (string) $value;
        }

        if (!is_string($value) && !is_int($value)) {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);
            if ('' === $value) {
                return null;
            }
        }

        return ($this->find)($value);
    }

    public function metadata(): FieldMetadata
    {
        return new FieldMetadata([
            'class' => $this->enumClass,
            'cases' => array_map(
                fn (UnitEnum $case): array => [
                    'name' => $case->name,
                    'value' => $case instanceof BackedEnum ? $case->value : $case->name,
                ],
                $this->cases
            ),
        ]);
    }
}
