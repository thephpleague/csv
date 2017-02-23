<?php
/**
* This file is part of the League.csv library
*
* @license http://opensource.org/licenses/MIT
* @link https://github.com/thephpleague/csv/
* @version 8.2.0
* @package League.csv
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
namespace League\Csv\Modifier;

use InvalidArgumentException;
use Iterator;
use SplFileObject;

/**
 *  A Stream Iterator
 *
 * @package League.csv
 * @since  8.2.0
 * @internal used internally to iterate over a stream resource
 *
 */
class StreamIterator implements Iterator
{
    /**
     * Stream pointer
     *
     * @var resource
     */
    protected $stream;

    /**
     * Current iterator value
     *
     * @var mixed
     */
    protected $current_line;

    /**
     * Current iterator key
     *
     * @var int
     */
    protected $current_line_number;

    /**
     * Flags for the StreamIterator
     *
     * @var int
     */
    protected $flags = 0;

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
     * New instance
     *
     * @param resource $stream stream type resource
     */
    public function __construct($stream)
    {
        if (!is_resource($stream) || 'stream' !== get_resource_type($stream)) {
            throw new InvalidArgumentException(sprintf(
                'Expected resource to be a stream, received %s instead',
                is_object($stream) ? get_class($stream) : gettype($stream)
            ));
        }

        $data = stream_get_meta_data($stream);
        if (!$data['seekable']) {
            throw new InvalidArgumentException('The stream must be seekable');
        }

        $this->stream = $stream;
    }

    /**
     * Set CSV control
     *
     * @see http://php.net/manual/en/splfileobject.setcsvcontrol.php
     *
     * @param string $delimiter
     * @param string $enclosure
     * @param string $escape
     */
    public function setCsvControl($delimiter = ',', $enclosure = '"', $escape = '\\')
    {
        $this->delimiter = $this->filterControl($delimiter, 'delimiter');
        $this->enclosure = $this->filterControl($enclosure, 'enclosure');
        $this->escape = $this->filterControl($escape, 'escape');
    }

    /**
     * Filter Csv control character
     *
     * @param string $char Csv control character
     * @param string $type Csv control character type
     *
     * @throws InvalidArgumentException If the Csv control character is not one character only.
     *
     * @return string
     */
    private function filterControl($char, $type)
    {
        if (1 == strlen($char)) {
            return $char;
        }

        throw new InvalidArgumentException(sprintf('The %s character must be a single character', $type));
    }

    /**
     * Set Flags
     *
     * @see http://php.net/manual/en/splfileobject.setflags.php
     *
     * @param int $flags
     */
    public function setFlags($flags)
    {
        if (false === filter_var($flags, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]])) {
            throw new InvalidArgumentException('The flags must be a positive integer');
        }

        $this->flags = $flags;
    }

    /**
     * Write a field array as a CSV line
     *
     * @see http://php.net/manual/en/splfileobject.fputcsv.php
     *
     * @param array  $fields
     * @param string $delimiter
     * @param string $enclosure
     *
     * @return int
     */
    public function fputcsv(array $fields, $delimiter = ',', $enclosure = '"', $escape = '\\')
    {
        return fputcsv(
            $this->stream,
            $fields,
            $this->filterControl($delimiter, 'delimiter'),
            $this->filterControl($enclosure, 'enclosure'),
            $this->filterControl($escape, 'escape')
        );
    }

    /**
     * Retrieves the current line of the file.
     *
     * @return mixed
     */
    public function current()
    {
        if (false !== $this->current_line) {
            return $this->current_line;
        }

        if (($this->flags & SplFileObject::READ_CSV) == SplFileObject::READ_CSV) {
            $this->current_line = $this->getCurrentRecord();

            return $this->current_line;
        }

        $this->current_line = $this->getCurrentLine();

        return $this->current_line;
    }

    /**
     * Retrieves the current line as a CSV Record
     *
     * @return array
     */
    protected function getCurrentRecord()
    {
        do {
            $ret = fgetcsv($this->stream, 0, $this->delimiter, $this->enclosure, $this->escape);
        } while ($this->flags & SplFileObject::SKIP_EMPTY && $ret !== false && $ret[0] === null);

        return $ret;
    }

    /**
     * Retrieves the current line as a string
     *
     * @return string
     */
    protected function getCurrentLine()
    {
        do {
            $line = fgets($this->stream);
        } while ($this->flags & SplFileObject::SKIP_EMPTY && $line !== false && rtrim($line, "\r\n") !== '');

        return $line;
    }

    /**
     * Get line number
     *
     * @return int
     */
    public function key()
    {
        return $this->current_line_number;
    }

    /**
     * Read next line
     */
    public function next()
    {
        $this->current_line = false;
        $this->current_line_number++;
    }

    /**
     * Rewind the file to the first line
     */
    public function rewind()
    {
        rewind($this->stream);
        $this->current_line_number = 0;
        $this->current_line = false;
        if ($this->flags & SplFileObject::READ_AHEAD) {
            $this->current();
        }
    }

    /**
     * Not at EOF
     *
     * @return bool
     */
    public function valid()
    {
        if ($this->flags & SplFileObject::READ_AHEAD) {
            return $this->current() !== false;
        }

        return !feof($this->stream);
    }

    /**
     * Gets line from file
     *
     * @see http://php.net/manual/en/splfileobject.fgets.php
     *
     * @return string
     */
    public function fgets()
    {
        if (false !== $this->current_line) {
            $this->next();
        }
        return $this->current_line = $this->getCurrentLine();
    }

    /**
     * Output all remaining data on a file pointer
     *
     * @see http://php.net/manual/en/splfileobject.fpatssthru.php
     *
     * @return int
     */
    public function fpassthru()
    {
        return fpassthru($this->stream);
    }

    /**
     * Seek to a position
     *
     * @see http://php.net/manual/en/splfileobject.fseek.php
     *
     * @param int $offset
     * @param int $whence
     *
     * @return int
     */
    public function fseek($offset, $whence = SEEK_SET)
    {
        return fseek($this->stream, $offset, $whence);
    }

    /**
     * Write to stream
     *
     * @see http://php.net/manual/en/splfileobject.fwrite.php
     *
     * @param string $str
     * @param int    $length
     *
     * @return int
     */
    public function fwrite($str, $length = 0)
    {
        return fwrite($this->stream, $str, $length);
    }

    /**
     * close the file pointer
     */
    public function __destruct()
    {
        $this->stream = null;
    }
}
