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
use function get_class;
use function gettype;
use function is_object;
use function sprintf;
use function str_split;
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
     * @var string|null
     */
    private $buffer;

    /**
     * @var string
     */
    private $previous_char;

    /**
     * @var bool
     */
    private $enclosed_field;

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
        $this->init();
        list($this->delimiter, $this->enclosure, ) = $this->document->getCsvControl();
        $this->trim_mask = str_replace([$this->delimiter, $this->enclosure], '', " \t\0\x0B");
        $this->document->setFlags(0);
        $this->document->rewind();

        $record = [];
        do {
            $line = (string) $this->document->fgets();
            foreach (str_split($line) as $char) {
                if (!in_array($char, [$this->delimiter, "\n", "\r"], true)) {
                    $this->processEnclosure($char);
                    continue;
                }

                $field = $this->processBreaks($char);
                if (null !== $this->buffer) {
                    continue;
                }

                $record[] = $field;
                if ($char === $this->delimiter) {
                    continue;
                }

                yield $record;

                $record = [];
            }
        } while ($this->document->valid());

        $record[] = $this->clean();

        yield $record;
    }

    /**
     * Flushes and returns the last field content.
     *
     * @return string|null
     */
    private function clean()
    {
        //yield the remaining buffer
        if ($this->enclosed_field && $this->enclosure === $this->previous_char) {
            //strip the enclosure character present at the
            //end of the buffer; this is the end of en enclosed field
            $this->buffer = substr($this->buffer, 0, -1);
        }

        return $this->flush();
    }

    /**
     * Flushes and returns the field content.
     *
     * If the field is not enclose we trim white spaces cf RFC4180
     *
     * @return string|null
     */
    private function flush()
    {
        if (null !== $this->buffer && !$this->enclosed_field) {
            $this->buffer = trim($this->buffer, $this->trim_mask);
        }

        $field = $this->buffer;
        $this->init();
        
        return $field;
    }

    /**
     * Initialize the internal properties.
     */
    private function init()
    {
        $this->buffer = null;
        $this->previous_char = '';
        $this->enclosed_field = false;
    }

    /**
     * Handles enclosure presence according to RFC4180.
     *
     * - detect enclosed field
     * - convert the double enclosure to one enclosure
     */
    private function processEnclosure(string $char)
    {
        if ($char !== $this->enclosure) {
            $this->previous_char = $char;
            $this->buffer .= $char;
            return;
        }

        if (!$this->enclosed_field) {
            if (null === $this->buffer) {
                $this->enclosed_field = true;
                return;
            }
            //invalid CSV content
            $this->previous_char = $char;
            $this->buffer .= $char;
            return;
        }

        //double enclosure
        if ($this->previous_char === $char) {
            //safe check to only strip double enclosure characters
            $this->previous_char = '';
            return;
        }

        $this->previous_char = $char;
        $this->buffer .= $char;
    }

    /**
     * Handles delimiter and line breaks according to RFC4180.
     *
     * @return null|string
     */
    private function processBreaks(string $char)
    {
        if ($char === $this->delimiter) {
            $this->buffer = (string) $this->buffer;
        }

        if (!$this->enclosed_field) {
            return $this->flush();
        }

        //the delimiter or the line break is enclosed
        if ($this->previous_char !== $this->enclosure) {
            $this->previous_char = $char;
            $this->buffer .= $char;
            return null;
        }

        //strip the enclosure character present at the
        //end of the buffer; this is the end of a field
        $this->buffer = substr($this->buffer, 0, -1);

        return $this->flush();
    }
}
