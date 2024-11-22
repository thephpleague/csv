<?php

/**
 * League.Csv (https://csv.thephpleague.com)
 *
 * (c) Ignace Nyamagana Butera <nyamsprod@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace League\Csv;

use Deprecated;

/**
 * Defines constants for common BOM sequences.
 *
 * @deprecated since version 9.16.0
 * @see Bom
 */
#[Deprecated(message:'Use Bom Enum instead', since:'league/csv:9.16.0')]
interface ByteSequence
{
    public const BOM_UTF8 = "\xEF\xBB\xBF";
    public const BOM_UTF16_BE = "\xFE\xFF";
    public const BOM_UTF16_LE = "\xFF\xFE";
    public const BOM_UTF32_BE = "\x00\x00\xFE\xFF";
    public const BOM_UTF32_LE = "\xFF\xFE\x00\x00";
}
