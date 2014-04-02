CSV
==========

[![Build Status](https://travis-ci.org/thephpleague/csv.png?branch=master)](https://travis-ci.org/thephpleague/csv)
[![Coverage Status](https://coveralls.io/repos/thephpleague/csv/badge.png)](https://coveralls.io/r/thephpleague/csv)
[![Total Downloads](https://poser.pugx.org/league/csv/downloads.png)](https://packagist.org/packages/league/csv)
[![Latest Stable Version](https://poser.pugx.org/league/csv/v/stable.png)](https://packagist.org/packages/league/csv)

League\Csv is a simple library to ease CSV parsing, writing and filtering in
PHP. The goal of the library is to be as powerful while remaining lightweight,
by utilizing PHP native classes whenever possible.

League\Csv was designed for developers who wants to deal with CSV data using
modern code and without the high levels of bootstrap and low-levels of
usefulness provided by existing core functions or third party-code.

This package is compliant with [PSR-1], [PSR-2], and [PSR-4].

[PSR-1]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-1-basic-coding-standard.md
[PSR-2]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-2-coding-style-guide.md
[PSR-4]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-4-autoloader.md


System Requirements
-------

You need **PHP >= 5.4.0** and the `mbstring` extension to use `League\Csv` but the latest stable version of PHP is recommended.

Install
-------

Install the `csv` package with Composer.

```json
{
    "require": {
        "league/csv": "5.*"
    }
}
```

Documentation
-------------

Csv has [full documentation](http://csv.thephpleague.com), powered by [Sculpin](https://sculpin.io).

Contribute to this documentation in the [sculpin branch](https://github.com/thephpleague/csv/tree/sculpin/source).

### Tips

#### Writing to a CSV data

* When creating a file using the library, first insert all the data that need to be inserted before starting manipulating the CSV. If you manipulate your data before insertion, you may change the file cursor position and get unexpected results.

* When merging multiples CSV documents don't forget to set the main CSV object
 as a `League\Csv\Writer` object with the `$open_mode = 'a+'` to preserve its content.
 This setting is of course not required when your main `League\Csv\Writer` object is 
 created from String

* Even thought you can iterate using the `Writer` class it is recommend to iterate over you CSV using the `Reader` class set with a read only class to avoid any issue.

#### CSV encoding charset

The library assumes that your data is UTF-8 encoded. If your are dealing with non-unicode data:

* Specify the encoding parameter using the `setEncoding` method otherwise your output conversions may no work.

* If you have your `LC_CTYPE` set to a locale that's using UTF-8, PHP will cut your fields the moment it encounters a byte it can't understand (i.e. any outside of ASCII that doesn't happen to be part of a UTF-8 character which it likely isn't). To resolve this issue you can use [PHP stream filter](http://www.php.net/manual/en/stream.filters.php) as shown [in the following gist](https://gist.github.com/nyamsprod/9932158). This tip is from [Philip Hofstetter](https://github.com/pilif)

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

[![Bitdeli Badge](https://d2weczhvl823v0.cloudfront.net/thephpleague/csv/trend.png)](https://bitdeli.com/free "Bitdeli Badge")
