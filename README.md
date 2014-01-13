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
        "bakame/csv": "3.*"
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

### The Codec Class.

The `Codec` class enable loading and saving data in CSV formats. The class returns a `Reader` class to use to further manipulate your data.

```php
<?php

use Bakame\Csv\Codec;

$codec = new Codec;
$codec->setDelimeter(',');
$codec->setEnclosure('"');
$codec->setEscape('\\');
```

#### `Codec::loadFile` and `Codec::loadString`

Depending on your CSV source you may choose `Codec::loadFile` method or `Codec::loadString` to enable reading your CSV. Whatever source you chose both methods will return a `Bakame\Csv\Reader` object to help you manipulate your data.

```php
$csv = $codec->loadFile('path/to/my/csv/file.csv');
//$csv is a Reader object

$csv = $codec->loadString(['foo,bar,baz', ['foo', 'bar', 'baz']]);
//$csv is a Reader object

```

#### `Codec::save`

This methods help you save you CSV data. 
It accepts:

* an `array` of data
* any object that implements the `Traversable` interface.

The path to where the data must saved can be given as:
* a simple string
* an `SplFileInfo` instance

If the data is invalid or the file does not exist or is not writable an `InvalidArgumentException` will be thrown by the class. 
Just like the loading methods, the `Codec::save` returns a `Bakame\Csv\Reader` object.

```php

$csv = $codec->save([1,2,3,4], '/path/to/my/saved/csv/file.csv');
//returns a Reader object

```

### The Reader Class


The `Reader` facilitates manipulating CSV data that are stored in a `SplFileObject` object.
To instantiate the class you must provide at leat a `SplFileObject` object like below:

```php

use Bakame\Csv\Reader;

$csv = new Reader(new \SpliFileObject('/path/to/your/csv/file.csv'));
$csv->setDelimeter(',');
$csv->setEnclosure('"');
$csv->setEscape('\\');

```
You can optionally set CSV delimiter, enclosure and/or escape characters.

The `Bakame\Csv\Reader` object let you access the `SplFileObject` used to instantiate it when using the method `Reader::getFile`. This method comes handy if, for instance, you want to download your data. But it also exposes several fetching methods to help you easily extract you CSV data:

#### `Reader::fetchAll` 

This methods returns a sequentials array of all CSV rows.

```php
$data = $csv->fetchAll();
// will return something like this :
// 
// [ 
//   ['john', 'doe', 'john.doe@example.com'],
//   ['jane', 'doe', 'jane.doe@example.com'],
//   ...
// ]
//
```
This method can take an optional callable variable to further manipulate each row before being returned. This callable expected an array as its sole argument.

```php
$data = $csv->fetchAll(function ($value) {
	return array_map('strtoupper', $value);
});
// will return something like this :
// 
// [ 
//   ['JOHN', 'DOE', 'JOHN.DOE@EXAMPLE.COM'],
//   ['JANE', 'DOE', 'JANE.DOE@EXAMPLE.COM'],
//   ...
// ]
//
```

#### `Reader::fetchAssoc` 

This method returns a sequentials array of all CSV rows. the rows are associative arrays where the key are given to the method using a array.

**Of Note:** 
* If the number of values in a CSV row is lesser than the number of named keys, the method will add `null` values to compensate for the missing values.
* If the number of values in a CSV row is greater that the number of named keys the exceeding values will be drop from the result set.

```php
$data = $csv->fetchAssoc(['firstname', 'lastname', 'email']);
// will return something like this :
// 
// [ 
//   ['firstname' => 'john', 'lastname' => 'doe', 'email' => 'john.doe@example.com'],
//   ['firstname' => 'jane', 'lastname' => 'doe', 'email' => 'jane.doe@example.com'],
//   ...
// ]
// 
```
This method can take an optional callable variable to further manipulate each row before being returned. This callable expected an array as its sole argument.

```php
$data = $csv->fetchAssoc(['firstname', 'lastname', 'email'], function ($value) {
	return array_map('strtoupper', $value);
});
// will return something like this :
// 
// [ 
//   ['firstname' => 'JOHN', 'lastname' => 'DOE', 'email' => 'JOHN.DOE@EXAMPLE.COM'],
//   ['firstname' => 'JANE', 'lastname' => 'DOE', 'email' => 'JANE.DOE@EXAMPLE.COM'],
//   ...
// ]
//
```

#### `Reader::fetchCol`

This method returns an sequentials array for a given CSV column.

```php
$data = $csv->fetchCol(2);
// will return something like this :
// 
// ['john.doe@example.com', 'jane.doe@example.com', ...]
// 
```
This method can take an optional callable variable to further manipulate each value before being returned. This callable expected an array as its sole argument.

```php
$data = $csv->fetchCol(2, function ($value) {
	return array_map('strtoupper', $value);
});
// will return something like this :
// 
// ['JOHN.DOE@EXAMPLE.COM', 'JANE.DOE@EXAMPLE.COM', ...]
//
```

#### `Reader::fetchOne`

This method returns an array representing one CSV row given the row Index. the index starts at 0.

```php
$data = $csv->fetchOne(1);
// will return something like this :
// 
// ['jane', 'doe', 'jane.doe@example.com']
// 
```

#### `Reader::fetchValue`

This method returns the value of a given field in a given row. If the value is not found it will return null.
The first argument represents the row and the second represents the column index. the 2 indexes starts at 0;
```php
$data = $csv->fetchValue(1, 2);
// will return something like this :
// 
// 'jane.doe@example.com'
// 
```

#### `Reader::setFlags`

Sometimes you may wish to change the SplFileObject Flags. You can do so using the following method:

```php
$csv->setFlags(SplFileObject::READ_AHEAD|SplFileObject::SKIP_EMPTY);
```

It is important to used the reader method and **not** the file method as the `Reader` will always append the `SplFileObject::READ_CSV` flag.


#### `Reader::__toString`

This method returns the CSV content as it is written in the file.

```php
echo $csv; // or $csv->__toString();
// will return something like this :
// 
// john,doe,john.doe@example.com
// jane,doe,jane.doe@example.com
// 
// 
```

#### `Reader::output`

The output method returns to the output buffer the CSV content. This method can be use if you want the CSV to be downloaded by your user.


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
