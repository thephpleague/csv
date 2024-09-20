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

use Attribute;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ValueError;

#[Attribute(Attribute::TARGET_CLASS)]
final class MapRecord
{
    public function __construct(
        /** @var array<string> $afterMapping */
        public readonly array $afterMapping = [],
        public readonly ?bool $convertEmptyStringToNull = null,
    ) {
        foreach ($this->afterMapping as $method) {
            if (!is_string($method)) {
                throw new ValueError('The method names must be strings.');
            }
        }
    }

    /**
     * @return array<ReflectionMethod>
     */
    public function afterMappingMethods(ReflectionClass $class): array
    {
        $methods = [];
        foreach ($this->afterMapping as $method) {
            try {
                $accessor = $class->getMethod($method);
            } catch (ReflectionException $exception) {
                throw new MappingFailed('The method `'.$method.'` is not defined on the `'.$class->getName().'` class.', 0, $exception);
            }

            if (0 !== $accessor->getNumberOfRequiredParameters()) {
                throw new MappingFailed('The method `'.$class->getName().'::'.$accessor->getName().'` has too many required parameters.');
            }

            $methods[] = $accessor;
        }

        return $methods;
    }
}
