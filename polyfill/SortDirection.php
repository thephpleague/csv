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

if (PHP_VERSION_ID < 80600 && !enum_exists('SortDirection', false)) {
    enum SortDirection
    {
        case Ascending;
        case Descending;
    }
}
