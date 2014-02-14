Bakame.csv
==========
[![Build Status](https://travis-ci.org/nyamsprod/Bakame.csv.png?branch=master)](https://travis-ci.org/nyamsprod/Bakame.csv)
[![Code Coverage](https://scrutinizer-ci.com/g/nyamsprod/Bakame.csv/badges/coverage.png?s=7ad9740c0ed5fd5d389abfe92d7af04d7f4f542e)](https://scrutinizer-ci.com/g/nyamsprod/Bakame.csv/)

A simple library to easily load, manipulate and save CSV files in PHP 5.4+

This package is compliant with [PSR-1][], [PSR-2][], and [PSR-4][].

[PSR-1]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-1-basic-coding-standard.md
[PSR-2]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-2-coding-style-guide.md
[PSR-4]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-4-autoloader.md


System Requirements
-------

You need **PHP >= 5.4.0** and the `mbstring` extension to use `Bakame\Csv` but the latest stable version of PHP is recommended.

Install
-------

Install the Bakame.csv package with Composer.

```json
{
    "require": {
        "bakame/csv": "4.*"
    }
}
```

Usage
-------

* [Downloading the CSV](examples/download.php)
* [Converting the CSV into a Json String](examples/json.php)
* [Converting the CSV into a HTML Table](examples/table.php)
* [Selecting specific rows in the CSV](examples/extract.php)
* [Filtering a CSV](examples/filtering.php)
* [Creating a CSV](examples/writing.php)
* [Switching between modes from Writer to Reader mode](examples/switchmode.php)

> The CSV file use for the examples is taken from [Paris Opendata](http://opendata.paris.fr/opendata/jsp/site/Portal.jsp?document_id=60&portlet_id=121)

### Tips

* When creating a file using the library, first insert all the data that need to be inserted before starting manipulating the CSV. If you manipulate your data before you may change the file cursor position and get unexpected results.

* If you are dealing with non-unicode data, specify the encoding parameter using the `setEncoding` method otherwise your output conversions may no work.

* **If you are on a Mac OS X Server**, add the following lines before using the library to help [PHP detect line ending in Mac OS X](http://php.net/manual/en/function.fgetcsv.php#refsect1-function.fgetcsv-returnvalues).

```php
if (! ini_get("auto_detect_line_endings")) {
    ini_set("auto_detect_line_endings", true);
}
```

Documentation
-------

The library is composed of two main classes:
* `Bakame\Csv\Reader` to extract and filter data from a CSV
* `Bakame\Csv\Writer` to insert new data into a CSV.

Both classes share methods to instantiate, format and output the CSV.

### Instantiation

There's several ways to instantiate these classes:

```php
use Bakame\Csv\Reader;
use Bakame\Csv\Writer;

$reader = new Reader('/path/to/your/csv/file.csv');
$reader = new Reader(new SpliFileInfo('/path/to/your/csv/file.csv'));
$reader = Reader::createFromString('john,doe,john.doe@example.com');

//or 

$writer = new Writer('/path/to/your/csv/file.csv', 'w');
$writer = new Writer(new SpliFileObject('/path/to/your/csv/file.csv'), 'a+');
$writer = Writer::createFromString('john,doe,john.doe@example.com');
```

Both classes constructors take one optional parameter `$open_mode` representing the file open mode used by the PHP [fopen](http://php.net/manual/en/function.fopen.php) function. 
* The `Bakame\Csv\Writer` `$open_mode` default value is `w`.
* The `Bakame\Csv\Reader` `$open_mode` default value is `r`, and **no other value is possible**. So you don't need to explicitly set it.

The static method `createFromString` is to be use if your data is a string. This method takes no optional `$open_mode` parameter.

Once your object is created you can optionally set:

* the CSV delimiter, enclosure and/or escape characters;
* the object `SplFileObject` flags;
* the CSV encoding charset if the CSV is not in `UTF-8`;

```php
$reader = new Reader('/path/to/your/csv/file.csv');

$reader->setDelimeter(',');
$reader->setEnclosure('"');
$reader->setEscape('\\');
$reader->setFlags(SplFileObject::READ_AHEAD|SplFileObject::SKIP_EMPTY);
$reader->setEncoding('iso-8859-1');
```

### Traversing the CSV

With both classes you can iterate over your csv data using the `foreach` construct:

```php
foreach ($reader as $row) {
    //do something meaningfull here with $row !!
    //$row is an array where each item represent a CSV data cell
}
```

### Outputting the CSV

**Don't forget to set the CSV encoding format before using any outputting CSV method if your data is not `UTF-8` encoded.**

With both classes you can:

#### show the CSV content:

Use the `echo` construct on the instantiated object or use the `__toString` method.

```php
echo $writer;
//or
echo $writer->__toString();
```

Use the `toHTML` method to format the CSV data into an HTML table. This method accepts an optional argument `$classname` to help you customize the table rendering, by defaut the classname given to the table is `table-csv-data`.

```php
echo $writer->toHTML('table table-bordered table-hover');
```

#### convert the CSV into a Json string:

Use the `json_encode` function directly on the instantiated object.

```php
echo json_encode($writer);
```

#### make the CSV downloadable

If you only wish to make your CSV downloadable just use the `output` method to return to the output buffer the CSV content.

```php
$reader->setEncoding('ISO-8859-15');
header('Content-Type: text/csv; charset="'.$reader->getEncoding().'"');
header('Content-Disposition: attachment; filename="name-for-your-file.csv"');
$reader->output();
```

Extracting data from the CSV
-------

To extract data use `Bakame\Csv\Reader` methods.

#### fetchAll($callable = null)

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
#### fetchAssoc([], $callable = null)

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

> * If the number of values in a CSV row is lesser than the number of named keys, the method will add `null` values to compensate for the missing values.
> * If the number of values in a CSV row is greater that the number of named keys the exceeding values will be drop from the result set.

#### fetchCol($columnIndex, $callable = null)

`fetchCol` returns a sequential array of all values in a given column from the CSV data.

```php
$data = $reader->fetchCol(2);
// will return something like this :
// 
// ['john.doe@example.com', 'jane.doe@example.com', ...]
// 

```

The methods listed above (`fetchAll`, `fetchAssoc`, `fetchCol`) can all take a optional `callable` argument to further manipulate each row before being returned. This callable function can take up to three parameters:

* the current csv row data
* the current csv key
* the current csv Iterator Object

#### fetchOne($offset)

`fetchOne` return one single row from the CSV data. The required argument `$offset` represent the row index starting at 0.

```php
$data = $reader->fetchOne(3); ///accessing the 4th row (indexing starts at 0)
// will return something like this :
// 
//   ['john', 'doe', 'john.doe@example.com']
//
```

### Filtering the data

You can further manipulate the CSV `fetch*` methods output by specifying filtering options using the following methods:

#### setFilter($callable = null)

`setFilter` method specifies an optional `callable` function to filter the CSV data. This function takes three parameters at most (see [CallbackFilterIterator][] for more informations)

[CallbackFilterIterator]: http://php.net/manual/en/class.callbackfilteriterator.php#callbackfilteriterator.examples

#### setSortBy($callable = null)

`setSortBy` method specifies an optional `callable` function to sort the CSV data. The function takes two parameters which will be filled by pairs of rows.

**Beware when using this filter that you will be using `iterator_to_array` which could lead to performance penalty if you have a heavy CSV file to sort**

#### setOffset($offset) and setLimit($limit)

* `setOffset` method specifies an optional offset for the return results.
* `setLimit` method specifies an optional maximum rows count for the return results. 

**Both methods have no effect on the `fetchOne` method output**

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

If you want to output differently you data you can use the `query` method. It works like the `fetchAll` method but returns an [Iterator][] that you may manipulate as you wish.

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

To create or update a CSV use the following specific `Bakame\Csv\Writer` methods.

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
$writer->insertAll($object); //using a Traversable object
```

**When inserting strings don't forget to specify the CSV delimiter and enclosure characters that match those use in the string**.


Switching from one class to the other
-------

It is possible to switch between modes by using:

* the `Bakame\Csv\Writer::getReader` method from the `Bakame\Csv\Writer` class
* the `Bakame\Csv\Reader::getWriter` method from the `Bakame\Csv\Reader` class this method accept the optional `$open_mode` parameter.

```php
$reader = $writer->getReader();
$newWriter = $reader->getWriter('a'); 
```
**be careful the `$newWriter` object is not equal to the `$writer` object!!**

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

[![Bitdeli Badge](https://d2weczhvl823v0.cloudfront.net/nyamsprod/bakame.csv/trend.png)](https://bitdeli.com/free "Bitdeli Badge")