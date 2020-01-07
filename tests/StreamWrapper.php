<?php

/**
 * League.Csv (https://csv.thephpleague.com)
 *
 * (c) Ignace Nyamagana Butera <nyamsprod@gmail.com>
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

    /**
     * @var resource
     */
    public $context;

    /**
     * @var resource
     */
    private $stream;

    public static function register(): void
    {
        if (!in_array(self::PROTOCOL, stream_get_wrappers(), true)) {
            stream_wrapper_register(self::PROTOCOL, __CLASS__);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function stream_open(string $path, string $mode, int $options, string &$opened_path = null): bool
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
     *
     * @return string|false
     */
    public function stream_read(int $count)
    {
        return fread($this->stream, $count);
    }

    /**
     * {@inheritdoc}
     *
     * @return int|false
     */
    public function stream_write(string $data)
    {
        return fwrite($this->stream, $data);
    }

    /**
     * {@inheritdoc}
     *
     * @return int|false
     */
    public function stream_tell()
    {
        return ftell($this->stream);
    }

    /**
     * {@inheritdoc}
     */
    public function stream_eof(): bool
    {
        return feof($this->stream);
    }

    /**
     * {@inheritdoc}
     */
    public function stream_seek(int $offset, int $whence): bool
    {
        fseek($this->stream, $whence);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function stream_stat(): array
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
