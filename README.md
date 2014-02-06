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

You need the `mbstring` and the `iconv` extension as well as **PHP >= 5.4.0** to use `Bakame\Csv` but the latest stable version of PHP is recommended.

Install
-------

You may install the Bakame.csv package with Composer (recommended) or manually.

```json
{
    "require": {
        "bakame/csv": "~4.*"
    }
}
```

**Manual Install:**

Download and extract the library in a specific directory, then add `'/path/to/Bakame/Csv/src'` to your PSR-0 compliant autoloader.

Manipulating a CSV
-------

**If you don't want to read the whole documentation just look at the [examples](examples/README.md) directory to see how the library works.**

The library is composed of two main classes:
* `Bakame\Csv\Reader` to extract and filter data from a CSV
* `Bakame\Csv\Writer` to insert new data into a CSV. 

Both classes share methods to instantiate, format and output the CSV.

### Instantiation

There's several ways to instantiate these classes:

```php
use Bakame\Csv\Reader;
use Bakame\Csv\Writer;

$writer = new Writer('/path/to/your/csv/file.csv');
$reader = new Reader('/path/to/your/csv/file.csv');
//or 

$writer = new Writer(new SpliFileObject('/path/to/your/csv/file.csv'));
$reader = new Reader(new SpliFileObject('/path/to/your/csv/file.csv'));
//or 

$writer = new Writer::createFromString('john,doe,john.doe@example.com');
$reader = new Reader::createFromString('john,doe,john.doe@example.com');
```

The static method `createFromString` is to be use if your data is a string.

Both classes can take one optional parameter `$open_mode` representing the file open mode used by the PHP [fopen][] function. 
* In case of the `Bakame\Csv\Writer` class `$open_mode` default value is `w`, but you can change this value according to your needs.
* In case of the `Bakame\Csv\Reader` class `$open_mode` default value is `r`, and **no other value is possible**. So you don't need to explicitly set it.
* the `$open_mode` argument is not taken into account when creating a object from the static method `createFromString`.


[fopen]: http://php.net/manual/en/function.fopen.php

Once your object is created you can optionally set the CSV delimiter, enclosure and/or escape characters as well as the file `SplFileObject` flags and the CSV encoding charset.

```php
$reader = new Reader('/path/to/your/csv/file.csv');

$reader->setDelimeter(',');
$reader->setEnclosure('"');
$reader->setEscape('\\');
$reader->setFlags(SplFileObject::READ_AHEAD|SplFileObject::SKIP_EMPTY);
$reader->setEncoding('iso-8859-1');
```

### Traversing the CSV

Both classes implement the `IteratorAggregate` Interface so you can iterate over your csv data using the `foreach` construct:

```php
foreach ($reader as $row) {
    //do something meaningfull here!!
}
```

### Outputting the CSV

Both classes implement the `jsonSerializable` interface so you can transform you CSV into a Json string using the `json_encode` function directly on the instantiated object.

```php
echo json_encode($reader);
```

Both classes share the following methods to enable outputting the CSV:

* The `__toString` method returns the CSV content as it is written in the file.
* The `output` method returns to the output buffer the CSV content. This method can be use if you want the CSV to be downloaded by your user.
* The `toHTML` method returns the CSV content formatted in a HTML Table, This methods accept an optional argument `$classname` to help you customize the table rendering, by defaut the classname given to the table is `table-csv-data`.

### Switching from one class to the other

Of course at any given time it is possible to switch from one object to the other by using:
* the `Bakame\Csv\Writer::getReader` method from the `Bakame\Csv\Writer` class
* the `Bakame\Csv\Reader::getWriter` method from the `Bakame\Csv\Reader` class this method accept the optional `$open_mode` parameter.

```php
$reader = $writer->getReader();
$newWriter = $reader->getWriter('a'); 
//be carefull the $newWriter is not equal to $writer!!

```

Extracting data from the CSV
-------

Extracting data is made easy using the following methods on a `Bakame\Csv\Reader` object: 

#### fetchOne

`fetchOne` return one single row from the CSV data. The required argument `$offset` represent the row index starting at 0.

```php
$data = $reader->fetchOne(3); ///accessing the 4th row (indexing starts at 0)
// will return something like this :
// 
//   ['john', 'doe', 'john.doe@example.com']
//
```

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
* the current csv Iterator Object (usually a `SplFileObject`)

### Filtering the data

You can further manipulate the CSV `fetch*` methods output by specifying filtering options using the following methods:

#### setFilter

`setFilter` method specifies an optional `callable` function to filter the CSV data. This function takes three parameters at most (see [CallbackFilterIterator][] for more informations)

[CallbackFilterIterator]: http://php.net/manual/en/class.callbackfilteriterator.php#callbackfilteriterator.examples

#### setSortBy

`setSortBy` method specifies an optional `callable` function to sort the CSV data. The function takes two parameters which will be filled by pairs of rows.

**Beware when using this filter that you will be using `iterator_to_array` which could lead to performance penalty if you have a heavy CSV file to sort**

#### setOffset and setLimit

* `setOffset` method specifies an optional offset for the return results.
* `setLimit` method specifies an optional maximum rows count for the return results. 

**Both methods are ignore by the `fetchOne` method.**

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

* The filtering methods are chainable;
* The methods can be call in any sort of order before any `fetch*` method call;
* After a `fetch*` method call, all filtering options are cleared;
* Only the last filtering settings are taken into account if the same method is called more than once;

### Manual extracting and filtering

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

Inserting data into the CSV
-------

To insert new data into a CSV use the following methods on a `Bakame\Csv\Writer` object: 

#### insertOne

`insertOne` inserts a single row. This method can take an `array`, a `string` or an `object` implementing the `__toString` method.

```php
class ToStringEnabledClass
{
    private $str;

    public function __construct($str)
    {
        $this->str = $str;
    }

    public function __toString()
    {
        return $this->str;
    }
}

$writer->insertOne(['john', 'doe', 'john.doe@example.com']); //used with an array
$writer->insertOne("'john','doe','john.doe@example.com'");   //used with a string
$writer->insertOne(new ToStringEnabledClass("john,doe,john.doe@example.com")) //used with an object implementing the '__toString' magic method;

```

#### insertAll

`insertAll` inserts multiple rows. This method can take an `array` or a `Traversable` object to add several rows to the CSV data.


```php
$arr = [
    [1, 2, 3],
    ['foo', 'bar', 'baz'],
    "'john','doe','john.doe@example.com'",
    new ToStringEnabledClass("john,doe,john.doe@example.com")
];

$writer->insertAll($arr) //using an array 

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

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

Credits
-------

- [ignace nyamagana butera](https://github.com/nyamsprod)
- [All Contributors](graphs/contributors)
