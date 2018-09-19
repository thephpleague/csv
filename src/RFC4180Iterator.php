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
     * Extract field without enclosure.
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

        //explode the line on the next delimiter character if any
        list($content, $line) = explode($this->delimiter, $line, 2) + [1 => false];

        //remove line breaks characters as per RFC4180
        if (false === $line) {
            $content = rtrim($content, "\r\n");
        }

        //remove whitespaces as per RFC4180
        return trim($content, $this->trim_mask);
    }

    /**
     * Extract field with enclosure.
     *
     * @param bool|string $line
     *
     * @return null|string
     */
    private function extractFieldEnclosed(&$line)
    {
        //remove the starting enclosure char to ease explode usage
        if ($line[0] ?? '' === $this->enclosure) {
            $line = substr($line, 1);
        }

        $content = '';
        //cover multiline field
        do {
            //explode the line on the next enclosure character if any
            list($buffer, $line) = explode($this->enclosure, $line, 2) + [1 => false];
            $content .= $buffer;
        } while (false === $line && $this->document->valid() && false !== ($line = $this->document->fgets()));

        //decode the field content as per RFC4180
        $content = str_replace($this->double_enclosure, $this->enclosure, $content);

        //remove line breaks characters as per RFC4180
        if (in_array($line, self::FIELD_BREAKS, true)) {
            $line = false;

            return rtrim($content, "\r\n");
        }

        //the field data is extracted since we have a delimiter
        if (($line[0] ?? '') === $this->delimiter) {
            $line = substr($line, 1);

            return $content;
        }

        //handles enclosure as per RFC4180 or malformed CSV like fgetcsv
        return $content.($line[0] ?? '').$this->extractFieldEnclosed($line);
    }
}
