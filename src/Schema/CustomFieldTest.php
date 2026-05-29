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
use PHPUnit\Framework\TestCase;

#[CoversClass(CallbackFieldParser::class)]
#[CoversClass(CustomField::class)]
final class CustomFieldTest extends TestCase
{
    // --------------------------------------------------------
    // parse()
    // --------------------------------------------------------

    public function testParseUsesClosure(): void
    {
        $field = new CustomField(
            fn ($value) => 'ok' === $value ? 'parsed' : null,
            'custom',
        );

        self::assertSame('parsed', $field->parse('ok'));
        self::assertNull($field->parse('nope'));
    }

    public function testParseUsesCallable(): void
    {
        $callable = function ($value) {
            return is_int($value) ? $value * 2 : null;
        };

        $field = new CustomField($callable, 'custom');

        self::assertSame(4, $field->parse(2));
        self::assertNull($field->parse('2'));
    }

    // --------------------------------------------------------
    // evaluate() (inherited behavior)
    // --------------------------------------------------------

    public function testEvaluateUsesParse(): void
    {
        $field = new CustomField(
            fn ($value) => 'valid' === $value ? true : null,
            'custom'
        );

        self::assertSame(1, $field->evaluate('valid'));
        self::assertSame(-1, $field->evaluate('invalid'));
        self::assertSame(0, $field->evaluate(null));
        self::assertSame(0, $field->evaluate(''));
    }

    // --------------------------------------------------------
    // score()
    // --------------------------------------------------------

    // --------------------------------------------------------
    // type()
    // --------------------------------------------------------

    public function testTypeIsCustom(): void
    {
        $field = new CustomField(fn () => null, 'custom');

        self::assertSame(FieldType::Custom, $field->type());
    }

    // --------------------------------------------------------
    // confidenceThreshold()
    // --------------------------------------------------------

    public function testConfidenceThresholdIsInherited(): void
    {
        $field = new CustomField(fn () => null, 'custom', 0.8);

        self::assertSame(0.8, $field->confidenceThreshold());
    }

    public function test_metadata_contains_expected_structure(): void
    {
        $field = new BooleanField();

        self::assertTrue($field->metadata()->isEmpty());
    }
}
