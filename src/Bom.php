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

use SplFileObject;
use Stringable;
use Throwable;
use ValueError;

enum Bom: string
{
    case Utf32Le = "\xFF\xFE\x00\x00";
    case Utf32Be = "\x00\x00\xFE\xFF";
    case Utf16Be = "\xFE\xFF";
    case Utf16Le = "\xFF\xFE";
    case Utf8 = "\xEF\xBB\xBF";

    public static function fromSequence(mixed $sequence): self
    {
        return self::tryFromSequence($sequence)
            ?? throw new ValueError('No BOM sequence could be found on the given sequence.');
    }

    public static function tryFromSequence(mixed $sequence): ?self
    {
        $str = match (true) {
            $sequence instanceof SplFileObject,
            $sequence instanceof Stream => self::getContents($sequence, 4, 0),
            is_resource($sequence) => stream_get_contents($sequence, 4, 0),
            $sequence instanceof AbstractCsv => $sequence->getInputBOM(),
            $sequence instanceof Stringable,
            is_string($sequence) => substr((string) $sequence, 0, 4),
            default => $sequence,
        };

        if (!is_string($str) || '' === rtrim($str)) {
            return null;
        }

        foreach (self::cases() as $bom) {
            if (str_starts_with($str, $bom->value)) {
                return $bom;
            }
        }

        return null;
    }

    private static function getContents(Stream|SplFileObject $sequence, int $length, int $offset): ?string
    {
        $position = $sequence->ftell();
        if (false === $position) {
            return null;
        }

        try {
            $sequence->fseek($offset);
            $str = $sequence->fread($length);
            $sequence->fseek($position);
            if (false === $str) {
                return null;
            }

            return $str;
        } catch (Throwable) {
            return null;
        }
    }

    public static function fromEncoding(string $name): self
    {
        return self::tryFromEncoding($name)
            ?? throw new ValueError('Unknown or unsupported BOM name `'.$name.'`.');
    }

    /**
     * @see https://unicode.org/faq/utf_bom.html#gen7
     */
    public static function tryFromEncoding(string $name): ?self
    {
        return match (strtoupper(str_replace(['_', '-'], '', $name))) {
            'UTF8' => self::Utf8,
            'UTF16',
            'UTF16BE' => self::Utf16Be,
            'UTF16LE' => self::Utf16Le,
            'UTF32',
            'UTF32BE' => self::Utf32Be,
            'UTF32LE' => self::Utf32Le,
            default => null,
        };
    }

    public function length(): int
    {
        return strlen($this->value);
    }

    public function encoding(): string
    {
        return match ($this) {
            self::Utf16Le => 'UTF-16LE',
            self::Utf16Be => 'UTF-16BE',
            self::Utf32Le => 'UTF-32LE',
            self::Utf32Be => 'UTF-32BE',
            self::Utf8 => 'UTF-8',
        };
    }

    public function isUtf8(): bool
    {
        return match ($this) {
            self::Utf8 => true,
            default => false,
        };
    }

    public function isUtf16(): bool
    {
        return match ($this) {
            self::Utf16Le,
            self::Utf16Be => true,
            default => false,
        };
    }

    public function isUtf32(): bool
    {
        return match ($this) {
            self::Utf32Le,
            self::Utf32Be => true,
            default => false,
        };
    }
}
