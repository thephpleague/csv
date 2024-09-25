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
use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;

/**
 * @deprecated since version 9.17.0
 *
 * @see MapRecord
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class AfterMapping
{
    /** @var array<string> $methods */
    public readonly array $methods;

    public function __construct(string ...$methods)
    {
        $this->methods = $methods;
    }

    /**
     *
     * @return array<ReflectionMethod>
     */
    public function afterMappingMethods(ReflectionClass $class): array
    {
        $methods = [];
        foreach ($this->methods as $method) {
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

    public static function from(ReflectionClass $class): ?self
    {
        $attributes = $class->getAttributes(self::class, ReflectionAttribute::IS_INSTANCEOF);
        $nbAttributes = count($attributes);
        if (0 === $nbAttributes) {
            return null;
        }

        if (1 < $nbAttributes) {
            throw new MappingFailed('Using more than one `'.self::class.'` attribute on a class property or method is not supported.');
        }

        return $attributes[0]->newInstance();
    }
}
