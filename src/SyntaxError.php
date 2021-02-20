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

use Throwable;

/**
 * SyntaxError Exception.
 */
class SyntaxError extends Exception
{
    /**
     * @var array<string>
     */
    protected $duplicateColumnNames = [];

    /**
     * DEPRECATION WARNING! This class will be removed in the next major point release.
     *
     * @deprecated since version 9.7.0
     */
    public function __construct(string $message = '', int $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public static function dueToHeaderNotFound(int $offset): self
    {
        return new self('The header record does not exist or is empty at offset: `'.$offset.'`');
    }

    public static function dueToInvalidHeaderColumnNames(): self
    {
        return new self('The header record contains non string colum names.');
    }

    public static function dueToDuplicateHeaderColumnNames(array $header): self
    {
        $duplicates = array_keys(array_filter(array_count_values($header), function (int $value): bool {
            return $value > 1;
        }));

        $instance = new self('The header record contains duplicate column names.');
        $instance->duplicateColumnNames = $duplicates;

        return $instance;
    }

    public function duplicateColumnNames(): array
    {
        return $this->duplicateColumnNames;
    }
}
