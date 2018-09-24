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
use function ltrim;
use function rtrim;
use function sprintf;
use function str_replace;
use function strlen;
use function substr;

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
     * @var string|bool
     */
    private $line = false;

    /**
     * New instance.
     *
     * @param SplFileObject|Stream $document
     */
    public function __construct($document, string $delimiter = ',', string $enclosure = '"')
    {
        $this->document = $this->filterDocument($document);
        $this->delimiter = $this->filterControl($delimiter, 'delimiter');
        $this->enclosure = $this->filterControl($enclosure, 'enclosure');
        $this->trim_mask = str_replace([$this->delimiter, $this->enclosure], '', " \t\0\x0B");
    }

    /**
     * Filter the submitted document.
     *
     * @param SplFileObject|Stream $document
     *
     * @return SplFileObject|Stream
     */
    private function filterDocument($document)
    {
        if ($document instanceof Stream || $document instanceof SplFileObject) {
            return $document;
        }

        throw new TypeError(sprintf(
            'Expected a %s or an SplFileObject object, %s given',
            Stream::class,
            is_object($document) ? get_class($document) : gettype($document)
        ));
    }

    /**
     * Filter a control character.
     */
    private function filterControl(string $value, string $name): string
    {
        if (1 === strlen($value)) {
            return $value;
        }

        throw new Exception(sprintf('Expected %s to be a single character %s given', $name, $value));
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
        while ($this->document->valid()) {
            $record = [];
            $this->line = $this->document->fgets();
            do {
                $method = 'extractFieldContent';
                $buffer = ltrim($this->line, $this->trim_mask);
                if (($buffer[0] ?? '') === $this->enclosure) {
                    $method = 'extractEnclosedFieldContent';
                    $this->line = $buffer;
                }

                $record[] = $this->$method();
            } while (false !== $this->line);

            yield $record;
        }
    }

    /**
     * Extract field without enclosure as per RFC4180.
     *
     * - Leading and trailing whitespaces must be removed.
     * - trailing line-breaks must be removed.
     *
     * @return null|string
     */
    private function extractFieldContent()
    {
        if (in_array($this->line, self::FIELD_BREAKS, true)) {
            $this->line = false;

            return null;
        }

        list($content, $this->line) = explode($this->delimiter, $this->line, 2) + [1 => false];
        if (false === $this->line) {
            return rtrim($content, "\r\n");
        }

        return $content;
    }

    /**
     * Extract field with enclosure as per RFC4180.
     *
     * - Field content can spread on multiple document lines.
     * - Content inside enclosure must be preserved.
     * - Double enclosure sequence must be replaced by single enclosure character.
     * - Trailing line break must be removed if they are not part of the field content.
     * - Invalid field do not throw as per fgetcsv behavior.
     */
    private function extractEnclosedFieldContent(): string
    {
        if (($this->line[0] ?? '') === $this->enclosure) {
            $this->line = substr($this->line, 1);
        }

        $content = '';
        while (false !== $this->line) {
            list($buffer, $remainder) = explode($this->enclosure, $this->line, 2) + [1 => false];
            $content .= $buffer;
            if (false !== $remainder) {
                $this->line = $remainder;
                break;
            }
            $this->line = $this->document->fgets();
        }

        if (in_array($this->line, self::FIELD_BREAKS, true)) {
            $this->line = false;

            return rtrim($content, "\r\n");
        }

        $char = $this->line[0] ?? '';
        if ($this->delimiter === $char) {
            $this->line = substr($this->line, 1);

            return $content;
        }

        if ($this->enclosure === $char) {
            return $content.$this->enclosure.$this->extractEnclosedFieldContent();
        }


        return $content.$this->extractFieldContent();
    }
}
