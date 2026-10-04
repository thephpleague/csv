---
layout: default
title: Installing the CSV package
description: How to install the package in your codebase
---

# Installation

## System Requirements

To install `League\Csv` you will need:

- the `mbstring` extension enabled in PHP
- at least **PHP >= 7.0.10** for version **9.0.0**
- at least **PHP >= 7.2.5** for version **9.6.0**
- at least **PHP >= 7.3** for version **9.7.0**
- at least **PHP >= 7.4** for version **9.8.0**

If you are using **PHP >= 8.0**, use version **9.6.2 or higher** for compatibility.

**Recommendation:** It is recommended to use the latest stable version of the `League\Csv` package in
combination with the latest PHP version.

<p class="message-notice">Since version <code>9.16.0</code> the <code>mbstring</code> extension is no
longer required but it is still recommended if you are using or plan to use any mb related stream filter</p>

## Composer Install

`League\Csv` is available on [Packagist](https://packagist.org/packages/league/csv) and can be installed using [Composer](https://getcomposer.org/):

```bash
composer require league/csv:^{{ site.data.project.version }}
```

### `SortDirection` and Composer

`league/csv` includes a small `SortDirection` polyfill because the library is designed to work both with and without Composer.

When `league/csv` is installed through Composer alongside `symfony/polyfill-php86`, Composer may report:

```text
Warning: Ambiguous class resolution, "SortDirection" was found in both
".../symfony/polyfill-php86/Resources/stubs/SortDirection.php" and
".../league/csv/polyfill/SortDirection.php", the first will be used.
```

This warning is harmless.

The two files contain the same `SortDirection` implementation. Furthermore, the League CSV polyfill only declares the enum when running on PHP versions where it is not provided by PHP:

```php
if (PHP_VERSION_ID < 80600 && !enum_exists('SortDirection', false)) {
    enum SortDirection
    {
        case Ascending;
        case Descending;
    }
}
```

Therefore, on PHP 8.6 and later, there is no conflict between two active declarations. The League CSV polyfill is simply a no-op.

No configuration or action is required.

## Manual Install

You can also use `League\Csv` without using Composer by downloading the library on Github.

1. Visit [the releases page](https://github.com/thephpleague/csv/releases) of the project.
2. Find the release of `League\Csv` for your version of PHP.
3. Click the **Source Code** link for preferred compression format.

The library is compatible with any [PSR-4](http://www.php-fig.org/psr/psr-4/) compatible autoloader.

Also, `League\Csv` comes bundled with its own autoloader script `autoload.php` located in the root directory.

```php
use League\Csv\Reader;
use League\Csv\Writer;

require '/path/to/league/csv/autoload.php';

// Your script starts here
// ...
```

where `path/to/league/csv` represents the path where the library was extracted.
