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
use RuntimeException;
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
    use ControlsTrait;

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
     * New instance
     *
     * The file path can be:
     *
     * - a SplFileObject
     * - a StreamIterator
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
            throw new RuntimeException(error_get_last()['message']);
        }

        return new static(new StreamIterator($stream));
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
     * @inheritdoc
     */
    public function __clone()
    {
        throw new LogicException('An object of class '.get_class($this).' cannot be cloned');
    }
}
