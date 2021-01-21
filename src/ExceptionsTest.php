<?php

/**
 * League.Csv (https://csv.thephpleague.com)
 *
 * (c) Ignace Nyamagana Butera <nyamsprod@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace League\Csv;

use PHPUnit\Framework\TestCase;

final class ExceptionsTest extends TestCase
{
    public function testExceptionsExtendBaseClass(): void
    {
        self::assertInstanceOf(Exception::class, new UnavailableFeature());
        self::assertInstanceOf(Exception::class, new CannotInsertRecord());
        self::assertInstanceOf(Exception::class, new InvalidArgument());
        self::assertInstanceOf(Exception::class, new SyntaxError());
        self::assertInstanceOf(Exception::class, new UnavailableStream());
    }
}
