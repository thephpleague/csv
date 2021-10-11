---
layout: default
title: Installation
---

# Installation

## System Requirements

You need:

- the `mbstring` extension
- at least **PHP >= 7.0.10** for version **9.0.0**
- at least **PHP >= 7.2.5** for version **9.6.0**
- at least **PHP >= 7.3** for version **9.7.0**

If you are using **PHP >= 8.0**, please use at least version **9.6.2** for compatibility.

**Of note:** It is recommended to use the latest stable version of the package in
combination with the latest PHP version.

## Composer

`Csv` is available on [Packagist](https://packagist.org/packages/league/csv) and can be installed using [Composer](https://getcomposer.org/):

```bash
composer require league/csv:^9.0
```

## Going Solo

You can also use `League\Csv` without using Composer by downloading the library on Github.

- Visit [the releases page](https://github.com/thephpleague/csv/releases);
- Select the version you want
- click the Source Code download link in your preferred compress format;

The library is compatible with any [PSR-4](http://www.php-fig.org/psr/psr-4/) compatible autoloader.

Also, `League\Csv` comes bundle with its own autoloader script `autoload.php` located in the root directory.

```php
use League\Csv\Reader;
use League\Csv\Writer;

require '/path/to/league/csv/autoload.php';

//your script starts here
```

where `path/to/league/csv` represents the path where the library was extracted.
