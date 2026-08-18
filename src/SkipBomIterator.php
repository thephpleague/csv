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

use Iterator;
use SplFileObject;
use const SEEK_SET;

/**
 * @internal
 */
final class SkipBomIterator implements Iterator
{
    public static function fromDocument(
        SplFileObject|Stream $document,
        string $delimiter = ',',
        string $enclosure = '"',
        string $escape = '\\',
    ): self {

        return new self(
            $document,
            Bom::tryFromSequence($document)?->length() ?? 0,
            $delimiter,
            $enclosure,
            $escape,
        );
    }

    public function __construct(
        private readonly SplFileObject|Stream $file,
        private readonly int $offset,
        private readonly string $delimiter = ',',
        private readonly string $enclosure = '"',
        private readonly string $escape = '\\',
    ) {
        $this->file->setFlags(SplFileObject::READ_CSV);
        $this->file->setCsvControl($this->delimiter, $this->enclosure, $this->escape);
    }

    public function current(): mixed
    {
        return $this->file->current();
    }

    public function fseek(int $offset, int $whence): int
    {
        return $this->file->fseek($offset, $whence);
    }

    public function key(): int
    {
        return $this->file->key();
    }

    public function next(): void
    {
        $this->file->next();
    }

    public function rewind(): void
    {
        $this->file->rewind();
        if (0 !== $this->offset) {
            $this->fseek($this->offset, SEEK_SET);
        }
    }

    public function valid(): bool
    {
        return !$this->file->eof();
    }
}
