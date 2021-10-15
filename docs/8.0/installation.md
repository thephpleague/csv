---
layout: default
title: Installation
redirect_from: /installation/
---

# Installation

## System Requirements

You need **PHP >= 5.5.0** and the `mbstring` extension to use `Csv` but the latest stable version of PHP is recommended.

## Composer

`Csv` is available on [Packagist](https://packagist.org/packages/league/csv) and can be installed using [Composer](https://getcomposer.org/):

```bash
composer require league/csv:^8.0
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
