CSV
==========

[![Latest Version](https://img.shields.io/github/release/thephpleague/csv.svg?style=flat-square)](https://github.com/thephpleague/csv/releases)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
[![Build Status](https://img.shields.io/travis/thephpleague/csv/master.svg?style=flat-square)](https://travis-ci.org/thephpleague/csv)
[![Coverage Status](https://img.shields.io/scrutinizer/coverage/g/thephpleague/csv.svg?style=flat-square)](https://scrutinizer-ci.com/g/thephpleague/csv/code-structure)
[![Quality Score](https://img.shields.io/scrutinizer/g/thephpleague/csv.svg?style=flat-square)](https://scrutinizer-ci.com/g/thephpleague/csv)
[![Total Downloads](https://img.shields.io/packagist/dt/league/csv.svg?style=flat-square)](https://packagist.org/packages/league/csv)

League\Csv is a simple library to ease CSV parsing, writing and filtering in
PHP. The goal of the library is to be powerful while remaining lightweight,
by utilizing PHP native classes whenever possible.

League\Csv was designed for developers who want to deal with CSV data using
modern code and without the high levels of bootstrap and low-levels of
usefulness provided by existing core functions or third party-code.

This package is compliant with [PSR-2] and [PSR-4].

[PSR-2]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-2-coding-style-guide.md
[PSR-4]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-4-autoloader.md


System Requirements
-------

You need **PHP >= 5.4.0** or **HHVM >= 3.2** and the `mbstring` extension to use `League\Csv` but the latest stable version of PHP/HHVM is recommended.

Install
-------

Install `League\Csv` using Composer.

```
composer require league/csv
```

This will edit (or create) your `composer.json` file and automatically choose the most recent version, for example: `~6.0`

#### Going Solo

You can also use `League\Csv` without using Composer by downloading the library and using a [PSR-4](http://www.php-fig.org/psr/psr-4/) compatible autoloader.


Documentation
-------------

`League\Csv` is [fully documented](http://csv.thephpleague.com). Contribute to this documentation in the [gh-pages branch](https://github.com/thephpleague/csv/tree/gh-pages)

### Tips

* When creating or editing a document using `League\Csv\Writer`, first insert all the data that needs to be inserted before anything else. If you manipulate your data before insertion, you may change the file cursor position and get unexpected results.

* Even though you can iterate over you document using `League\Csv\Writer` class, **it is recommend and best practice** to iterate over you CSV using the `League\Csv\Reader` class to avoid any issue.

* The library assumes that your data is UTF-8 encoded. If your are dealing with non-unicode data you **must** enable your data conversion into UTF-8 otherwise output methods will fail. You can transcode your CSV :
    * using the library [stream filtering methods](http://csv.thephpleague.com/filtering);
    * or by setting the source [encoding charset](http://csv.thephpleague.com/overview);

Testing
-------

``` bash
$ phpunit
```

Contributing
-------

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

Credits
-------

- [ignace nyamagana butera](https://github.com/nyamsprod)
- [All Contributors](https://github.com/thephpleague/csv/graphs/contributors)
