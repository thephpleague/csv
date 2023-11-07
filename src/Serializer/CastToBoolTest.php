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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CastToBoolTest extends TestCase
{
    public function testItFailsWithNonSupportedType(): void
    {
        $this->expectException(MappingFailed::class);

        new CastToBool('int');
    }

    #[DataProvider('providesValidInputValue')]
    public function testItCanConvertStringToBool(
        string $propertyType,
        ?bool $default,
        ?string $input,
        ?bool $expected
    ): void {
        self::assertSame($expected, (new CastToBool($propertyType, $default))->toVariable($input));
    }

    public static function providesValidInputValue(): iterable
    {
        yield 'with a true type - true' => [
            'propertyType' => 'bool',
            'default' => null,
            'input' => 'true',
            'expected' => true,
        ];

        yield 'with a true type - yes' => [
            'propertyType' => 'bool',
            'default' => null,
            'input' => 'yes',
            'expected' => true,
        ];

        yield 'with a true type - 1' => [
            'propertyType' => 'bool',
            'default' => null,
            'input' => '1',
            'expected' => true,
        ];

        yield 'with a false type - false' => [
            'propertyType' => 'bool',
            'default' => null,
            'input' => 'f',
            'expected' => false,
        ];

        yield 'with a false type - no' => [
            'propertyType' => 'bool',
            'default' => null,
            'input' => 'no',
            'expected' => false,
        ];

        yield 'with a false type - 0' => [
            'propertyType' => 'bool',
            'default' => null,
            'input' => '0',
            'expected' => false,
        ];

        yield 'with a null type' => [
            'propertyType' => '?bool',
            'default' => null,
            'input' => null,
            'expected' => null,
        ];

        yield 'with another default type' => [
            'propertyType' => '?bool',
            'default' => false,
            'input' => null,
            'expected' => false,
        ];

        yield 'with the mixed type' => [
            'propertyType' => 'mixed',
            'default' => null,
            'input' => 'YES',
            'expected' => true,
        ];
    }
}
