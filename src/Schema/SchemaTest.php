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
use PHPUnit\Framework\TestCase;
use ValueError;

final class SchemaTest extends TestCase
{
    private function field(string $name): Field
    {
        return new CustomField(fn (mixed $value): mixed => $value, $name, 0.95);
    }

    public function testConstructAndCount(): void
    {
        $schema = new Schema([
            'name' => $this->field('string'),
            'age' => $this->field('numeric'),
        ]);

        self::assertCount(2, $schema);
    }

    public function testConstructThrowsOnDuplicateKey(): void
    {
        $test = new class () implements IteratorAggregate {
            public function getIterator(): Iterator
            {
                yield 'a' => new StringField();
                yield 'a' => new BooleanField();
            }
        };

        $this->expectException(ValueError::class);
        new Schema($test);
    }

    public function testIsEmpty(): void
    {
        $schema = new Schema();
        self::assertTrue($schema->isEmpty());

        $schema = new Schema(['name' => $this->field('string')]);
        self::assertFalse($schema->isEmpty());
    }

    public function testAllReturnsFields(): void
    {
        $fields = [
            'name' => $this->field('string'),
        ];

        $schema = new Schema($fields);

        self::assertSame($fields, $schema->all());
    }

    public function testNames(): void
    {
        $schema = new Schema([
            'name' => $this->field('string'),
            'age' => $this->field('numeric'),
        ]);

        self::assertSame(['name', 'age'], $schema->names());
    }

    public function testTypes(): void
    {
        $schema = new Schema([
            'name' => $this->field('string'),
            'age' => $this->field('numeric'),
        ]);

        self::assertSame([
            'name' => 'string',
            'age' => 'numeric',
        ], $schema->types());
    }

    public function testHas(): void
    {
        $schema = new Schema([
            'name' => $this->field('string'),
        ]);

        self::assertTrue($schema->has('name'));
        self::assertFalse($schema->has('age'));
    }

    public function testGetReturnsField(): void
    {
        $field = $this->field('string');

        $schema = new Schema([
            'name' => $field,
        ]);

        self::assertSame($field, $schema->get('name'));
    }

    public function testGetThrowsOnMissingKey(): void
    {
        $schema = new Schema();

        $this->expectException(ValueError::class);

        $schema->get('missing');
    }

    public function testIterator(): void
    {
        $fields = [
            'name' => $this->field('string'),
            'age' => $this->field('numeric'),
        ];

        $schema = new Schema($fields);

        $result = [];
        foreach ($schema as $key => $field) {
            $result[$key] = $field;
        }

        self::assertSame($fields, $result);
    }

    public function testMap(): void
    {
        $schema = new Schema([
            'name' => $this->field('string'),
            'age' => $this->field('numeric'),
        ]);

        $result = iterator_to_array(
            $schema->map(fn (Field $field, $key) => $field->name())
        );

        self::assertSame([
            'name' => 'string',
            'age' => 'numeric',
        ], $result);
    }

    public function testGetByNumericIndex(): void
    {
        $fields = [
            $this->field('string'),
            $this->field('numeric'),
        ];

        $schema = new Schema($fields);

        self::assertSame('string', $schema->get(0)->name());
        self::assertSame('numeric', $schema->get(1)->name());
    }

    public function testHasWithNumericIndex(): void
    {
        $schema = new Schema([
            $this->field('string'),
        ]);

        self::assertTrue($schema->has(0));
        self::assertFalse($schema->has(1));
    }
}
