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

namespace League\Csv;

/**
 * DEPRECATION WARNING! This namespace function will be removed in the next major point release.
 *
 * @deprecated since version 9.7.0
 * @see Bom::tryFromSequence()
 * @codeCoverageIgnore
 *
 * Returns the BOM sequence found at the start of the string.
 *
 * If no valid BOM sequence is found an empty string is returned
 */
function bom_match(string $str): string
{
    return Bom::tryFromSequence($str)?->value ?? '';
}

/**
 * DEPRECATION WARNING! This namespace function will be removed in the next major point release.
 *
 * @deprecated since version 9.7.0
 * @see Info::getDelimiterStats()
 * @codeCoverageIgnore
 *
 * Detect Delimiters usage in a {@link Reader} object.
 *
 * Returns a associative array where each key represents
 * a submitted delimiter and each value the number of CSV fields found
 * when processing at most $limit CSV records with the given delimiter
 *
 * @param array<string> $delimiters
 * @param int<-1, max> $limit
 *
 * @return array<string,int>
 */
function delimiter_detect(Reader $csv, array $delimiters, int $limit = 1): array
{
    return Info::getDelimiterStats($csv, $delimiters, $limit);
}
