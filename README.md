CSV
=====

[![Latest Version](https://img.shields.io/github/release/thephpleague/csv.svg?style=flat-square)](https://github.com/thephpleague/csv/releases)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
[![Build Status](https://img.shields.io/travis/thephpleague/csv/master.svg?style=flat-square)](https://travis-ci.org/thephpleague/csv)
[![Coverage Status](https://img.shields.io/scrutinizer/coverage/g/thephpleague/csv.svg?style=flat-square)](https://scrutinizer-ci.com/g/thephpleague/csv/code-structure)
[![Quality Score](https://img.shields.io/scrutinizer/g/thephpleague/csv.svg?style=flat-square)](https://scrutinizer-ci.com/g/thephpleague/csv)
[![Total Downloads](https://img.shields.io/packagist/dt/league/csv.svg?style=flat-square)](https://packagist.org/packages/league/csv)

Csv is a simple library to ease CSV parsing, writing and filtering in
PHP. The goal of the library is to be powerful while remaining lightweight,
by utilizing PHP native classes whenever possible.

Highlights
-------

* Simple API
* Read and Write to CSV documents in a memory efficient and scalable way
* Use SPL to interact with the CSV documents
* Support PHP Stream filtering capabilities
* Transform CSV documents into popular format (JSON, XML or HTML)
* Fully documented
* Fully unit tested
* Framework-agnostic
* Composer ready, [PSR-2] and [PSR-4] compliant

Documentation
-------

Full documentation can be found at [csv.thephpleague.com](http://csv.thephpleague.com). Contribute to this documentation in the [gh-pages branch](https://github.com/thephpleague/csv/tree/gh-pages)

System Requirements
-------

You need **PHP >= 5.5.0** and the `mbstring` extension to use `Csv` but the latest stable version of PHP is recommended.

Install
-------

Install `Csv` using Composer.

```
$ composer require league/csv
```

Configuration
-------

**Warning:** If your CSV document was created or is read on a Macintosh computer, add the following lines before using the library to help [PHP detect line ending](http://php.net/manual/en/function.fgetcsv.php#refsect1-function.fgetcsv-returnvalues).

```php
if (!ini_get("auto_detect_line_endings")) {
    ini_set("auto_detect_line_endings", '1');
}
```

Testing
-------

`Csv` has a [PHPUnit](https://phpunit.de) test suite and a coding style compliance test suite using [PHP CS Fixer](http://cs.sensiolabs.org/). To run the tests, run the following command from the project folder.

``` bash
$ composer test
```

Contributing
-------

Contributions are welcome and will be fully credited. Please see [CONTRIBUTING](.github/CONTRIBUTING.md) and [CONDUCT](CONDUCT.md) for details.

Security
-------

If you discover any security related issues, please email nyamsprod@gmail.com instead of using the issue tracker.

Credits
-------

- [ignace nyamagana butera](https://github.com/nyamsprod)
- [All Contributors](https://github.com/thephpleague/csv/graphs/contributors)

License
-------

The MIT License (MIT). Please see [LICENSE](LICENSE) for more information.

[PSR-2]: http://www.php-fig.org/psr/psr-2/
[PSR-4]: http://www.php-fig.org/psr/psr-4/
