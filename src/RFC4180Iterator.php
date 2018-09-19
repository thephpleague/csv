<?php

/**
 * League.Csv (https://csv.thephpleague.com).
 *
 * @author  Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @license https://github.com/thephpleague/csv/blob/master/LICENSE (MIT License)
 * @version 9.1.5
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
 * @package League.csv
 * @since   9.2.0
 * @author  Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @internal used internally to produce RFC4180 compliant records
 */
final class RFC4180Iterator implements IteratorAggregate
{
    /**
     * @internal
     */
    const FIELD_BREAKS = [false, "\r", "\r\n", "\n", ''];

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
    private $double_enclosure;

    /**
     * @var string
     */
    private $trim_mask;

    /**
     * New instance.
     *
     * @param SplFileObject|Stream $document
     */
    public function __construct($document)
    {
        if (!$document instanceof Stream && !$document instanceof SplFileObject) {
            throw new TypeError(sprintf(
                'Expected a %s or an SplFileObject object, % given',
                Stream::class,
                is_object($document) ? get_class($document) : gettype($document)
            ));
        }

        $this->document = $document;
    }

    /**
     * @inheritdoc
     *
     * Converts the stream into a CSV record iterator
     */
    public function getIterator()
    {
        //initialisation
        list($this->delimiter, $this->enclosure, ) = $this->document->getCsvControl();
        $this->double_enclosure = $this->enclosure.$this->enclosure;
        $this->trim_mask = str_replace([$this->delimiter, $this->enclosure], '', " \t\0\x0B");
        $this->document->setFlags(0);
        $this->document->rewind();
        do {
            yield $this->extractRecord($this->document->fgets());
        } while ($this->document->valid());
    }

    /**
     * Extract a record from the Stream document.
     *
     * The return array is similar as to the returned value of fgetcsv
     * If this the an empty line the record will be an array with a single value
     * equals to null otherwise the array contains string data.
     *
     * @param string|bool $line
     */
    private function extractRecord($line): array
    {
        $record = [];
        do {
            $method = 'extractField';
            if (($line[0] ?? '') === $this->enclosure) {
                $method = 'extractFieldEnclosed';
            }
            $record[] = $this->$method($line);
        } while (false !== $line);

        return $record;
    }

    /**
     * Extract field without enclosure as per RFC4180.
     *
     * Leading and trailing whitespaces are trimmed because the field
     * is not enclosed. trailing line-breaks are also removed.
     *
     * @param bool|string $line
     *
     * @return null|string
     */
    private function extractField(&$line)
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
     * - Leading and trailing whitespaces are preserved because the field
     * is enclosed.
     * - The field content can spread on multiple document lines.
     * - Double enclosure character muse be replaced by single enclosure character.
     * - Trailing line break are remove if they are not part of the field content.
     * - Invalid field do not throw as per fgetcsv behavior.
     *
     * @param bool|string $line
     *
     * @return null|string
     */
    private function extractFieldEnclosed(&$line)
    {
        //remove the starting enclosure character if present
        if ($line[0] ?? '' === $this->enclosure) {
            $line = substr($line, 1);
        }

        $content = '';
        do {
            list($buffer, $line) = explode($this->enclosure, $line, 2) + [1 => false];
            $content .= $buffer;
        } while (false === $line && $this->document->valid() && false !== ($line = $this->document->fgets()));

        $content = str_replace($this->double_enclosure, $this->enclosure, $content);
        if (in_array($line, self::FIELD_BREAKS, true)) {
            $line = false;

            return rtrim($content, "\r\n");
        }

        $char = $line[0] ?? '';
        if ($char === $this->delimiter) {
            $line = substr($line, 1);

            return $content;
        }

        //handles enclosure as per RFC4180 or malformed CSV like fgetcsv
        return $content.$char.$this->extractFieldEnclosed($line);
    }
}