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

use PHPUnit\Framework\TestCase;
use ValueError;

use function iterator_to_array;

final class FieldListTest extends TestCase
{
    private Field $s1;
    private Field $s2;
    private Field $s3;

    protected function setUp(): void
    {
        $this->s1 = $this->createField(FieldType::String);
        $this->s2 = $this->createField(FieldType::Numeric);
        $this->s3 = $this->createField(FieldType::Boolean);
    }

    private function createField(FieldType $type): Field
    {
        return new class ($type) implements Field {
            public function __construct(private FieldType $type)
            {
            }

            public function type(): FieldType
            {
                return $this->type;
            }

            public function name(): string
            {
                return $this->type->name;
            }

            public function metadata(): FieldMetadata
            {
                return new FieldMetadata();
            }

            public function confidenceThreshold(): float
            {
                return 0.5;
            }

            public function parse(mixed $value): mixed
            {
                return $value;
            }

            public function evaluate(mixed $value): int
            {
                return 1;
            }
        };
    }

    public function testConstructAndAll(): void
    {
        $list = new FieldList($this->s1, $this->s2);

        self::assertSame([$this->s1, $this->s2], $list->all());
    }

    public function testIsEmpty(): void
    {
        self::assertTrue((new FieldList())->isEmpty());
        self::assertFalse((new FieldList($this->s1))->isEmpty());
    }

    public function testCount(): void
    {
        $list = new FieldList($this->s1, $this->s2);

        self::assertCount(2, $list);
    }

    public function testIterator(): void
    {
        $list = new FieldList($this->s1, $this->s2);

        self::assertSame([$this->s1, $this->s2], iterator_to_array($list));
    }

    public function testFirstAndLast(): void
    {
        $list = new FieldList($this->s1, $this->s2, $this->s3);

        self::assertSame($this->s1, $list->first());
        self::assertSame($this->s3, $list->last());
    }

    public function testNthWithPositiveOffset(): void
    {
        $list = new FieldList($this->s1, $this->s2);

        self::assertSame($this->s2, $list->nth(1));
    }

    public function testNthWithNegativeOffset(): void
    {
        $list = new FieldList($this->s1, $this->s2, $this->s3);

        self::assertSame($this->s3, $list->nth(-1));
        self::assertSame($this->s2, $list->nth(-2));
    }

    public function testNthOutOfBounds(): void
    {
        $list = new FieldList($this->s1);

        self::assertNull($list->nth(10));
        self::assertNull($list->nth(-10));
    }

    public function testGet(): void
    {
        $list = new FieldList($this->s1);

        self::assertSame($this->s1, $list->get(0));
    }

    public function testGetThrows(): void
    {
        $list = new FieldList();

        $this->expectException(ValueError::class);
        $this->expectExceptionMessage('Invalid field offset: 0');

        $list->get(0);
    }

    public function testAppend(): void
    {
        $list = new FieldList($this->s1);

        $new = $list->append(new FieldList($this->s2));

        self::assertSame([$this->s1, $this->s2], $new->all());
        self::assertSame([$this->s1], $list->all()); // immutability
    }

    public function testPrepend(): void
    {
        $list = new FieldList($this->s1);

        $new = $list->prepend($this->s2);

        self::assertSame([$this->s2, $this->s1], $new->all());
        self::assertSame([$this->s1], $list->all()); // immutability
    }

    public function testReplace(): void
    {
        $list = new FieldList($this->s1, $this->s2);

        $new = $list->replace(0, $this->s3);

        self::assertSame([$this->s3, $this->s2], $new->all());
        self::assertSame([$this->s1, $this->s2], $list->all()); // immutability
    }

    public function testReplaceThrows(): void
    {
        $list = new FieldList();

        $this->expectException(ValueError::class);

        $list->replace(0, $this->s1);
    }

    public function testRemoveByOffset(): void
    {
        $list = new FieldList($this->s1, $this->s2, $this->s3);

        $new = $list->removeByOffset(1);

        self::assertSame([$this->s1, $this->s3], $new->all());
    }

    public function testRemoveByOffsetMultiple(): void
    {
        $list = new FieldList($this->s1, $this->s2, $this->s3);

        $new = $list->removeByOffset(0, 2);

        self::assertSame([$this->s2], $new->all());
    }

    public function testRemoveByOffsetInvalidReturnsSameInstance(): void
    {
        $list = new FieldList($this->s1);

        $new = $list->removeByOffset(10);

        self::assertSame($list, $new);
    }

    public function testRemoveByType(): void
    {
        $list = new FieldList($this->s1, $this->s2);

        $new = $list->removeByType(FieldType::String);

        self::assertSame([$this->s2], $new->all());
    }

    public function testRemoveByTypeNoMatchReturnsSameInstance(): void
    {
        $list = new FieldList($this->s1);

        $new = $list->removeByType(FieldType::Numeric);

        self::assertSame($list, $new);
    }
}
