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

namespace League\Csv;

use PHPUnit\Framework\TestCase;

final class FragmentFinderTest extends TestCase
{
    public function test_returning_the_first_found_match(): void
    {
        $reader = Reader::fromString("a,b\nc,d\n");
        $finder = new FragmentFinder();
        $found = $finder->findFirst('cell=3,1-2,2;1,1', $reader);
        self::assertInstanceOf(TabularDataReader::class, $found);

        self::assertSame(['a'], $found->first());
    }
}
