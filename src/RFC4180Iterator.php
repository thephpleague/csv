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
     * The CSV document.
     *
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
    private $previous_char = '';

    /**
     * @var bool
     */
    private $enclosed_field = false;

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
        $this->flush();

        $methodList = [
            $this->enclosure => 'processEnclosure',
            $this->delimiter => 'processBreaks',
            "\n" => 'processBreaks',
            "\r" => 'processBreaks',
        ];

        $record = [];
        while ($this->document->valid()) {
            //let's walk through the stream char by char
            foreach (str_split((string) $this->document->fgets()) as $char) {
                $method = $methodList[$char] ?? 'addCharacter';
                if ('processBreaks' !== $method) {
                    $this->$method($char);
                    continue;
                }

                $field = $this->$method($char);
                if (null !== $this->buffer) {
                    continue;
                }

                $record[] = $field;
                if ($char !== $this->delimiter) {
                    yield $record;

                    $record = [];
                }
            }
        }

        $record[] = $this->clean();

        yield $record;
    }

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
     * Format and return the field content.
     *
     * @return string|null
     */
    private function flush()
    {
        //if the field is not enclose we trim white spaces
        if (null !== $this->buffer && !$this->enclosed_field) {
            $this->buffer = trim($this->buffer, $this->trim_mask);
        }

        //adding field content to the record
        $field = $this->buffer;

        //reset parameters
        $this->buffer = null;
        $this->previous_char = '';
        $this->enclosed_field = false;
        
        return $field;
    }

    /**
     * Append a character to the buffer.
     *
     */
    private function addCharacter(string $char)
    {
        $this->previous_char = $char;
        $this->buffer .= $char;
    }

    /**
     * Handle enclosure presence.
     */
    private function processEnclosure(string $char)
    {
        if (!$this->enclosed_field) {
            //the enclosure is at the start of the record
            //so we have an enclosed field
            if (null === $this->buffer) {
                $this->enclosed_field = true;
                return;
            }
            //invalid CSV content let's deal with it like fgetcsv
            //we add the character to the buffer and we move on
            return $this->addCharacter($char);
        }

        //double enclosure let's skip the character and move on
        if ($this->previous_char === $char) {
            //we reset the previous character to the empty string
            //to only strip double enclosure characters
            $this->previous_char = '';
            return;
        }

        return $this->addCharacter($char);
    }

    /**
     * Handle delimiter and line breaks.
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

        //the line break is enclosed let's add it to the buffer and move on
        if ($this->previous_char !== $this->enclosure) {
            return $this->addCharacter($char);
        }

        //strip the enclosure character present at the
        //end of the buffer; this is the end of a record
        $this->buffer = substr($this->buffer, 0, -1);

        return $this->flush();
    }
}
