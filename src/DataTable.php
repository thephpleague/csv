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

use Iterator;

use function array_filter;
use function array_keys;
use function array_unique;
use function array_values;
use function is_int;

final class DataTable implements TabularData
{
    /** @var array<string>|array{} $header */
    private readonly array $header;
    /** @var Iterator<array-key, array<array-key, mixed>> $rows */
    private readonly Iterator $rows;

    /**
     * @param iterable<array-key, array<array-key, mixed>> $rows
     * @param array<string>|array{} $header
     *
     * @throws SyntaxError
     */
    public function __construct(iterable $rows, array $header)
    {
        $this->rows = MapIterator::toIterator($rows);
        $this->header = match (true) {
            $header !== array_filter($header, is_string(...)) => throw SyntaxError::dueToInvalidHeaderColumnNames(),
            $header !== array_unique($header) => throw SyntaxError::dueToDuplicateHeaderColumnNames($header),
            [] !== array_filter(array_keys($header), fn (string|int $value) => !is_int($value) || $value < 0) => throw new SyntaxError('The header mapper indexes should only contain positive integer or 0.'),
            default => array_values($header),
        };
    }

    public static function fromEmpty(): self
    {
        return new self([], []);
    }

    /**
     * @return array<string>|array{}
     */
    public function getHeader(): array
    {
        return $this->header;
    }

    /**
     * @return Iterator<array-key, array<array-key, mixed>>
     */
    public function getIterator(): Iterator
    {
        return $this->rows;
    }
}
