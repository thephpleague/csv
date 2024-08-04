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

namespace League\Csv\Fragment;

use League\Csv\FragmentNotFound;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ExpressionTest extends TestCase
{
    #[Test]
    #[DataProvider('validExpressionProvider')]
    public function it_can_generate_an_expression_from_a_string(string $input, string $expected): void
    {
        self::assertSame($expected, Expression::from($input)->toString());
    }

    public static function validExpressionProvider(): iterable
    {
        yield 'single row' => [
            'input' => 'ROW=1',
            'expected' => 'row=1',
        ];

        yield 'row range' => [
            'input' => 'row=1-5',
            'expected' => 'row=1-5',
        ];

        yield 'row infinite range' => [
            'input' => 'row=12-*',
            'expected' => 'row=12-*',
        ];

        yield 'multiple row selections' => [
            'input' => 'row=1-5;12-*',
            'expected' => 'row=1-5;12-*',
        ];

        yield 'single column' => [
            'input' => 'CoL=1',
            'expected' => 'col=1',
        ];

        yield 'column range' => [
            'input' => 'col=12-24',
            'expected' => 'col=12-24',
        ];

        yield 'column infinite range' => [
            'input' => 'col=12-*',
            'expected' => 'col=12-*',
        ];

        yield 'multiple column selections' => [
            'input' => 'col=1-5;12-*',
            'expected' => 'col=1-5;12-*',
        ];

        yield 'single cell' => [
            'input' => 'CeLl=1,4',
            'expected' => 'cell=1,4',
        ];

        yield 'cell range' => [
            'input' => 'CeLl=1,4-5,9',
            'expected' => 'cell=1,4-5,9',
        ];

        yield 'cell range with infinite' => [
            'input' => 'CeLl=1,4-*',
            'expected' => 'cell=1,4-*',
        ];

        yield 'multiple cell selection' => [
            'input' => 'CeLl=1,4-5,9;12,15-*',
            'expected' => 'cell=1,4-5,9;12,15-*',
        ];
    }

    #[Test]
    #[DataProvider('invalidExpressionProvider')]
    public function it_will_throw_parsing_incorrect_expression(string $expression): void
    {
        $this->expectException(FragmentNotFound::class);

        Expression::from($expression)->toString();
    }

    public static function invalidExpressionProvider(): iterable
    {
        yield 'invalid row index' => ['expression' => 'row=-1'];
        yield 'invalid row end' => ['expression' => 'row=1--1'];
        yield 'invalid row number' => ['expression' => 'row=1-four'];
        yield 'invalid row range infinite' => ['expression' => 'row=*-1'];
        yield 'invalid multiple row range' => ['expression' => 'row=1-4,2-5'];

        yield 'invalid column index' => ['expression' => 'col=-1'];
        yield 'invalid column end' => ['expression' => 'col=1--1'];
        yield 'invalid column number' => ['expression' => 'col=1-four'];
        yield 'invalid column range infinite' => ['expression' => 'col=*-1'];
        yield 'invalid multiple column range' => ['expression' => 'col=1-4,2-5'];

        yield 'invalid cell' => ['expression' => 'cell=1,*'];
        yield 'invalid cell index' => ['expression' => 'cell=1,-3'];
        yield 'invalid cell number' => ['expression' => 'cell=1,three'];
    }

    #[Test]
    #[DataProvider('ignoreExpressionProvider')]
    public function it_will_fail_parsing_incorrect_expression(string $expression): void
    {
        $this->expectException(FragmentNotFound::class);

        Expression::from($expression);
    }

    public static function ignoreExpressionProvider(): iterable
    {
        yield 'invalid multiple cell selection' => ['expression' => 'cell=2,3-14,16;22-23'];
    }

    #[Test]
    public function it_can_add_remove_selections(): void
    {
        $expression = Expression::fromColumn();
        self::assertCount(0, $expression);

        $addExpression = $expression
            ->push( '12-*')
            ->unshift('1-5');

        $removeExpression = $addExpression->remove('12-*');
        $replaceExpression = $addExpression->replace('12-*', '8-9');

        self::assertCount(0, $expression);
        self::assertCount(2, $addExpression);
        self::assertCount(2, $replaceExpression);
        self::assertCount(1, $removeExpression);
        self::assertSame($addExpression->get(-1), $replaceExpression->get(-1));

        self::assertSame($expression, $expression->push());
        self::assertSame($expression, $expression->unshift());
        self::assertSame($expression, $expression->remove());
        self::assertFalse($expression->has('12-*'));

        self::assertSame($addExpression, $addExpression->push());
        self::assertSame($addExpression, $addExpression->unshift());
        self::assertSame($addExpression, $addExpression->remove());
        self::assertSame($addExpression, $addExpression->replace($addExpression->get(0), $addExpression->get(0)));

        self::assertEquals('12-*', $addExpression->get(1));
        self::assertEquals('12-*', $addExpression->get(-1));
        self::assertTrue($addExpression->has('12-*'));

        self::assertFalse($removeExpression->hasKey(1));
        self::assertFalse($removeExpression->has('12-*'));
        self::assertTrue($removeExpression->hasKey(0));
        self::assertEquals('1-5', $removeExpression->get(0));
        self::assertEquals('1-5', $addExpression->get(0));

        $this->expectException(FragmentNotFound::class);
        $removeExpression->get(42);
    }

    #[Test]
    public function it_can_ignore_all_selections(): void
    {
        self::assertSame('row=', Expression::fromRow('7-5')->toString());
        self::assertSame('row=', Expression::fromRow()->toString());
        self::assertSame('row=', Expression::from('row=')->toString());
        self::assertTrue(Expression::from('row=')->isEmpty());
        self::assertSame(Type::Row, Expression::from('row=')->type());

        self::assertSame('cell=', Expression::fromCell('2,3-1,2')->toString());
        self::assertSame('cell=', Expression::fromCell()->toString());
        self::assertSame('cell=', Expression::from('cell=')->toString());
        self::assertSame(Type::Cell, Expression::from('cell=')->type());

        self::assertSame('col=', Expression::fromColumn('7-5')->toString());
        self::assertSame('col=', Expression::fromColumn()->toString());
        self::assertSame('col=', Expression::from('col=')->toString());
        self::assertSame(Type::Column, Expression::from('col=')->type());

    }
}
