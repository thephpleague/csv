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
        "bakame/csv": "2.*"
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

The `Codec` class serves 2 main functions loading and saving data in CSV formats. To do that, the class always returns a `Reader` class to use to further manipulate your data.

```php
<?php

use Bakame\Csv\Codec;

$codec = new Codec;
$codec->setDelimeter(',');
$codec->setEnclosure('"');
$codec->setEscape('\\');

$reader = $codec->loadFile('path/to/my/csv/file.csv');
//returns a Reader object

$reader = $codec->loadString(['foo,bar,baz', ['foo', 'bar', 'baz']]);
//returns a Reader object

```

To Save your data you can use the `save` method as shown below. 
The method accepts:
* an `array` of data
* any object that implements the `Traversable` interface.

The path to where the data must saved can be given as:
* a simple string
* an `SplFileInfo` instance

If for any reason the data or the file does not exist an `InvalidArgumentException` will be thrown by the class

```php

$reader = $codec->save([1,2,3,4], '/path/to/my/saved/csv/file.csv');
//returns a Reader object

```

### The Reader Class


The `Reader` main job is to facilitate manipulating CSV data that are stored in a `SplFileObject` object.
To instantiate the class you must provide at leat a `SplFileObject` object like below:

```php

use Bakame\Csv\Reader;

$file = new \SpliFileObject('/path/to/your/csv/file.csv');
$reader = new Reader($file, $delimiter, $enclosure, $escape);

```
You can optionally set CSV delimiter, enclosure and/or escape characters.


The class comes with several fetching methods to help you deal with your data:

#### `Reader::fetchAll` 

This methods returns a sequentials array of all CSV rows.

```php
$data = $reader->fetchAll();
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
$data = $reader->fetchAll(function ($value) {
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

```php
$data = $reader->fetchAssoc(['firstname', 'lastname', 'email']);
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
$data = $reader->fetchAssoc(['firstname', 'lastname', 'email'], function ($value) {
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
$data = $reader->fetchCol(2);
// will return something like this :
// 
// ['john.doe@example.com', 'jane.doe@example.com', ...]
// 
```
This method can take an optional callable variable to further manipulate each value before being returned. This callable expected an array as its sole argument.

```php
$data = $reader->fetchCol(2, function ($value) {
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
$data = $reader->fetchOne(1);
// will return something like this :
// 
// ['jane', 'doe', 'jane.doe@example.com']
// 
```

#### `Reader::fetchValue`

This method returns the value of a given field in a given row. If the value is not found it will return null.
The first argument represents the row and the second represents the column index. the 2 indexes starts at 0;
```php
$data = $reader->fetchValue(1, 2);
// will return something like this :
// 
// 'jane.doe@example.com'
// 
```

#### `Reader::setFlags`

Sometimes you may wish to change the SplFileObject Flags. You can do so using the following method:

```php
$reader->setFlags(SplFileObject::READ_AHEAD|SplFileObject::SKIP_EMPTY);
```

It is important to used the reader method and **not** the file method as the `Reader` will always append the `SplFileObject::READ_CSV` flag.


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
