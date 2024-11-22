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

/**
 * @deprecated since version 9.17.0
 *
 * @see MapRecord
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class AfterMapping
{
    public readonly MapRecord $mapRecord;
    public readonly array $methods;

    public function __construct(string ...$methods)
    {
        $this->mapRecord = new MapRecord($methods);
        $this->methods = $this->mapRecord->afterMapping;
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
