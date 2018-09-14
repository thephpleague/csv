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
use function substr;

/**
 * A RFC4180 Compliant Parser in Pure PHP.
 *
 * @see https://php.net/manual/en/function.fgetcsv.php
 * @see https://php.net/manual/en/function.fgetc.php
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
        $record = [];
        $buffer = '';
        $previous_char = '';
        $enclosed_field = false;
        list($delimiter, $enclosure, ) = $this->document->getCsvControl();
        $this->document->rewind();

        //let's walk through the stream a char by char
        while (false !== ($char = $this->document->fgetc())) {
            switch ($char) {
                case $enclosure:
                    if (!$enclosed_field) {
                        //the enclosure is at the start of the record
                        //this is an enclosed field
                        if ('' === $buffer) {
                            $enclosed_field = true;
                            break;
                        }
                        //invalid CSV content let's deal with it like fgetcsv
                        //we add the character to the buffer and we move on
                        $previous_char = $char;
                        $buffer .= $char;
                        break;
                    }
                    //double quoted enclosure let's skip the character and move on
                    if ($previous_char === $enclosure) {
                        $previous_char = '';
                        break;
                    }
                    $previous_char = $char;
                    $buffer .= $char;
                    break;
                case $delimiter:
                    if ($enclosed_field) {
                        //the delimiter is enclosed let's add it to the buffer and move on
                        if ($previous_char !== $enclosure) {
                            $buffer .= $char;
                            break;
                        }
                        //strip the enclosure character present at the
                        //end of the buffer; this is the end of en enclosed field
                        $buffer = substr($buffer, 0, -1);
                    }

                    //the buffer is the field content we add it to the record
                    $record[] = $buffer;

                    //reset field parameters
                    $buffer = '';
                    $previous_char = '';
                    $enclosed_field = false;
                    break;
                case "\n":
                case "\r":
                    if ($enclosed_field) {
                        //the line break is enclosed let's add it to the buffer and move on
                        if ($previous_char !== $enclosure) {
                            $previous_char = $char;
                            $buffer .= $char;
                            break;
                        }
                        //strip the enclosure character present at the
                        //end of the buffer; this is the end of a record
                        $buffer = substr($buffer, 0, -1);
                    }

                    //adding field content to the record
                    $record[] = $buffer;
                    //reset field parameters
                    $buffer = '';
                    $enclosed_field = false;
                    $previous_char = '';

                    //yield the record
                    yield $record;

                    //reset record
                    $record = [];
                    break;
                default:
                    $buffer .= $char;
                    break;
            }
        }
        $record[] = $buffer;

        yield $record;
    }
}
