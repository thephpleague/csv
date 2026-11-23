<?php

/**
 * League.Csv (https://csv.thephpleague.com)
 *
 * (c) Ignace Nyamagana Butera <nyamsprod@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace League\Csv;

use SplFileObject;

use function fopen;
use function in_array;
use function is_resource;
use function stream_context_create;
use function stream_context_get_options;
use function stream_get_wrappers;
use function stream_wrapper_register;

/**
 * @internal Exposes an SplFileObject instance as a read-only PHP stream resource.
 *
 * The SplFileObject CSV methods are deprecated since PHP 8.6. Reading the
 * SplFileObject through a stream resource lets the Reader parse it with the
 * Stream class, which relies on the fgetcsv() function instead.
 */
final class SplFileObjectStreamWrapper
{
    private const PROTOCOL = 'league-csv-splfileobject';

    /** @var resource|null */
    public $context;

    private SplFileObject $document;

    /**
     * Returns a read-only stream resource reading from the given SplFileObject.
     *
     * @throws UnavailableStream if the stream resource can not be created
     *
     * @return resource
     */
    public static function open(SplFileObject $document)
    {
        if (!in_array(self::PROTOCOL, stream_get_wrappers(), true)) {
            stream_wrapper_register(self::PROTOCOL, self::class);
        }

        $context = stream_context_create([self::PROTOCOL => ['document' => $document]]);
        $resource = @fopen(self::PROTOCOL.'://document', 'rb', false, $context);
        is_resource($resource) || throw UnavailableStream::dueToPathNotFound($document->getPathname());

        return $resource;
    }

    public function stream_open(string $path, string $mode, int $options, ?string &$opened_path): bool
    {
        $document = null;
        if (is_resource($this->context)) {
            $document = stream_context_get_options($this->context)[self::PROTOCOL]['document'] ?? null;
        }

        if (!$document instanceof SplFileObject) {
            return false;
        }

        $this->document = $document;

        return true;
    }

    public function stream_read(int $count): string|false
    {
        return $this->document->fread($count);
    }

    public function stream_eof(): bool
    {
        return $this->document->eof();
    }

    public function stream_tell(): int
    {
        return (int) $this->document->ftell();
    }

    public function stream_seek(int $offset, int $whence): bool
    {
        return 0 === $this->document->fseek($offset, $whence);
    }

    /**
     * @return array<int|string, int>
     */
    public function stream_stat(): array
    {
        return $this->document->fstat();
    }
}
