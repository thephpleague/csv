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

use InvalidArgumentException;

use function filter_var;
use function header;
use function preg_replace_callback;
use function rawurlencode;
use function str_contains;
use function str_replace;
use function strtolower;

use const FILTER_FLAG_STRIP_HIGH;
use const FILTER_FLAG_STRIP_LOW;
use const FILTER_UNSAFE_RAW;

/**
 * Send the CSV headers.
 *
 * Adapted from Symfony\Component\HttpFoundation\ResponseHeaderBag::makeDisposition
 *
 * @see https://tools.ietf.org/html/rfc6266#section-4.3
 *
 * @internal
 */
final class HttpHeaders
{
    /**
     * @param non-empty-string $contentType
     *
     * @throws InvalidArgumentException
     */
    public static function forFileDownload(string $filename, string $contentType): void
    {
        ('' !== $contentType && !str_contains($contentType, "\r") && !str_contains($contentType, "\n")) || throw new InvalidArgumentException('The content type must be a non-empty value without CR or LF characters.');
        if ('' === $filename) {
            return;
        }
        (!str_contains($filename, '/') && !str_contains($filename, '\\')) || throw new InvalidArgumentException('The filename `'.$filename.'` cannot contain the "/" or "\" characters.');

        /** @var string $filteredName */
        $filteredName = filter_var($filename, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH);
        $fallbackName = str_replace('%', '', $filteredName);
        $disposition = 'attachment; filename="'.str_replace(['\\', '"'], ['\\\\', '\\"'], $fallbackName).'"';
        if ($filename !== $fallbackName) {
            $disposition .= "; filename*=UTF-8''".preg_replace_callback(
                pattern: '/[^A-Za-z0-9!#$&+\-.^_`|~]/',
                callback: static fn (array $matches): string => strtolower(rawurlencode($matches[0])),
                subject: $filename
            );
        }

        header('content-type: '.$contentType);
        header('content-transfer-encoding: binary');
        header('content-description: File Transfer');
        header('content-disposition: '.$disposition);
    }
}
