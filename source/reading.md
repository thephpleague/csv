---
layout: layout
title: Reading & Filtering
---

# Reading & Filtering

## Extracting data from the CSV

To extract data use `League\Csv\Reader` methods.

### fetchAll($callable = null)

`fetchAll` returns a sequential array of all rows.

~~~.language-php
$data = $reader->fetchAll();
// will return something like this :
// 
// [ 
//   ['john', 'doe', 'john.doe@example.com'],
//   ['jane', 'doe', 'jane.doe@example.com'],
//   ...
// ]
//
~~~

### fetchAssoc([], $callable = null)

`fetchAssoc` returns a sequential array of all rows. The rows themselves are associative arrays where the keys are given directly to the method using an one dimension array. This array should only contain unique `string` and/or `integer` values.

~~~.language-php
$data = $reader->fetchAssoc(['firstname', 'lastname', 'email']);
// will return something like this :
// 
// [ 
//   ['firstname' => 'john', 'lastname' => 'doe', 'email' => 'john.doe@example.com'],
//   ['firstname' => 'jane', 'lastname' => 'doe', 'email' => 'jane.doe@example.com'],
//   ...
// ]
// 
~~~

If the number of values in a CSV row is lesser than the number of named keys, the method will add `null` values to compensate for the missing values.

If the number of values in a CSV row is greater that the number of named keys the exceeding values will be drop from the result set.

### fetchCol($columnIndex = 0, $callable = null)

`fetchCol` returns a sequential array of all values in a given column from the CSV data.

If no argument is given to the method it will return the first colum from the CSV data.
If the column does not exists in the csv data the method will return an array full of `null` value.

~~~.language-php
$data = $reader->fetchCol(2);
// will return something like this :
// 
// ['john.doe@example.com', 'jane.doe@example.com', ...]
// 
~~~

The methods listed above (`fetchAll`, `fetchAssoc`, `fetchCol`) can all take a optional `callable` argument to further manipulate each row before being returned. This callable function can take up to three parameters:

* the current csv row data
* the current csv key
* the current csv iterator object

### fetchOne($offset = 0)

`fetchOne` return one single row from the CSV data. The required argument $offset represent the row index starting at 0. If no argument is given to the method it will return the first row from the CSV data.

~~~.language-php
$data = $reader->fetchOne(3); ///accessing the 4th row (indexing starts at 0)
// will return something like this :
// 
//   ['john', 'doe', 'john.doe@example.com']
//
~~~

## Filtering the data

You can further manipulate the CSV `fetch*` methods output by specifying filtering options using the following methods:

### setFilter($callable = null)

setFilter method specifies an optional callable function to filter the CSV data. This function takes three parameters at most (see CallbackFilterIterator for more informations)

### setSortBy($callable = null)

setSortBy method specifies an optional callable function to sort the CSV data. The function takes two parameters which will be filled by pairs of rows.

Beware when using this filter that you will be using `iterator_to_array` which could lead to performance penalty if you have a heavy CSV file to sort

### setOffset($offset) and setLimit($limit)

setOffset method specifies an optional offset for the return results.
setLimit method specifies an optional maximum rows count for the return results.

Both methods have no effect on the `fetchOne` method output

Here's an example:

~~~.language-php
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
~~~

**Of note:**

The filtering methods are chainable;
The methods can be call in any sort of order before any `fetch*` method call;
After a `fetch*` method call, all filtering options are cleared;
Only the last filtering settings are taken into account if the same method is called more than once;

### Manual extracting and filtering

If you want to output differently you data you can use the `query` method. It works like the `fetchAll` method but returns an Iterator that you may manipulate as you wish.

~~~.language-php
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
~~~