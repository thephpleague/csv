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

enum FieldType: string
{
    case Boolean = 'boolean';
    case Custom = 'custom';
    case Date = 'date';
    case Enum = 'enum';
    case Json = 'json';
    case Numeric = 'numeric';
    case String  = 'string';
    case StructuredString = 'structured_string';
}
