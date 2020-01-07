<?php

/**
 * League.Csv (https://csv.thephpleague.com)
 *
 * (c) Ignace Nyamagana Butera <nyamsprod@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace LeagueTest\Csv;

use League\Csv\CannotInsertRecord;
use League\Csv\Exception as BaseException;
use League\Csv\InvalidArgument;
use League\Csv\SyntaxError;
use League\Csv\UnavailableFeature;
use PHPUnit\Framework\TestCase;

class ExceptionsTest extends TestCase
{
    public function testExceptionsExtendBaseClass(): void
    {
        self::assertInstanceOf(BaseException::class, new UnavailableFeature());
        self::assertInstanceOf(BaseException::class, new CannotInsertRecord());
        self::assertInstanceOf(BaseException::class, new InvalidArgument());
        self::assertInstanceOf(BaseException::class, new SyntaxError());
    }
}
