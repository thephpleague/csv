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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use ValueError;

#[CoversClass(JsonField::class)]
final class JsonFieldTest extends TestCase
{
    private JsonField $field;

    protected function setUp(): void
    {
        $this->field = new JsonField();
    }

    public function testTypeAndName(): void
    {
        self::assertSame(FieldType::Json, $this->field->type());
        self::assertSame(FieldType::Json->value, $this->field->name());
    }

    public function testDetailsExposeFlagsAndDepth(): void
    {
        $field = new JsonField(flags: JSON_BIGINT_AS_STRING, depth: 256);
        $details = $field->metadata();

        self::assertSame(JSON_BIGINT_AS_STRING, $details->get('flags'));
        self::assertSame(256, $details->get('depth'));
    }

    public static function provideValidJson(): array
    {
        return [
            ['{"a":1}', ['a' => 1]],
            ['{"a":1,"b":2}', ['a' => 1, 'b' => 2]],
            ['[1,2,3]', [1, 2, 3]],
            ['  {"foo":"bar"}  ', ['foo' => 'bar']],
            ['{"nested":{"x":1}}', ['nested' => ['x' => 1]]],
            ['true', true],
            ['false', false],
            ['null', null],
            ['123', 123],
        ];
    }

    #[DataProvider('provideValidJson')]
    public function testParseValidJson(string $input, mixed $expected): void
    {
        $result = $this->field->parse($input);

        self::assertSame($expected, $result);
    }

    public static function provideInvalidJson(): array
    {
        return [
            [''],
            ['   '],
            ['{invalid}'],
            ['{"a":1'],          // missing closing brace
            ['[1,2,]'],         // trailing comma
            ['foo'],
        ];
    }

    #[DataProvider('provideInvalidJson')]
    public function testParseInvalidJsonReturnsNull(string $input): void
    {
        self::assertNull($this->field->parse($input));
    }

    public function testParseRejectsNonStringValues(): void
    {
        self::assertNull($this->field->parse(null));
        self::assertNull($this->field->parse(123));
        self::assertNull($this->field->parse([]));
        self::assertNull($this->field->parse(new stdClass()));
    }

    public function testDepthLimitIsRespected(): void
    {
        $field = new JsonField(depth: 2);

        $json = '{"a":{"b":{"c":1}}}'; // depth 3

        self::assertNull($field->parse($json));
    }

    public function testFlagsAffectDecoding(): void
    {
        $json = '{"big":12345678901234567890}';

        $default = new JsonField();
        $withFlag = new JsonField(flags: JSON_BIGINT_AS_STRING);

        $defaultResult = $default->parse($json);
        $flagResult = $withFlag->parse($json);

        // default: bigint becomes float
        self::assertIsArray($defaultResult);
        self::assertIsFloat($defaultResult['big']);

        // with flag: bigint preserved as string
        self::assertIsArray($flagResult);
        self::assertIsString($flagResult['big']);
    }

    public function testInvalidConstructorArgumentsThrow(): void
    {
        $this->expectException(ValueError::class);

        new JsonField(depth: 0); /* @phpstan-ignore-line */
    }

    public function test_metadata_contains_expected_structure(): void
    {
        $field = new JsonField(depth: 2, flags: JSON_BIGINT_AS_STRING);

        self::assertSame([
            'flags' => JSON_BIGINT_AS_STRING,
            'depth' => 2,
        ], $field->metadata()->all());
    }
}
