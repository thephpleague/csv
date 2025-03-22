<?php

/**
 * League.Csv (https://csv.thephpleague.com)
 *
 * (c) Ignace Nyamagana Butera <nyamsprod@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace League\Csv\Serializer;

use ReflectionParameter;
use ReflectionProperty;

enum TypeCastingTargetType
{
    case PropertyName;
    case MethodFirstArgument;

    public static function fromAccessor(ReflectionParameter|ReflectionProperty $accessor): self
    {
        if ($accessor instanceof ReflectionProperty) {
            return self::PropertyName;
        }

        return self::MethodFirstArgument;
    }
}
