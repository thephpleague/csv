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
use ReflectionEnum;
use ReflectionEnumUnitCase;
use Throwable;
use UnitEnum;
use ValueError;

use function array_map;
use function filter_var;
use function is_int;
use function is_string;
use function trim;

use const FILTER_VALIDATE_INT;

final class EnumField extends FieldEvaluator implements Field
{
    private readonly ?string $backedEnumType;
    /** @var list<UnitEnum> */
    private readonly array $cases;
    /** @var class-string<UnitEnum> */
    public readonly string $enumClass;
    private readonly array $byNames;

    /**
     * @param class-string<UnitEnum> $enumClass
     */
    public function __construct(
        string $enumClass,
        float $confidenceThreshold = 0.8
    ) {
        try {
            $ref = new ReflectionEnum($enumClass);
        } catch (Throwable $exception) {
            throw new ValueError('Enum "'.$enumClass.'" can not be use: '.$exception->getMessage(), previous: $exception);
        }

        parent::__construct($confidenceThreshold);

        $this->enumClass = $enumClass;
        $this->backedEnumType = !$ref->isBacked() ? null : $ref->getBackingType()->getName();
        $this->cases = array_map(fn (ReflectionEnumUnitCase $case) => $case->getValue(), $ref->getCases());

        $byNames = [];
        foreach ($this->cases as $case) {
            $byNames[$case->name] = $case;
        }
        $this->byNames = $byNames;
    }

    public function type(): FieldType
    {
        return FieldType::Enum;
    }

    public function name(): string
    {
        return FieldType::Enum->value;
    }

    public function parse(mixed $value): ?UnitEnum
    {
        if ($value instanceof UnitEnum && $value::class === $this->enumClass) {
            return $value;
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

        if (null === $this->backedEnumType) {
            return !is_string($value) ? null : ($this->byNames[$value] ?? null);
        }

        if ('int' === $this->backedEnumType && is_string($value)) {
            $value = filter_var($value, FILTER_VALIDATE_INT);
            if (false === $value) {
                return null;
            }
        }

        /** @var BackedEnum $enumClass */
        $enumClass = $this->enumClass;

        return $enumClass::tryFrom($value);
    }

    public function metadata(): FieldMetadata
    {
        return new FieldMetadata([
            'class' => $this->enumClass,
            'backedType' => $this->backedEnumType,
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
