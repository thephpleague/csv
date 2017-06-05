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

use League\Csv\Exception\LogicException;
use League\Csv\Exception\RuntimeException;
use SeekableIterator;
use SplFileObject;
use TypeError;

/**
 * An object oriented API for a seekable stream resource.
 *
 * @package  League.csv
 * @since    8.2.0
 * @author   Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @internal used internally to iterate over a stream resource
 *
 */
class StreamIterator implements SeekableIterator
{
    use ValidatorTrait;

    /**
     * Attached filters
     *
     * @var resource[]
     */
    protected $filters;

    /**
     * Stream pointer
     *
     * @var resource
     */
    protected $stream;

    /**
     * Tell whether the stream should be closed on object destruction
     *
     * @var bool
     */
    protected $should_close_stream = false;

    /**
     * Current iterator value
     *
     * @var mixed
     */
    protected $value;

    /**
     * Current iterator key
     *
     * @var int
     */
    protected $offset;

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
     *
     * @param resource $stream stream type resource
     *
     * @throws RuntimeException if the argument passed is not a seeakable stream resource
     */
    public function __construct($stream)
    {
        if (!is_resource($stream)) {
            throw new TypeError(sprintf('Argument passed must be a seekable stream resource, %s given', gettype($stream)));
        }

        if ('stream' !== ($type = get_resource_type($stream))) {
            throw new TypeError(sprintf('Argument passed must be a seekable stream resource, %s resource given', $type));
        }

        if (!stream_get_meta_data($stream)['seekable']) {
            throw new RuntimeException('Argument passed must be a seekable stream resource');
        }

        $this->stream = $stream;
    }

    /**
     * close the file pointer
     */
    public function __destruct()
    {
        if ($this->should_close_stream) {
            fclose($this->stream);
        }

        $this->stream = null;
    }

    /**
     * Return a new instance from a file path
     *
     * @param string        $path      file path
     * @param string        $open_mode the file open mode flag
     * @param resource|null $context   the resource context
     *
     * @throws RuntimeException if the stream resource can not be created
     *
     * @return static
     */
    public static function createFromPath(string $path, string $open_mode = 'r', $context = null): self
    {
        $args = [$path, $open_mode, false];
        if (null !== $context) {
            $args[] = $context;
        }

        if (!$stream = @fopen(...$args)) {
            throw new RuntimeException(error_get_last()['message']);
        }

        $instance = new static($stream);
        $instance->should_close_stream = true;

        return $instance;
    }

    /**
     * Return a new instance from a string
     *
     * @param string $content the CSV document as a string
     *
     * @return static
     */
    public static function createFromString(string $content): self
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);

        $instance = new static($stream);
        $instance->should_close_stream = true;

        return $instance;
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
    public function setCsvControl(string $delimiter = ',', string $enclosure = '"', string $escape = '\\')
    {
        $this->delimiter = $this->filterControl($delimiter, 'delimiter');
        $this->enclosure = $this->filterControl($enclosure, 'enclosure');
        $this->escape = $this->filterControl($escape, 'escape');
    }

    /**
     * Set StreamIterator Flags
     *
     * @see http://php.net/manual/en/splfileobject.setflags.php
     *
     * @param int $flags
     */
    public function setFlags(int $flags)
    {
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
     * @param string $escape
     *
     * @return int|bool
     */
    public function fputcsv(array $fields, string $delimiter = ',', string $enclosure = '"', string $escape = '\\')
    {
        return fputcsv($this->stream, $fields, $this->filterControl($delimiter, 'delimiter'), $this->filterControl($enclosure, 'enclosure'), $this->filterControl($escape, 'escape'));
    }

    /**
     * Get line number
     *
     * @see http://php.net/manual/en/splfileobject.key.php
     *
     * @return int
     */
    public function key()
    {
        return $this->offset;
    }

    /**
     * Read next line
     *
     * @see http://php.net/manual/en/splfileobject.next.php
     *
     */
    public function next()
    {
        $this->value = false;
        $this->offset++;
    }

    /**
     * Rewind the file to the first line
     *
     * @see http://php.net/manual/en/splfileobject.rewind.php
     *
     */
    public function rewind()
    {
        rewind($this->stream);
        $this->offset = 0;
        $this->value = false;
        if ($this->flags & SplFileObject::READ_AHEAD) {
            $this->current();
        }
    }

    /**
     * Not at EOF
     *
     * @see http://php.net/manual/en/splfileobject.valid.php
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
     * Retrieves the current line of the file.
     *
     * @see http://php.net/manual/en/splfileobject.current.php
     *
     * @return mixed
     */
    public function current()
    {
        if (false !== $this->value) {
            return $this->value;
        }

        if (($this->flags & SplFileObject::READ_CSV) == SplFileObject::READ_CSV) {
            $this->value = $this->getCurrentRecord();

            return $this->value;
        }

        $this->value = $this->getCurrentLine();

        return $this->value;
    }

    /**
     * Retrieves the current line as a CSV Record
     *
     * @return array|bool
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
     * @return string|bool
     */
    protected function getCurrentLine()
    {
        do {
            $line = fgets($this->stream);
        } while ($this->flags & SplFileObject::SKIP_EMPTY && $line !== false && rtrim($line, "\r\n") !== '');

        return $line;
    }

    /**
     * Seek to specified line
     *
     * @see http://php.net/manual/en/splfileobject.seek.php
     *
     * @param int $position
     */
    public function seek($position)
    {
        $pos = $this->filterMinRange((int) $position, 0, 'Can\'t seek stream to negative line %d');
        foreach ($this as $key => $value) {
            if ($key === $pos || feof($this->stream)) {
                $this->offset--;
                break;
            }
        }

        $this->current();
    }

    /**
     * Gets line from file
     *
     * @see http://php.net/manual/en/splfileobject.fgets.php
     *
     * @return string|bool
     */
    public function fgets()
    {
        if (false !== $this->value) {
            $this->next();
        }

        return $this->value = $this->getCurrentLine();
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
     * Read from file
     *
     * @see http://php.net/manual/en/splfileobject.fread.php
     *
     * @param int $length The number of bytes to read
     *
     * @return string|false
     */
    public function fread($length)
    {
        return fread($this->stream, $length);
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
    public function fseek(int $offset, int $whence = SEEK_SET)
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
     * @return int|bool
     */
    public function fwrite(string $str, int $length = 0)
    {
        return fwrite($this->stream, $str, $length);
    }

    /**
     * append a filter
     *
     * @see http://php.net/manual/en/function.stream-filter-append.php
     *
     * @param string $filter_name
     * @param int    $read_write
     * @param mixed  $params
     *
     * @throws RuntimeException if the filter can not be appended
     *
     * @return resource
     */
    public function appendFilter(string $filter_name, int $read_write, $params = null)
    {
        $res = @stream_filter_append($this->stream, $filter_name, $read_write, $params);
        if (is_resource($res)) {
            return $res;
        }

        throw new RuntimeException(error_get_last()['message']);
    }

    /**
     * Removes a registered filter
     *
     * @see http://php.net/manual/en/function.stream-filter-remove.php
     *
     * @param resource $resource
     */
    public function removeFilter($resource)
    {
        return stream_filter_remove($resource);
    }

    /**
     * Flushes the output to a file
     *
     * @see http://php.net/manual/en/splfileobject.fwrite.php
     *
     * @return bool
     */
    public function fflush()
    {
        return fflush($this->stream);
    }

    /**
     * @inheritdoc
     */
    public function __clone()
    {
        throw new LogicException('An object of class '.StreamIterator::class.' cannot be cloned');
    }
}
