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

namespace League\Csv\Schema;

use Iterator;
use IteratorAggregate;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ValueError;

use function iterator_to_array;

#[CoversClass(FieldMetadata::class)]
final class FieldMetadataTest extends TestCase
{
    public function testConstructAndAll(): void
    {
        $metadata = new FieldMetadata(['a' => 1, 'b' => 2]);

        self::assertSame(['a' => 1, 'b' => 2], $metadata->all());
    }

    public function testCount(): void
    {
        $metadata = new FieldMetadata(['a' => 1, 'b' => 2]);

        self::assertCount(2, $metadata);
    }

    public function testIsEmpty(): void
    {
        self::assertTrue((new FieldMetadata([]))->isEmpty());
        self::assertFalse((new FieldMetadata(['a' => 1]))->isEmpty());
    }

    public function testKeys(): void
    {
        $metadata = new FieldMetadata(['a' => 1, 'b' => 2]);

        self::assertSame(['a', 'b'], $metadata->keys());
    }

    public function testHas(): void
    {
        $metadata = new FieldMetadata(['a' => 1]);

        self::assertTrue($metadata->has('a'));
        self::assertFalse($metadata->has('b'));
    }

    public function testGet(): void
    {
        $metadata = new FieldMetadata(['a' => 42]);

        self::assertSame(42, $metadata->get('a'));
    }

    public function testGetThrowsOnMissingKey(): void
    {
        $metadata = new FieldMetadata();

        $this->expectException(ValueError::class);
        $this->expectExceptionMessage('The key does not exist: a');

        $metadata->get('a');
    }

    public function testIterator(): void
    {
        $data = ['a' => 1, 'b' => 2];
        $metadata = new FieldMetadata($data);

        self::assertSame($data, iterator_to_array($metadata));
    }

    public function testConstructWithDuplicateKeysThrows(): void
    {
        $test = new class () implements IteratorAggregate {
            public function getIterator(): Iterator
            {
                yield 'a' => 1;
                yield 'a' => 2;
            }
        };

        $this->expectException(ValueError::class);

        new FieldMetadata($test);
    }

    public function testMergeSingle(): void
    {
        $m1 = new FieldMetadata(['a' => 1]);
        $m2 = new FieldMetadata(['b' => 2]);

        $merged = $m1->union($m2);

        self::assertSame(['a' => 1, 'b' => 2], $merged->all());
    }

    public function testMergeMultiple(): void
    {
        $m1 = new FieldMetadata(['a' => 1]);
        $m2 = new FieldMetadata(['b' => 2]);
        $m3 = new FieldMetadata(['c' => 3]);

        $merged = $m1->union($m2, $m3);

        self::assertSame([
            'a' => 1,
            'b' => 2,
            'c' => 3,
        ], $merged->all());
    }

    public function testMergeDuplicateKeysThrows(): void
    {
        $m1 = new FieldMetadata(['a' => 1]);
        $m2 = new FieldMetadata(['a' => 2]);

        $this->expectException(ValueError::class);

        $m1->union($m2);
    }

    public function testMergeWithNoArgumentsReturnsSameInstance(): void
    {
        $m1 = new FieldMetadata(['a' => 1]);

        $merged = $m1->union();

        self::assertSame($m1, $merged);
    }
}
