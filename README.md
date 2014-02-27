Bakame.csv
==========
[![Build Status](https://travis-ci.org/thephpleague/csv.png?branch=master)](https://travis-ci.org/thephpleague/csv)
[![Code Coverage](https://scrutinizer-ci.com/g/thephpleague/csv/badges/coverage.png?s=7ad9740c0ed5fd5d389abfe92d7af04d7f4f542e)](https://scrutinizer-ci.com/g/thephpleague/csv/)

A simple library to easily load, manipulate and save CSV files in PHP 5.4+

This package is compliant with [PSR-1], [PSR-2], and [PSR-4].

[PSR-1]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-1-basic-coding-standard.md
[PSR-2]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-2-coding-style-guide.md
[PSR-4]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-4-autoloader.md


System Requirements
-------

You need **PHP >= 5.4.0** and the `mbstring` extension to use `Bakame\Csv` but the latest stable version of PHP is recommended.

Install
-------

Install the `csv` package with Composer.

```json
{
    "require": {
        "league/csv": "4.*"
    }
}
```

Usage
-------

* [Selecting specific rows in the CSV](examples/extract.php)
* [Filtering a CSV](examples/filtering.php)
* [Creating a CSV](examples/writing.php)
* [Merging 2 CSV documents](examples/merge.php)
* [Switching between modes from Writer to Reader mode](examples/switchmode.php)
* [Downloading the CSV](examples/download.php)
* [Converting the CSV into a Json String](examples/json.php)
* [Converting the CSV into a XML file](examples/xml.php)
* [Converting the CSV into a HTML Table](examples/table.php)

> The CSV data use for the examples are taken from [Paris Opendata](http://opendata.paris.fr/opendata/jsp/site/Portal.jsp?document_id=60&portlet_id=121)

### Tips

* When creating a file using the library, first insert all the data that need to be inserted before starting manipulating the CSV. If you manipulate your data before insertion, you may change the file cursor position and get unexpected results.

* If you are dealing with non-unicode data, specify the encoding parameter using the `setEncoding` method otherwise your output conversions may no work.

* If you have your LC_CTYPE set to a locale that's using UTF-8 and you try to parse a file that's not in UTF-8, PHP will cut your fields the moment it encounters a byte it can't understand (i.e. any outside of ASCII that doesn't happen to be part of a UTF-8 character which it likely isn't). [This gist will show you a possible solution](https://gist.github.com/pilif/9137146) to this problem by using [PHP stream filter](http://www.php.net/manual/en/stream.filters.php). This tip is from [Philip Hofstetter](https://github.com/pilif)

* When merging multiples CSV documents don't forget to set the main CSV object
 as a `Bakame\Csv\Writer` object with the `$open_mode = 'a+'` to preserve its content.
 This setting is of course not required when your main `Bakame\Csv\Writer` object is 
 created from String

* **If you are on a Mac OS X Server**, add the following lines before using the library to help [PHP detect line ending in Mac OS X](http://php.net/manual/en/function.fgetcsv.php#refsect1-function.fgetcsv-returnvalues).

```php
if (! ini_get("auto_detect_line_endings")) {
    ini_set("auto_detect_line_endings", true);
}
```

Documentation
-------------

Fractal has [full documentation](http://csv.thephpleague.com), powered by [Sculpin](https://sculpin.io).

Contribute to this documentation in the [sculpin branch](https://github.com/thephpleague/csv/tree/sculpin/source).

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
- [All Contributors](graphs/contributors)

[![Bitdeli Badge](https://d2weczhvl823v0.cloudfront.net/thephpleague/csv/trend.png)](https://bitdeli.com/free "Bitdeli Badge")
