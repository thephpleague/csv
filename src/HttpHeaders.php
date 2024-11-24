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
use function rawurlencode;
use function str_contains;
use function str_replace;

use const FILTER_FLAG_STRIP_HIGH;
use const FILTER_FLAG_STRIP_LOW;

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
     * @throws InvalidArgumentException
     */
    public static function forFileDownload(string $filename, string $contentType): void
    {
        if (str_contains($filename, '/') || str_contains($filename, '\\')) {
            throw new InvalidArgumentException('The filename `'.$filename.'` cannot contain the "/" or "\" characters.');
        }

        /** @var string $filteredName */
        $filteredName = filter_var($filename, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH);
        $fallbackName = str_replace('%', '', $filteredName);
        $disposition = 'attachment;filename="'.str_replace('"', '\\"', $fallbackName).'"';
        if ($filename !== $fallbackName) {
            $disposition .= ";filename*=UTF-8''".preg_replace_callback(
                '/[%"\x00-\x1F\x7F-\xFF]/',
                static fn (array $matches): string => strtolower(rawurlencode($matches[0])),
                $filename
            );
        }

        header('content-type: '.$contentType);
        header('content-transfer-encoding: binary');
        header('content-description: File Transfer');
        header('content-disposition: '.$disposition);
    }
}
