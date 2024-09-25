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

namespace League\Csv\Serializer;

use Countable;
use DateTimeInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Traversable;

final class CastToBoolTest extends TestCase
{
    public function testItFailsWithNonSupportedType(): void
    {
        $this->expectException(MappingFailed::class);

        new CastToBool(new ReflectionProperty((new class () {
            public string $string;
        })::class, 'string'));
    }

    #[DataProvider('providesValidInputValue')]
    public function testItCanConvertStringToBool(
        ReflectionProperty $propertyType,
        ?bool $default,
        string|bool|null $input,
        ?bool $expected
    ): void {
        $cast = new CastToBool($propertyType);
        $cast->setOptions($default);

        self::assertSame($expected, $cast->toVariable($input));
    }

    public static function providesValidInputValue(): iterable
    {
        yield 'with a true type - true' => [
            'propertyType' => new ReflectionProperty((new class () {
                public bool $boolean;
            })::class, 'boolean'),
            'default' => null,
            'input' => 'true',
            'expected' => true,
        ];

        yield 'with a true type - yes' => [
            'propertyType' => new ReflectionProperty((new class () {
                public bool $boolean;
            })::class, 'boolean'),
            'default' => null,
            'input' => 'yes',
            'expected' => true,
        ];

        yield 'with a true type - 1' => [
            'propertyType' => new ReflectionProperty((new class () {
                public bool $boolean;
            })::class, 'boolean'),
            'default' => null,
            'input' => '1',
            'expected' => true,
        ];

        yield 'with a false type - false' => [
            'propertyType' => new ReflectionProperty((new class () {
                public bool $boolean;
            })::class, 'boolean'),
            'default' => null,
            'input' => 'f',
            'expected' => false,
        ];

        yield 'with a false type - no' => [
            'propertyType' => new ReflectionProperty((new class () {
                public bool $boolean;
            })::class, 'boolean'),
            'default' => null,
            'input' => 'no',
            'expected' => false,
        ];

        yield 'with a false type - 0' => [
            'propertyType' => new ReflectionProperty((new class () {
                public bool $boolean;
            })::class, 'boolean'),
            'default' => null,
            'input' => '0',
            'expected' => false,
        ];

        yield 'with a null type' => [
            'propertyType' => new ReflectionProperty((new class () {
                public ?bool $boolean;
            })::class, 'boolean'),
            'default' => null,
            'input' => null,
            'expected' => null,
        ];

        yield 'with another default type' => [
            'propertyType' => new ReflectionProperty((new class () {
                public ?bool $nullableBool;
            })::class, 'nullableBool'),
            'default' => false,
            'input' => null,
            'expected' => false,
        ];

        yield 'with the mixed type' => [
            'propertyType' => new ReflectionProperty((new class () {
                public mixed $mixed;
            })::class, 'mixed'),
            'default' => null,
            'input' => 'YES',
            'expected' => true,
        ];

        yield 'with union type' => [
            'propertyType' => new ReflectionProperty((new class () {
                public DateTimeInterface|bool|null $unionType;
            })::class, 'unionType'),
            'default' =>  false,
            'input' => 'yes',
            'expected' => true,
        ];

        yield 'with nullable union type' => [
            'propertyType' => new ReflectionProperty((new class () {
                public DateTimeInterface|bool|null $unionType;
            })::class, 'unionType'),
            'default' => false,
            'input' => null,
            'expected' => false,
        ];

        yield 'with a boolean false value' => [
            'propertyType' => new ReflectionProperty((new class () {
                public DateTimeInterface|bool|null $unionType;
            })::class, 'unionType'),
            'default' => false,
            'input' => false,
            'expected' => false,
        ];

        yield 'with a boolean true value' => [
            'propertyType' => new ReflectionProperty((new class () {
                public DateTimeInterface|bool|null $unionType;
            })::class, 'unionType'),
            'default' => false,
            'input' => true,
            'expected' => true,
        ];
    }

    #[DataProvider('invalidPropertyName')]
    public function testItWillThrowIfNotTypeAreSupported(string $propertyName): void
    {
        $this->expectException(MappingFailed::class);

        $class = new class () {
            public ?bool $nullableBool;
            public bool $boolean;
            public mixed $mixed;
            public string $string;
            public ?int $nullableInt;
            public DateTimeInterface|bool|null $unionType;
            public DateTimeInterface|string $invalidUnionType;
            public Countable&Traversable $intersectionType;
        };

        $reflectionProperty = new ReflectionProperty($class::class, $propertyName);

        new CastToBool($reflectionProperty);
    }

    public static function invalidPropertyName(): iterable
    {
        return [
            'named type not supported' => ['propertyName' => 'nullableInt'],
            'union type not supported' => ['propertyName' => 'invalidUnionType'],
            'intersection type not supported' => ['propertyName' => 'intersectionType'],
        ];
    }
}
