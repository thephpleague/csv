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

use ReflectionException;
use ReflectionMethod;
use ReflectionProperty;

use function array_key_exists;
use function is_string;

/**
 * @internal
 */
final class PropertySetter
{
    public function __construct(
        public readonly ReflectionMethod|ReflectionProperty $accessor,
        public readonly int $offset,
        public readonly TypeCasting $cast,
        public readonly bool $convertEmptyStringToNull,
        public readonly bool $trimFieldValueBeforeCasting,
    ) {
    }

    /**
     * @throws ReflectionException
     * @throws TypeCastingFailed
     */
    public function __invoke(object $object, array $recordValues): void
    {
        $typeCastedValue = $this->cast->toVariable($this->getRecordValue($recordValues));

        match (true) {
            $this->accessor instanceof ReflectionMethod => $this->accessor->invoke($object, $typeCastedValue),
            $this->accessor instanceof ReflectionProperty => $this->accessor->setValue($object, $typeCastedValue),
        };
    }

    /**
     * @throws TypeCastingFailed
     */
    private function getRecordValue(array $record): mixed
    {
        if (!array_key_exists($this->offset, $record)) {
            throw TypeCastingFailed::dueToUndefinedValue($this->offset);
        }

        $value = $record[$this->offset];
        if (is_string($value) && $this->trimFieldValueBeforeCasting) {
            $value = trim($value);
        }

        if ('' === $value && $this->convertEmptyStringToNull) {
            return null;
        }

        return $value;
    }
}
