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

    public static function dueToInvalidHeaderContent(): self
    {
        return new self('The header record must be an empty or a flat array with unique string values.');
    }
}
