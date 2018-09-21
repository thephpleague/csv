<?php

/**
 * League.Csv (https://csv.thephpleague.com).
 *
 * @author  Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @license https://github.com/thephpleague/csv/blob/master/LICENSE (MIT License)
 * @version 9.2.0
 * @link    https://github.com/thephpleague/csv
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace LeagueTest\Csv;

use function feof;
use function fread;
use function fseek;
use function ftell;
use function fwrite;
use function stream_context_get_options;
use function stream_get_wrappers;
use function stream_wrapper_register;

final class StreamWrapper
{
    const PROTOCOL = 'leaguetest';

    public $context;

    private $stream;

    public static function register()
    {
        if (!in_array(self::PROTOCOL, stream_get_wrappers(), true)) {
            stream_wrapper_register(self::PROTOCOL, __CLASS__);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function stream_open($path, $mode, $options, &$opened_path)
    {
        $options = stream_context_get_options($this->context);
        if (!isset($options[self::PROTOCOL]['stream'])) {
            return false;
        }

        $this->stream = $options[self::PROTOCOL]['stream'];

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function stream_read($count)
    {
        return fread($this->stream, $count);
    }

    /**
     * {@inheritdoc}
     */
    public function stream_write($data)
    {
        return fwrite($this->stream, $data);
    }

    /**
     * {@inheritdoc}
     */
    public function stream_tell()
    {
        return ftell($this->stream);
    }

    /**
     * {@inheritdoc}
     */
    public function stream_eof()
    {
        return feof($this->stream);
    }

    /**
     * {@inheritdoc}
     */
    public function stream_seek($offset, $whence)
    {
        fseek($this->stream, $whence);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function stream_stat()
    {
        return [
            'dev'     => 0,
            'ino'     => 0,
            'mode'    => 33206,
            'nlink'   => 0,
            'uid'     => 0,
            'gid'     => 0,
            'rdev'    => 0,
            'size'    => 0,
            'atime'   => 0,
            'mtime'   => 0,
            'ctime'   => 0,
            'blksize' => 0,
            'blocks'  => 0,
        ];
    }
}
