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

use Iterator;
use League\Csv\Exception\InvalidArgumentException;
use League\Csv\Exception\LogicException;
use SplFileObject;

/**
 *  an object oriented interface for a stream resource.
 *
 * @package  League.csv
 * @since    8.2.0
 * @author   Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @internal used internally to iterate over a stream resource
 *
 */
class StreamIterator implements Iterator
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
        if (!is_resource($stream)) {
            throw new InvalidArgumentException(sprintf('Argument 1 passed to %s must be a resource, %s given', __METHOD__, is_object($stream) ? get_class($stream) : gettype($stream)));
        }

        if ('stream' !== ($type = get_resource_type($stream))) {
            throw new InvalidArgumentException(sprintf('Argument 1 passed to %s must be a stream resource, %s resource given', __METHOD__, $type));
        }

        if (!stream_get_meta_data($stream)['seekable']) {
            throw new InvalidArgumentException(sprintf('Argument 1 passed to %s must be a seekable stream resource', __METHOD__));
        }

        $this->stream = $stream;
    }

    /**
     * close the file pointer
     */
    public function __destruct()
    {
        $this->stream = null;
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
        $this->delimiter = $this->filterControl($delimiter, 'delimiter', __METHOD__);
        $this->enclosure = $this->filterControl($enclosure, 'enclosure', __METHOD__);
        $this->escape = $this->filterControl($escape, 'escape', __METHOD__);
    }

    /**
     * Set Flags
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
     * @return int|false
     */
    public function fputcsv(array $fields, string $delimiter = ',', string $enclosure = '"', string $escape = '\\')
    {
        return fputcsv(
            $this->stream,
            $fields,
            $this->filterControl($delimiter, 'delimiter', __METHOD__),
            $this->filterControl($enclosure, 'enclosure', __METHOD__),
            $this->filterControl($escape, 'escape', __METHOD__)
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
     * @return string
     */
    protected function getCurrentLine(): string
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
    public function key(): int
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
    public function valid(): bool
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
    public function fgets(): string
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
    public function fpassthru(): int
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
    public function fseek(int $offset, int $whence = SEEK_SET): int
    {
        return fseek($this->stream, $offset, $whence);
    }

    /**
     * Seek a specified line
     *
     * @param int $line_pos
     *
     * @throws LogicException if the line positon is negative
     */
    public function seek(int $line_pos)
    {
        if (0 > $line_pos) {
            throw new LogicException(sprintf('Can\'t seek stream to negative line %d', $line_pos));
        }

        foreach ($this as $key => $value) {
            if ($key == $line_pos || feof($this->stream)) {
                $this->current_line_number--;
                break;
            }
        }

        $this->current();
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
    public function fwrite(string $str, int $length = 0): int
    {
        return fwrite($this->stream, $str, $length);
    }

    /**
     * append a filter
     *
     * @param string $filter_name
     *
     * @return resource
     */
    public function appendFilter(string $filter_name, int $read_write)
    {
        return stream_filter_append($this->stream, $filter_name, $read_write);
    }

    /**
     * prepend a filter
     *
     * @param string $filter_name
     *
     * @return resource
     */
    public function prependFilter(string $filter_name, int $read_write)
    {
        return stream_filter_prepend($this->stream, $filter_name, $read_write);
    }

    /**
     * remove a registered filter
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
     * @return bool
     */
    public function fflush(): bool
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
