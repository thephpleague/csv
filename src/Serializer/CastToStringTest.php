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

final class CastToStringTest extends TestCase
{
    public function testItFailsWithNonSupportedType(): void
    {
        $this->expectException(MappingFailed::class);

        new CastToString('int');
    }

    #[DataProvider('providesValidInputValue')]
    public function testItCanConvertStringToBool(
        string $propertyType,
        ?string $default,
        ?string $input,
        ?string $expected
    ): void {
        self::assertSame($expected, (new CastToString($propertyType, $default))->toVariable($input));
    }

    public static function providesValidInputValue(): iterable
    {
        yield 'with a string/nullable type' => [
            'propertyType' => '?string',
            'default' => null,
            'input' => 'true',
            'expected' => 'true',
        ];

        yield 'with a string type' => [
            'propertyType' => 'string',
            'default' => null,
            'input' => 'yes',
            'expected' => 'yes',
        ];

        yield 'with a nullable string type and the null value' => [
            'propertyType' => '?string',
            'default' => null,
            'input' => null,
            'expected' => null,
        ];

        yield 'with a nullable string type and a non null default value' => [
            'propertyType' => '?string',
            'default' => 'foo',
            'input' => null,
            'expected' => 'foo',
        ];
    }
}
