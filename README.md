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
        "bakame/csv": "~3.*"
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

Before manipulating you CSV data, you must first be able to load and save you CSV. In order to do so, the library provides you the `Bakame\Csv\Codec` class.

### The Codec Class.

Before using the class you may need to set the csv controls characters. You can do it on the class constructor or use the appropriate setter method like below:

```php
<?php

use Bakame\Csv\Codec;

$codec = new Codec;
$codec->setDelimeter(',');
$codec->setEnclosure('"');
$codec->setEscape('\\');

//or

$codec = new Codec(',', '"', '\\');
```

#### Loading CSV data

Once instantiated and depending on your CSV source you may choose:

* the `Codec::loadFile` method to load the CSV from a file;
* the `Codec::loadString` method to enable reading your CSV from a string;

Both methods will return a `Bakame\Csv\Reader` object to help you manipulate your data.

```php
$csv = $codec->loadFile('path/to/my/csv/file.csv');
//$csv is a \Bakame\Csv\Reader object

$csv = $codec->loadString(['foo,bar,baz', ['foo', 'bar', 'baz']]);
//$csv is a \Bakame\Csv\Reader object

```

#### Saving CSV data

The `Codec::save` method help you save you CSV data.

It accepts:
* an `array` of data
* any object that implements the `Traversable` interface.

The path to where the data must saved can be given as:
* a simple string
* an `SplFileInfo` instance

If the data is invalid or the file does not exist or is not writable an `InvalidArgumentException` exception will be thrown.

Just like the loading methods, the `Codec::save` returns a `Bakame\Csv\Reader` object.

```php
$csv = $codec->save([1,2,3,4], '/path/to/my/saved/csv/file.csv');
//$csv is a \Bakame\Csv\Reader object

```

### The Reader Class

The `Bakame\Csv\Reader` manipulates CSV data that are stored in a `SplFileObject` object. 
> **The class does not modify the CSV data, it just helps you accessing them more easily**

To instantiate the class you must provide at least a `SplFileObject` object like below:

```php

use Bakame\Csv\Reader;

$csv = new Reader(new SpliFileObject('/path/to/your/csv/file.csv'));
$csv->setDelimeter(',');
$csv->setEnclosure('"');
$csv->setEscape('\\');
$csv->setFlags(SplFileObject::READ_AHEAD|SplFileObject::SKIP_EMPTY);

//or 

$csv = new Reader(
	new SpliFileObject('/path/to/your/csv/file.csv'), 
	',',
	'"',
	'\\',
	SplFileObject::READ_AHEAD|SplFileObject::SKIP_EMPTY
);

```
You can optionally set the CSV delimiter, enclosure and/or escape characters as well as the file flags.

The `Bakame\Csv\Reader` lets you access the `SplFileObject` by using the `Bakame\Csv\Reader::getFile` method.

#### Displaying the CSV

* The `Bakame\Csv\Reader::__toString` method returns the CSV content as it is written in the file.
* The `Bakame\Csv\Reader::output` method returns to the output buffer the CSV content. This method can be use if you want the CSV to be downloaded by your user.

#### Traversing the CSV

The `Bakame\Csv\Reader` implements the `ArrayAccess` so if you want to access a given row you can do so using an array like syntax:

```php
$row = $csv[5]; //accessing the 6th row;
``` 
** The `Bakame\Csv\Reader` can not modify the CSV content so if you try to set/delete/update a row you'll get a `RuntimeException` exception **

Aside from the Extracting CSV data is made easy using the following methods: 

##### fetchAll

 `fetchAll` returns a sequential array of all rows.

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
##### fetchAssoc

`fetchAssoc` returns a sequential array of all rows. The rows themselves are associative arrays where the keys are given directly to the method using an one dimension array. This array should only contain unique `string` and/or `integer` values.

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
> 
> * If the number of values in a CSV row is lesser than the number of named keys, the method will add `null` values to compensate for the missing values.
> * If the number of values in a CSV row is greater that the number of named keys the exceeding values will be drop from the result set.

##### fetchCol

`fetchCol` returns a sequential array of all values in a given column from the CSV data.

```php
$data = $csv->fetchCol(2);
// will return something like this :
// 
// ['john.doe@example.com', 'jane.doe@example.com', ...]
// 

```

The methods listed above (`fetchAll`, `fetchAssoc`, `fetchCol`) can all:
* Take a optional `callable` argument to further manipulate each row before being returned.

#### Filtering the CSV data

In order to filter the CSV data you can modify the `fetch*` methods output by specifying filtering options using the following methods:

* the `setFilter`method specifies an optional `callable` function to filter the CSV data. This function takes three parameters at most (see [CallbackFilterIterator][] for more informations)
* the `setSortBy`method specifies an optional `callable` function to sort the CSV data. The function takes two parameters which will be filled by pairs of rows.
* the `setOffset` method specifies an optional offset for the return results.
* the `setLimit` method specifies an optional maximum rows count for the return results.

[CallbackFilterIterator]: http://php.net/manual/en/class.callbackfilteriterator.php#callbackfilteriterator.examples

Here's an example:

```php
$data = $csv
    ->setOffset(3)
    ->setLimit(5)
	->fetchAssoc(['firstname', 'lastname', 'email'], function ($value) {
	return array_map('strtoupper', $value);
});
// data length will be equals or lesser that 5 starting from the row index 3.
// will return something like this :
// 
// [ 
//   ['firstname' => 'JOHN', 'lastname' => 'DOE', 'email' => 'JOHN.DOE@EXAMPLE.COM'],
//   ['firstname' => 'JANE', 'lastname' => 'DOE', 'email' => 'JANE.DOE@EXAMPLE.COM'],
//   ...
// ]
// 
```
***Of note**:

* After a call the the `fetch*` methods, the filtering data are flushed.
* the filtering methods can be used in any sort of order.


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
