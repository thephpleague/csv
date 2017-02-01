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
declare(strict_types = 1);

namespace League\Csv;

use League\Csv\Config\ControlsTrait;
use League\Csv\Config\StreamTrait;
use SplFileObject;

/**
 *  An abstract class to enable basic CSV manipulation
 *
 * @package League.csv
 * @since  4.0.0
 *
 */
abstract class AbstractCsv
{
    use ControlsTrait;
    use StreamTrait;

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
     * The file open mode flag
     *
     * @var string
     */
    protected $open_mode;

    /**
     * Creates a new instance
     *
     * The file path can be:
     *
     * - a string
     * - a SplFileObject
     * - a StreamIterator
     *
     * @param mixed $path The file path
     * @param string $open_mode The file open mode flag
     */
    protected function __construct($path, string $open_mode = 'r+')
    {
        $this->open_mode = strtolower($open_mode);
        $this->path = $path;
        $this->initStreamFilter();
    }

    /**
     * The destructor
     */
    public function __destruct()
    {
        $this->path = null;
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
        $csv->setDelimiter($controls[0]);
        $csv->setEnclosure($controls[1]);
        if (isset($controls[2])) {
            $csv->setEscape($controls[2]);
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
     * The string must be an object that implements the `__toString` method,
     * or a string
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
     * @param string $path file path
     * @param string $open_mode the file open mode flag
     *
     * @return static
     */
    public static function createFromPath(string $path, string $open_mode = 'r+'): self
    {
        return new static($path, $open_mode);
    }

    /**
     * Return a new {@link AbstractCsv} instance from another {@link AbstractCsv} object
     *
     * @param string $class the class to be instantiated
     * @param string $open_mode the file open mode flag
     *
     * @return static
     */
    protected function newInstance(string $class, string $open_mode): self
    {
        $csv = new $class($this->path, $open_mode);
        $csv->delimiter = $this->delimiter;
        $csv->enclosure = $this->enclosure;
        $csv->escape = $this->escape;
        $csv->input_bom = $this->input_bom;
        $csv->output_bom = $this->output_bom;
        $csv->newline = $this->newline;

        return $csv;
    }

    /**
     * Return a new {@link Writer} instance from a {@link AbstractCsv} object
     *
     * @param string $open_mode the file open mode flag
     *
     * @return Writer
     */
    public function newWriter(string $open_mode = 'r+'): self
    {
        return $this->newInstance(Writer::class, $open_mode);
    }

    /**
     * Return a new {@link Reader} instance from a {@link AbstractCsv} object
     *
     * @param string $open_mode the file open mode flag
     *
     * @return Reader
     */
    public function newReader(string $open_mode = 'r+'): self
    {
        return $this->newInstance(Reader::class, $open_mode);
    }

    /**
     * Set the Inner Iterator
     *
     * @return StreamIterator|SplFileObject
     */
    protected function getCsvDocument()
    {
        if ($this->path instanceof StreamIterator || $this->path instanceof SplFileObject) {
            return $this->path;
        }

        return new SplFileObject($this->getStreamFilterPath(), $this->open_mode);
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
            header('Content-Type: text/csv');
            header('Content-Transfer-Encoding: binary');
            header("Content-Disposition: attachment; filename=\"$filename\"");
        }

        return $this->fpassthru();
    }

    /**
     * Outputs all data on the csv file to disk
     *
     * @param string $filename CSV File to be saved on disk
     *
     * @return int Returns the file
     */
    public function outputFile(string $filename): int
    {
        if (null !== $filename) {
            $filename = filter_var($filename, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW);
        }

        return $this->saveTofile($filename);

    }

    /**
     * Save csv to file in the disk
     *
     * @param string $filename CSV File to be saved on disk
     *
     * @return int Return the size of file
     */
    protected function saveTofile(string $filename): int
    {
        $bom = '';
        $input_bom = $this->getInputBOM();
        if ($this->output_bom && $input_bom != $this->output_bom) {
            $bom = $this->output_bom;
        }
        $csv = $this->getCsvDocument();
        $csv->rewind();
        if ('' !== $bom) {
            $csv->fseek(mb_strlen($input_bom));
        } else {
            $csv->fseek(0);
        }

        $filesize = 0;
        $outputFile = new SplFileObject($filename, 'w+');

        while (!$csv->eof()) {
            $line = $csv->fgets();
            $outputFile->fwrite($line, strlen($line));
            $filesize += strlen($line);
        }

        $outputFile->fflush();

        return $filesize;
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
        $csv = $this->getCsvDocument();
        $csv->rewind();
        if ('' !== $bom) {
            $csv->fseek(mb_strlen($input_bom));
        }
        echo $bom;
        $res = $csv->fpassthru();

        return $res + strlen($bom);
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
}
