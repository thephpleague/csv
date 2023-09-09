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

use function array_fill_keys;
use function array_filter;
use function array_reduce;
use function array_unique;
use function count;
use function strlen;

use const COUNT_RECURSIVE;

final class Info implements ByteSequence
{
    private const BOM_SEQUENCE_LIST = [
        ByteSequence::BOM_UTF32_BE,
        ByteSequence::BOM_UTF32_LE,
        ByteSequence::BOM_UTF16_BE,
        ByteSequence::BOM_UTF16_LE,
        ByteSequence::BOM_UTF8,
    ];

    /**
     * Returns the BOM sequence found at the start of the string.
     *
     * If no valid BOM sequence is found an empty string is returned
     */
    public static function fetchBOMSequence(string $str): ?string
    {
        foreach (self::BOM_SEQUENCE_LIST as $sequence) {
            if (str_starts_with($str, $sequence)) {
                return $sequence;
            }
        }

        return null;
    }

    /**
     * Detect Delimiters usage in a {@link Reader} object.
     *
     * Returns a associative array where each key represents
     * a submitted delimiter and each value the number CSV fields found
     * when processing at most $limit CSV records with the given delimiter
     *
     * @param array<string> $delimiters
     *
     * @return array<string, int>
     */
    public static function getDelimiterStats(Reader $csv, array $delimiters, int $limit = 1): array
    {
        $stmt = Statement::create()->offset(0)->limit($limit);

        $delimiterStats = function (array $stats, string $delimiter) use ($csv, $stmt): array {
            $csv->setDelimiter($delimiter);
            $foundRecords = [];
            foreach ($stmt->process($csv)->getRecords() as $record) {
                if (1 < count($record)) {
                    $foundRecords[] = $record;
                }
            }

            $stats[$delimiter] = count($foundRecords, COUNT_RECURSIVE);

            return $stats;
        };

        $currentDelimiter = $csv->getDelimiter();
        $currentHeaderOffset = $csv->getHeaderOffset();

        $csv->setHeaderOffset(null);

        $stats = array_reduce(
            array_unique(array_filter($delimiters, fn (string $value): bool => 1 === strlen($value))),
            $delimiterStats,
            array_fill_keys($delimiters, 0)
        );

        $csv->setHeaderOffset($currentHeaderOffset);
        $csv->setDelimiter($currentDelimiter);

        return $stats;
    }
}
