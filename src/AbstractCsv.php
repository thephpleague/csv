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

use LogicException;
use SplFileObject;

/**
 *  An abstract class to enable basic CSV manipulation
 *
 * @package League.csv
 * @since  4.0.0
 * @author  Ignace Nyamagana Butera <nyamsprod@gmail.com>
 *
 */
abstract class AbstractCsv
{
    use ValidatorTrait;

    /**
     *  UTF-8 BOM sequence
     */
    const BOM_UTF8 = "\xEF\xBB\xBF";

    /**
     * UTF-16 BE BOM sequence
     */
    const BOM_UTF16_BE = "\xFE\xFF";

    /**
     * UTF-16 LE BOM sequence
     */
    const BOM_UTF16_LE = "\xFF\xFE";

    /**
     * UTF-32 BE BOM sequence
     */
    const BOM_UTF32_BE = "\x00\x00\xFE\xFF";

    /**
     * UTF-32 LE BOM sequence
     */
    const BOM_UTF32_LE = "\xFF\xFE\x00\x00";

    /**
     * The CSV document
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
     *
     * @var int
     */
    protected $stream_filter_mode;

    /**
     * The CSV document BOM sequence
     *
     * @var string|null
     */
    protected $input_bom = null;

    /**
     * New instance
     *
     * @param SplFileObject|StreamIterator $document The CSV Object instance
     */
    protected function __construct($document)
    {
        $this->document = $document;
    }

    /**
     * The destructor
     */
    public function __destruct()
    {
        $this->clearStreamFilter();
        $this->document = null;
    }

    /**
     * @inheritdoc
     */
    public function __clone()
    {
        throw new LogicException('An object of class '.get_class($this).' cannot be cloned');
    }

    /**
     * Return a new {@link AbstractCsv} from a SplFileObject
     *
     * @param SplFileObject $file
     *
     * @return static
     */
    public static function createFromFileObject(SplFileObject $file): self
    {
        $csv = new static($file);
        $controls = $file->getCsvControl();
        $csv->delimiter = $controls[0];
        $csv->enclosure = $controls[1];
        if (isset($controls[2])) {
            $csv->escape = $controls[2];
        }

        return $csv;
    }

    /**
     * Return a new {@link AbstractCsv} from a PHP resource stream
     *
     * @param resource $stream
     *
     * @return static
     */
    public static function createFromStream($stream): self
    {
        return new static(new StreamIterator($stream));
    }

    /**
     * Return a new {@link AbstractCsv} from a string
     *
     * @param string $str the string
     *
     * @return static
     */
    public static function createFromString(string $str): self
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $str);

        return new static(new StreamIterator($stream));
    }

    /**
     * Return a new {@link AbstractCsv} from a file path
     *
     * @param string $path      file path
     * @param string $open_mode the file open mode flag
     *
     * @return static
     */
    public static function createFromPath(string $path, string $open_mode = 'r+'): self
    {
        if (!$stream = @fopen($path, $open_mode)) {
            throw new InvalidArgumentException(error_get_last()['message']);
        }

        return new static(new StreamIterator($stream));
    }

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
        if (null !== $this->input_bom) {
            return $this->input_bom;
        }

        $bom = [
            self::BOM_UTF32_BE, self::BOM_UTF32_LE,
            self::BOM_UTF16_BE, self::BOM_UTF16_LE, self::BOM_UTF8,
        ];

        $this->document->setFlags(SplFileObject::READ_CSV);
        $this->document->rewind();
        $line = $this->document->fgets();
        $res = array_filter($bom, function ($sequence) use ($line) {
            return strpos($line, $sequence) === 0;
        });

        $this->input_bom = (string) array_shift($res);

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
     * Retrieves the CSV content
     *
     * @return string
     */
    public function __toString(): string
    {
        ob_start();
        $this->fpassthru();

        return ob_get_clean();
    }

    /**
     * Outputs all data on the CSV file
     *
     * @param string $filename CSV downloaded name if present adds extra headers
     *
     * @return int Returns the number of characters read from the handle
     *             and passed through to the output.
     */
    public function output(string $filename = null): int
    {
        if (null !== $filename) {
            $filename = filter_var($filename, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW);
            header('content-type: text/csv');
            header('content-transfer-encoding: binary');
            header('content-disposition: attachment; filename="'.rawurlencode($filename).'"');
        }

        return $this->fpassthru();
    }

    /**
     * Outputs all data from the CSV
     *
     * @return int Returns the number of characters read from the handle
     *             and passed through to the output.
     */
    protected function fpassthru(): int
    {
        $bom = '';
        $input_bom = $this->getInputBOM();
        if ($this->output_bom && $input_bom != $this->output_bom) {
            $bom = $this->output_bom;
        }

        $this->document->rewind();
        if ('' !== $bom) {
            $this->document->fseek(mb_strlen($input_bom));
        }
        echo $bom;
        $res = $this->document->fpassthru();

        return $res + strlen($bom);
    }

    /**
     * Sets the field delimiter
     *
     * @param string $delimiter
     *
     * @return static
     */
    public function setDelimiter(string $delimiter): self
    {
        $this->delimiter = $this->filterControl($delimiter, 'delimiter');
        $this->resetDynamicProperties();

        return $this;
    }

    /**
     * Reset dynamic CSV document properties to improve performance
     */
    protected function resetDynamicProperties()
    {
    }

    /**
     * Sets the field enclosure
     *
     * @param string $enclosure
     *
     * @return static
     */
    public function setEnclosure(string $enclosure): self
    {
        $this->enclosure = $this->filterControl($enclosure, 'enclosure');
        $this->resetDynamicProperties();

        return $this;
    }

    /**
     * Sets the field escape character
     *
     * @param string $escape
     *
     * @return static
     */
    public function setEscape(string $escape): self
    {
        $this->escape = $this->filterControl($escape, 'escape');
        $this->resetDynamicProperties();

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
     * @throws LogicException If the stream filter API can not be used
     *
     * @return static
     */
    public function addStreamFilter(string $filtername): self
    {
        if (!$this->document instanceof StreamIterator) {
            throw new LogicException('The stream filter API can not be used');
        }

        $this->stream_filters[$filtername][] = $this->document->appendFilter($filtername, $this->stream_filter_mode);
        $this->resetDynamicProperties();
        $this->input_bom = null;

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
