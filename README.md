Bakame.csv
==========
[![Build Status](https://travis-ci.org/nyamsprod/Bakame.csv.png?branch=master)](https://travis-ci.org/nyamsprod/Bakame.csv)
[![Scrutinizer Quality Score](https://scrutinizer-ci.com/g/nyamsprod/Bakame.csv/badges/quality-score.png?s=e4619fba277f07a7a81e057756a51791d19abdf2)](https://scrutinizer-ci.com/g/nyamsprod/Bakame.csv/)
[![Code Coverage](https://scrutinizer-ci.com/g/nyamsprod/Bakame.csv/badges/coverage.png?s=7ad9740c0ed5fd5d389abfe92d7af04d7f4f542e)](https://scrutinizer-ci.com/g/nyamsprod/Bakame.csv/)


A simple wrapper to parse and save csv files in PHP 5.4+

This package is compliant with [PSR-0][], [PSR-1][], and [PSR-2][].

[PSR-0]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-0.md
[PSR-1]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-1-basic-coding-standard.md
[PSR-2]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-2-coding-style-guide.md

Install
-------

You may install the Bakame Url package with Composer (recommended) or manually.

```json
{
    "require": {
        "bakame/csv": "1.*"
    }
}
```


System Requirements
-------

You need **PHP >= 5.4.0** and the `mbstring` extension to use `Bakame\Csv` but the latest stable version of PHP is recommended.

Instantiation
-------

The easiest way to get started is to add `'/path/to/Bakame/Csv/src'` to your PSR-0 compliant Autoloader. Once added to the autoloader you can start manipulating CSV files as explain below:

Usage
-------

The wrapper serves 2 main functions loading and saving data in CSV formats. To do that, the wrapper always returns a `SplFileObject` instance with flags set to `SplFileObject::READ_CSV` so that you can use it to further manipulate your data.
Of course, since a `SplFileObject` is returned you are free to modify the flags to satisfy your application needs.

```php
<?php


use Bakame\Csv\Wrapper;

$csv = new Wrapper;
$csv->setDelimeter(',');
$csv->setEnclosure('"');
$csv->setEscape('\\');

$file = $csv->loadFile('path/to/my/csv/file.csv');
//returns a SplFileObject object you can use to iterate throught your CSV data

$file = $csv->loadString(['foo,bar,baz', ['foo', 'bar', 'baz']]);
//returns a SplTempFileObject object you can use to iterate throught your CSV data

```

To Save your data you can use the `save` method as shown below. 
The method accepts:
* an `array` of data
* any object that implements the `Traversable` interface.

The path to where the data must saved can be given as:
* a simple string
* an `SplFileInfo` instance

If for any reason the 

```php

$file = $csv->save([1,2,3,4], '/path/to/my/saved/csv/file.csv');
//returns a SplFileObject object to manage the newly saved data

```

Testing
-------

``` bash
$ phpunit
```

Contributing
-------

Please see [CONTRIBUTING](https://github.com/nyamsprod/Bakame.csv/blob/master/CONTRIBUTING.md) for details.

Credits
-------

- [ignace nyamagana butera](https://github.com/nyamsprod)