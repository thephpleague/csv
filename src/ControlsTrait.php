<?php
/**
* This file is part of the League.csv library
*
* @license http://opensource.org/licenses/MIT
* @link https://github.com/thephpleague/csv/
* @version 9.0.0
* @package League.csv
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
declare(strict_types=1);

namespace League\Csv;

use InvalidArgumentException;
use LogicException;
use SplFileObject;

/**
 *  An abstract class to enable basic CSV manipulation
 *
 * @package  League.csv
 * @since    9.0.0
 * @author   Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @internal
 */
trait ControlsTrait
{
    use ValidatorTrait;

    /**
     * The CSV document
     *
     * can be a StreamIterator object, a SplFileObject object or the string path to a file
     *
     * @var StreamIterator|SplFileObject
     */
    protected $document;

    /**
     * the field delimiter (one character only)
     *
     * @var string
     */
    protected $delimiter = ',';

    /**
     * the field enclosure character (one character only)
     *
     * @var string
     */
    protected $enclosure = '"';

    /**
     * the field escape character (one character only)
     *
     * @var string
     */
    protected $escape = '\\';

    /**
     * The Input file BOM character
     * @var string
     */
    protected $input_bom;

    /**
     * The Output file BOM character
     * @var string
     */
    protected $output_bom = '';

    /**
     * collection of stream filters
     *
     * @var array
     */
    protected $stream_filters = [];

    /**
     * The stream filter mode (read or write)
     */
    protected $stream_filter_mode;

    /**
     * Returns the current field delimiter
     *
     * @return string
     */
    public function getDelimiter(): string
    {
        return $this->delimiter;
    }

    /**
     * Returns the current field enclosure
     *
     * @return string
     */
    public function getEnclosure(): string
    {
        return $this->enclosure;
    }

    /**
     * Returns the current field escape character
     *
     * @return string
     */
    public function getEscape(): string
    {
        return $this->escape;
    }

    /**
     * Returns the BOM sequence in use on Output methods
     *
     * @return string
     */
    public function getOutputBOM(): string
    {
        return $this->output_bom;
    }

    /**
     * Returns the BOM sequence of the given CSV
     *
     * @return string
     */
    public function getInputBOM(): string
    {
        if (null === $this->input_bom) {
            $bom = [
                AbstractCsv::BOM_UTF32_BE, AbstractCsv::BOM_UTF32_LE,
                AbstractCsv::BOM_UTF16_BE, AbstractCsv::BOM_UTF16_LE, AbstractCsv::BOM_UTF8,
            ];

            $this->document->setFlags(SplFileObject::READ_CSV);
            $this->document->rewind();
            $line = $this->document->fgets();
            $res = array_filter($bom, function ($sequence) use ($line) {
                return strpos($line, $sequence) === 0;
            });

            $this->input_bom = (string) array_shift($res);
        }

        return $this->input_bom;
    }

    /**
     * Tells whether the stream filter capabilities can be used
     *
     * @return bool
     */
    public function isStream(): bool
    {
        return $this->document instanceof StreamIterator;
    }

    /**
     * Tell whether the specify stream filter is attach to the current stream
     *
     * @return bool
     */
    public function hasStreamFilter(string $filtername): bool
    {
        return isset($this->stream_filters[$filtername]);
    }

    /**
     * Sets the field delimiter
     *
     * @param string $delimiter
     *
     * @throws InvalidArgumentException If $delimiter is not a single character
     *
     * @return $this
     */
    public function setDelimiter(string $delimiter): self
    {
        $this->delimiter = $this->filterControl($delimiter, 'delimiter');

        return $this;
    }

    /**
     * Sets the field enclosure
     *
     * @param string $enclosure
     *
     * @throws InvalidArgumentException If $enclosure is not a single character
     *
     * @return $this
     */
    public function setEnclosure(string $enclosure): self
    {
        $this->enclosure = $this->filterControl($enclosure, 'enclosure');

        return $this;
    }

    /**
     * Sets the field escape character
     *
     * @param string $escape
     *
     * @throws InvalidArgumentException If $escape is not a single character
     *
     * @return $this
     */
    public function setEscape(string $escape): self
    {
        $this->escape = $this->filterControl($escape, 'escape');

        return $this;
    }

    /**
     * Sets the BOM sequence to prepend the CSV on output
     *
     * @param string $str The BOM sequence
     *
     * @return static
     */
    public function setOutputBOM(string $str): self
    {
        $this->output_bom = $str;

        return $this;
    }

    /**
     * append a stream filter
     *
     * @param string $filtername a string or an object that implements the '__toString' method
     *
     * @return $this
     */
    public function addStreamFilter(string $filtername): self
    {
        if (!$this->document instanceof StreamIterator) {
            throw new LogicException('The stream filter API can not be used');
        }

        $this->stream_filters[$filtername][] = $this->document->appendFilter($filtername, $this->stream_filter_mode);

        return $this;
    }

    /**
     * Remove all registered stream filter
     */
    protected function clearStreamFilter()
    {
        foreach (array_keys($this->stream_filters) as $filtername) {
            $this->removeStreamFilter($filtername);
        }

        $this->stream_filters = [];
    }

    /**
     * Remove all the stream filter with the same name
     *
     * @param string $filtername the stream filter name
     */
    protected function removeStreamFilter(string $filtername)
    {
        foreach ($this->stream_filters[$filtername] as $filter) {
            $this->document->removeFilter($filter);
        }

        unset($this->stream_filters[$filtername]);
    }
}
