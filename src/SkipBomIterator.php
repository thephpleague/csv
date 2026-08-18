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
final readonly class SkipBomIterator implements Iterator
{
    private int $offset;

    public function __construct(private SplFileObject|Stream $document)
    {
        $this->offset = Bom::tryFromSequence($document)?->length() ?? 0;
        $this->document->setFlags(SplFileObject::READ_CSV);
    }

    public function current(): mixed
    {
        return $this->document->current();
    }

    public function fseek(int $offset, int $whence): int
    {
        return $this->document->fseek($offset, $whence);
    }

    public function key(): int
    {
        return $this->document->key();
    }

    public function next(): void
    {
        $this->document->next();
    }

    public function rewind(): void
    {
        $this->document->rewind();
        if (0 !== $this->offset) {
            $this->fseek($this->offset, SEEK_SET);
        }
    }

    public function valid(): bool
    {
        return !$this->document->eof();
    }
}
