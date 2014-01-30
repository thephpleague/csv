Bakame.csv
==========
[![Build Status](https://travis-ci.org/nyamsprod/Bakame.csv.png?branch=master)](https://travis-ci.org/nyamsprod/Bakame.csv)
[![Code Coverage](https://scrutinizer-ci.com/g/nyamsprod/Bakame.csv/badges/coverage.png?s=7ad9740c0ed5fd5d389abfe92d7af04d7f4f542e)](https://scrutinizer-ci.com/g/nyamsprod/Bakame.csv/)

A simple library to easily load, manipulate and save CSV files in PHP 5.4+

This package is compliant with [PSR-0][], [PSR-1][], and [PSR-2][].

[PSR-0]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-0.md
[PSR-1]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-1-basic-coding-standard.md
[PSR-2]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-2-coding-style-guide.md

System Requirements
-------

You need **PHP >= 5.4.0** and the `mbstring` extension to use `Bakame\Csv` but the latest stable version of PHP is recommended.

Install
-------

You may install the Bakame Url package with Composer (recommended) or manually.

```json
{
    "require": {
        "bakame/csv": "~4.*"
    }
}
```

Manual Install
-------
Download, extract the library then add `'/path/to/Bakame/Csv/src'` to your PSR-0 compliant autoloader.

Manipulating a CSV
-------
This library is composed of two main classes:
* `Bakame\Csv\Reader` to read and extract data from a CSV
* `Bakame\Csv\Writer` to create, update and save a CSV. 

Both classes share methods to instantiate, format and output the CSV.

### Instantiation

There's several ways to instantiate these classes:

```php
use Bakame\Csv\Reader;
use Bakame\Csv\Writer;

$writer = new Writer('/path/to/your/csv/file.csv');

//or 

$writer = new Writer(new SpliFileObject('/path/to/your/csv/file.csv'));

//or 

$writer = new Writer::createFromString('john,doe,john.doe@example.com');

```

The static method `Bakame\Csv\Writer::createFromString` is to be use if your data is a string.

Both class can take one optional parameter representing the file open mode used by the PHP [fopen][] function. 
* In case of the `Bakame\Csv\Writer` the default value is `w`, but you can change this value according to your needs.
* In case of the `Bakame\Csv\Reader` the default value is `r`, and no other value is possible. So you don't need to explicitly set it.

[fopen]: http://php.net/manual/en/function.fopen.php

Once you have a `Bakame\Csv\Writer` or a `Bakame\Csv\Reader` object you can optionally set the CSV delimiter, enclosure and/or escape characters as well as the file flags.

```php
$reader = new Reader('/path/to/your/csv/file.csv');

$reader->setDelimeter(',');
$reader->setEnclosure('"');
$reader->setEscape('\\');
$reader->setFlags(SplFileObject::READ_AHEAD|SplFileObject::SKIP_EMPTY);
```

### Traversing the CSV

Both classes implements the `IteratorAggregate` Interface so you can easily iterate over your csv:

```php
foreach ($reader as $row) {
    //do something meaningfull here!!
}
```

### Displaying the data

Both classes implement the `jsonSerializable` interface so you can transform you CSV into a Json string using the `json_encode` function directly on the instantiated object.

Both classes share the following methods to enable outputting the CSV easily
* The `__toString` method returns the CSV content as it is written in the file.
* The `output` method returns to the output buffer the CSV content. This method can be use if you want the CSV to be downloaded by your user.
* The `toHTML` method returns the CSV content formatted in a HTML Table, This methods accept an optional `classname` to help you customize the table rendering, by defaut the classname given to the table is `table-csv-data`.

### Switching from Reader to Writer

Of course at any given time it is possible to switch from one object to the other by using:
* the `Bakame\Csv\Writer::getReader` method from the `Bakame\Csv\Writer` class
* the `Bakame\Csv\Reader::getWriter` method from the `Bakame\Csv\Reader` class this method accept the optional `open_mode` parameter.


Reading a CSV
-------

### Traversing the CSV

The `Bakame\Csv\Reader` implements the `ArrayAccess` Interface so you can access a given row using an array like syntax:

```php
$row = $reader[5]; //accessing the 6th row;
``` 

**The `Bakame\Csv\Reader` can not modify the CSV content so if you try to set/delete/update a row you'll get a `RuntimeException` exception!**

Extracting data is also made easy using the following methods: 

#### fetchAll

 `fetchAll` returns a sequential array of all rows.

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
#### fetchAssoc

`fetchAssoc` returns a sequential array of all rows. The rows themselves are associative arrays where the keys are given directly to the method using an one dimension array. This array should only contain unique `string` and/or `integer` values.

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
> 
> * If the number of values in a CSV row is lesser than the number of named keys, the method will add `null` values to compensate for the missing values.
> * If the number of values in a CSV row is greater that the number of named keys the exceeding values will be drop from the result set.

#### fetchCol

`fetchCol` returns a sequential array of all values in a given column from the CSV data.

```php
$data = $reader->fetchCol(2);
// will return something like this :
// 
// ['john.doe@example.com', 'jane.doe@example.com', ...]
// 

```

The methods listed above (`fetchAll`, `fetchAssoc`, `fetchCol`) can all take a optional `callable` argument to further manipulate each row before being returned. This callable function can take three parameters at most:

* the current csv row data
* the current csv key
* the current csv object

### Filtering the data

You can further manipulate the CSV `fetch*` methods output by specifying the following filtering options:

* the `setFilter`method specifies an optional `callable` function to filter the CSV data. This function takes three parameters at most (see [CallbackFilterIterator][] for more informations)
* the `setSortBy`method specifies an optional `callable` function to sort the CSV data. The function takes two parameters which will be filled by pairs of rows. **Beware when using this filter that you will be using `iterator_to_array` which could lead to performance penalty if you have a heavy CSV file to sort**
* the `setOffset` method specifies an optional offset for the return results.
* the `setLimit` method specifies an optional maximum rows count for the return results.

[CallbackFilterIterator]: http://php.net/manual/en/class.callbackfilteriterator.php#callbackfilteriterator.examples

Here's an example:

```php
function filterByEmail($row) 
{
    return filer_var($row[2], FILTER_VALIDATE_EMAIL);
}

function sortByLastName($rowA, $rowB)
{
    return strcmp($rowB[1], $rowA[1]);
}

$data = $reader
    ->setOffset(3)
    ->setLimit(2)
    ->setFilter('filterByEmail')
    ->setSortBy('sortByLastName')
    ->fetchAssoc(['firstname', 'lastname', 'email'], function ($value) {
    return array_map('strtoupper', $value);
});
// data length will be equals or lesser that 2 starting from the row index 3.
// will return something like this :
// 
// [ 
//   ['firstname' => 'JANE', 'lastname' => 'RAMANOV', 'email' => 'JANE.RAMANOV@EXAMPLE.COM'],
//   ['firstname' => 'JOHN', 'lastname' => 'DOE', 'email' => 'JOHN.DOE@EXAMPLE.COM'],
// ]
// 
```
**Of note:**

* The methods can be call in any sort of order before any `fetch*` method call.
* After a `fetch*` method call, all filtering options are cleared.
* The filtering method will only take into account the last filtering options if for some reason you call twice the same filtering method.

### Manual Filtering

If you want to output differently you data you can use the `query` method. It works like the `fetch*` method but returns an [Iterator][] that you may manipulate as you wish.

[Iterator]: http://php.net/manual/en/class.iterator.php

```php
function filterByEmail($row) 
{
    return filer_var($row[2], FILTER_VALIDATE_EMAIL);
}

function sortByLastName($rowA, $rowB)
{
    return strcmp($rowB[1], $rowA[1]);
}

$iterator = $reader
    ->setFilter('filterByEmail')
    ->setSortBy('sortByLastName')
    ->setOffset(3)
    ->setLimit(2)
    ->query(function ($value) {
        return array_map('strtoupper', $value);
    });
```

Creating, updating and saving a CSV
-------

Once you have a instance of the `Bakame\Csv\Writer` class you can insert new info using two methods:
* `insertOne` which insert a single row: This method can take an `array`, a `string` or an `object` implementing the `__toString` method.
* `insertMany` which insert multiple rows: this method can take an `array` or a `Traversable` object to add several row to the CSV data.

```php

$writer->insertOne(['john', 'doe', 'john.doe@example.com']); //used with an array
$writer->insertOne("'john','doe','john.doe@example.com'");   //used with a string

$arr = [
    [1, 2, 3],
    ['foo', 'bar', 'baz'],
];

$writer->insertMany($arr) //using an array 

$object = new ArrayIterator($arr);
$writer->insertMany($object); //using a Traversable object

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
