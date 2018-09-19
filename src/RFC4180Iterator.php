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
        $this->trim_mask = str_replace([$this->delimiter, $this->enclosure], '', " \t\0\x0B");
        $this->document->setFlags(0);
        $this->document->rewind();
        do {
            $line = $this->document->fgets();
            yield $this->extractRecord($line);
        } while ($this->document->valid());
    }

    /**
     * Extract a record from the Stream document.
     *
     * @param string|bool $line
     */
    private function extractRecord($line): array
    {
        $record = [];
        do {
            $method = ($line[0] ?? '') === $this->enclosure ? 'extractEnclosedField' : 'extractField';
            $record[] = $this->$method($line);
        } while (false !== $line);

        return $record;
    }

    /**
     * Extract field without enclosure.
     *
     * @param bool|string $line
     *
     * @return null|string
     */
    private function extractField(& $line)
    {
        //process the line if it is only a line-break or the empty string
        if ($line === false || $line === "\r" || $line === "\r\n" || $line === "\n" || $line === '') {
            $line = false;

            return null;
        }

        //explode the line on the next delimiter character
        list($content, $line) = explode($this->delimiter, $line, 2) + [1 => false];

        //if this is the end of line remove line breaks
        if (false === $line) {
            $content = rtrim($content, "\r\n");
        }

        //remove whitespaces
        return trim($content, $this->trim_mask);
    }

    /**
     * Extract field with enclosure.
     *
     * @param bool|string $line
     *
     * @return null|string
     */
    private function extractEnclosedField(& $line)
    {
        //remove the first enclosure from the line if present to easily use explode
        if ($line[0] ?? '' === $this->enclosure) {
            $line = substr($line, 1);
        }

        //covers multiline fields
        $content = '';
        do {
            //explode the line on the next enclosure character found
            list($buffer, $line) = explode($this->enclosure, $line, 2) + [1 => false];
            $content .= $buffer;
        } while (false === $line && $this->document->valid() && false !== ($line = $this->document->fgets()));

        //format the field content by removing double quoting if present
        $content = str_replace($this->enclosure.$this->enclosure, $this->enclosure, $content);

        //process the line if it is only a line-break or the empty string
        if ($line === false || $line === "\r" || $line === "\r\n" || $line === "\n" || $line === '') {
            $line = false;

            return rtrim($content, "\r\n");
        }

        //the field data is extracted since we have a delimiter
        if (($line[0] ?? '') === $this->delimiter) {
            $line = substr($line, 1);

            return $content;
        }

        //double quote content found
        if (($line[0] ?? '') === $this->enclosure) {
            $content .= '"'.$this->extractEnclosedField($line);
        }

        return $content;
    }
}
