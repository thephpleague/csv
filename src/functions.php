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

namespace League\Csv {

    use ReflectionClass;
    use Traversable;
    use function array_fill_keys;
    use function array_filter;
    use function array_reduce;
    use function array_unique;
    use function count;
    use function is_array;
    use function iterator_to_array;
    use function strpos;
    use const COUNT_RECURSIVE;

    /**
     * Returns the BOM sequence found at the start of the string.
     *
     * If no valid BOM sequence is found an empty string is returned
     */
    function bom_match(string $str): string
    {
        static $list;

        $list = $list ?? (new ReflectionClass(ByteSequence::class))->getConstants();

        foreach ($list as $sequence) {
            if (0 === strpos($str, $sequence)) {
                return $sequence;
            }
        }

        return '';
    }

    /**
     * Detect Delimiters usage in a {@link Reader} object.
     *
     * Returns a associative array where each key represents
     * a submitted delimiter and each value the number CSV fields found
     * when processing at most $limit CSV records with the given delimiter
     *
     * @param string[] $delimiters
     *
     * @return int[]
     */
    function delimiter_detect(Reader $csv, array $delimiters, int $limit = 1): array
    {
        $found = array_unique(array_filter($delimiters, static function (string $value): bool {
            return 1 == strlen($value);
        }));
        $stmt = (new Statement())->limit($limit)->where(static function (array $record): bool {
            return count($record) > 1;
        });
        $reducer = static function (array $result, string $delimiter) use ($csv, $stmt): array {
            $result[$delimiter] = count(iterator_to_array($stmt->process($csv->setDelimiter($delimiter)), false), COUNT_RECURSIVE);

            return $result;
        };
        $delimiter = $csv->getDelimiter();
        $header_offset = $csv->getHeaderOffset();
        $csv->setHeaderOffset(null);
        $stats = array_reduce($found, $reducer, array_fill_keys($delimiters, 0));
        $csv->setHeaderOffset($header_offset)->setDelimiter($delimiter);

        return $stats;
    }

    /**
     * Tell whether the content of the variable is iterable.
     *
     * @see http://php.net/manual/en/function.is-iterable.php
     */
    function is_iterable($iterable): bool
    {
        return is_array($iterable) || $iterable instanceof Traversable;
    }

    /**
     * Tell whether the content of the variable is an int or null.
     *
     * @see https://wiki.php.net/rfc/nullable_types
     */
    function is_nullable_int($value): bool
    {
        return null === $value || is_int($value);
    }
}

namespace {

    use League\Csv;

    if (PHP_VERSION_ID < 70100 && !function_exists('\is_iterable')) {
        /**
         * @codeCoverageIgnore
         */
        function is_iterable($iterable)
        {
            return Csv\is_iterable($iterable);
        }
    }
}
