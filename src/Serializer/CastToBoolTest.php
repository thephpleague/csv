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

        new CastToBool(new ReflectionProperty(BoolClass::class, 'string'));
    }

    #[DataProvider('providesValidInputValue')]
    public function testItCanConvertStringToBool(
        ReflectionProperty $propertyType,
        ?bool $default,
        ?string $input,
        ?bool $expected
    ): void {
        $cast = new CastToBool($propertyType);
        $cast->setOptions($default);

        self::assertSame($expected, $cast->toVariable($input));
    }

    public static function providesValidInputValue(): iterable
    {
        yield 'with a true type - true' => [
            'propertyType' => new ReflectionProperty(BoolClass::class, 'boolean'),
            'default' => null,
            'input' => 'true',
            'expected' => true,
        ];

        yield 'with a true type - yes' => [
            'propertyType' => new ReflectionProperty(BoolClass::class, 'boolean'),
            'default' => null,
            'input' => 'yes',
            'expected' => true,
        ];

        yield 'with a true type - 1' => [
            'propertyType' => new ReflectionProperty(BoolClass::class, 'boolean'),
            'default' => null,
            'input' => '1',
            'expected' => true,
        ];

        yield 'with a false type - false' => [
            'propertyType' => new ReflectionProperty(BoolClass::class, 'boolean'),
            'default' => null,
            'input' => 'f',
            'expected' => false,
        ];

        yield 'with a false type - no' => [
            'propertyType' => new ReflectionProperty(BoolClass::class, 'boolean'),
            'default' => null,
            'input' => 'no',
            'expected' => false,
        ];

        yield 'with a false type - 0' => [
            'propertyType' => new ReflectionProperty(BoolClass::class, 'boolean'),
            'default' => null,
            'input' => '0',
            'expected' => false,
        ];

        yield 'with a null type' => [
            'propertyType' => new ReflectionProperty(BoolClass::class, 'nullableBool'),
            'default' => null,
            'input' => null,
            'expected' => null,
        ];

        yield 'with another default type' => [
            'propertyType' => new ReflectionProperty(BoolClass::class, 'nullableBool'),
            'default' => false,
            'input' => null,
            'expected' => false,
        ];

        yield 'with the mixed type' => [
            'propertyType' => new ReflectionProperty(BoolClass::class, 'mixed'),
            'default' => null,
            'input' => 'YES',
            'expected' => true,
        ];

        yield 'with union type' => [
            'reflectionProperty' => new ReflectionProperty(BoolClass::class, 'unionType'),
            'default' =>  false,
            'input' => 'yes',
            'expected' => true,
        ];

        yield 'with nullable union type' => [
            'reflectionProperty' => new ReflectionProperty(BoolClass::class, 'unionType'),
            'default' => false,
            'input' => null,
            'expected' => false,
        ];
    }

    #[DataProvider('invalidPropertyName')]
    public function testItWillThrowIfNotTypeAreSupported(string $propertyName): void
    {
        $this->expectException(MappingFailed::class);

        $reflectionProperty = new ReflectionProperty(BoolClass::class, $propertyName);

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

class BoolClass
{
    public ?bool $nullableBool;
    public bool $boolean;
    public mixed $mixed;
    public string $string;
    public ?int $nullableInt;
    public DateTimeInterface|bool|null $unionType;
    public DateTimeInterface|string $invalidUnionType;
    public Countable&Traversable $intersectionType;
}
