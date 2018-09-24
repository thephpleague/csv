<?php

/**
 * League.Csv (https://csv.thephpleague.com).
 *
 * @author  Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @license https://github.com/thephpleague/csv/blob/master/LICENSE (MIT License)
 * @version 9.2.0
 * @link    https://github.com/thephpleague/csv
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace League\Csv;

use IteratorAggregate;
use SplFileObject;
use TypeError;
use function explode;
use function get_class;
use function gettype;
use function in_array;
use function is_object;
use function rtrim;
use function sprintf;
use function str_replace;
use function strlen;
use function substr;
use function trim;

/**
 * A RFC4180 Compliant Parser in Pure PHP.
 *
 * @see https://php.net/manual/en/function.fgetcsv.php
 * @see https://php.net/manual/en/function.fgets.php
 * @see https://tools.ietf.org/html/rfc4180
 * @see http://edoceo.com/utilitas/csv-file-format
 *
 * @internal used internally to produce RFC4180 compliant records
 */
final class RFC4180Parser implements IteratorAggregate
{
    /**
     * @internal
     */
    const FIELD_BREAKS = [false, '', "\r\n", "\n", "\r"];

    /**
     * @var SplFileObject|Stream
     */
    private $document;

    /**
     * @var string
     */
    private $delimiter;

    /**
     * @var string
     */
    private $enclosure;

    /**
     * @var string
     */
    private $trim_mask;

    /**
     * New instance.
     *
     * @param SplFileObject|Stream $document
     */
    public function __construct($document, string $delimiter = ',', string $enclosure = '"')
    {
        if (!$document instanceof Stream && !$document instanceof SplFileObject) {
            throw new TypeError(sprintf(
                'Expected a %s or an SplFileObject object, % given',
                Stream::class,
                is_object($document) ? get_class($document) : gettype($document)
            ));
        }

        if (1 !== strlen($delimiter)) {
            throw new Exception(sprintf('%s() expects delimiter to be a single character %s given', __METHOD__, $delimiter));
        }

        if (1 !== strlen($enclosure)) {
            throw new Exception(sprintf('%s() expects enclosure to be a single character %s given', __METHOD__, $enclosure));
        }

        $this->document = $document;
        $this->delimiter = $delimiter;
        $this->enclosure = $enclosure;
        $this->trim_mask = str_replace([$this->delimiter, $this->enclosure], '', " \t\0\x0B");
    }

    /**
     * @inheritdoc
     *
     * Converts the stream into a CSV record iterator by extracting records one by one
     *
     * The returned record array is similar to the returned value of fgetcsv
     *
     * - If the line is empty the record will be an array with a single value equals to null
     * - Otherwise the array contains strings.
     */
    public function getIterator()
    {
        $this->document->setFlags(0);
        $this->document->rewind();
        do {
            $record = [];
            $line = $this->document->fgets();
            do {
                $method = 'extractFieldContent';
                if (($line[0] ?? '') === $this->enclosure) {
                    $method = 'extractEnclosedFieldContent';
                }
                $record[] = $this->$method($line);
            } while (false !== $line);

            yield $record;
        } while ($this->document->valid());
    }

    /**
     * Extract field without enclosure as per RFC4180.
     *
     * - Leading and trailing whitespaces must be removed.
     * - trailing line-breaks must be removed.
     *
     * @param bool|string $line
     *
     * @return null|string
     */
    private function extractFieldContent(&$line)
    {
        if (in_array($line, self::FIELD_BREAKS, true)) {
            $line = false;

            return null;
        }

        list($content, $line) = explode($this->delimiter, $line, 2) + [1 => false];
        if (false === $line) {
            return trim(rtrim($content, "\r\n"), $this->trim_mask);
        }

        return trim($content, $this->trim_mask);
    }

    /**
     * Extract field with enclosure as per RFC4180.
     *
     * - Field content can spread on multiple document lines.
     * - Content inside enclosure must be preserved.
     * - Double enclosure sequence must be replaced by single enclosure character.
     * - Trailing line break must be removed if they are not part of the field content.
     * - Invalid field do not throw as per fgetcsv behavior.
     *
     * @param bool|string $line
     */
    private function extractEnclosedFieldContent(&$line): string
    {
        if (($line[0] ?? '') === $this->enclosure) {
            $line = substr($line, 1);
        }

        $content = '';
        while (false !== $line) {
            list($buffer, $line) = explode($this->enclosure, $line, 2) + [1 => false];
            $content .= $buffer;
            if (false !== $line) {
                break;
            }
            $line = $this->document->fgets();
        }

        if (in_array($line, self::FIELD_BREAKS, true)) {
            $line = false;

            return rtrim($content, "\r\n");
        }

        $char = $line[0] ?? '';
        if ($char === $this->delimiter) {
            $line = substr($line, 1);

            return $content;
        }

        if ($char === $this->enclosure) {
            return $content.$this->enclosure.$this->extractEnclosedFieldContent($line);
        }

        return $content.$this->extractFieldContent($line);
    }
}
