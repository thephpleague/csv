---
layout: default
title: Extracting data from a CSV
permalink: reading/
---

# Extracting data

To extract data use `League\Csv\Reader` methods.

## Fetching CSV data

### query(callable $callable = null)

The `query` method prepares and issues queries on the CSV data. It returns an `Iterator` that represents the result that you can further manipulate as you wish.

~~~php
$data = $reader->query();
foreach ($data as $line_index => $row) {
    //do something here
}
~~~

### fetchAll(callable $callable = null)

`fetchAll` returns a sequential array of all rows.

~~~php
$data = $reader->fetchAll();
// will return something like this :
// 
// [ 
//   ['john', 'doe', 'john.doe@example.com'],
//   ['jane', 'doe', 'jane.doe@example.com'],
//   ...
// ]
//
$nb_rows = count($data);
~~~

### fetchAssoc($offset_or_keys = 0, callable $callable = null)

`fetchAssoc` returns a sequential array of all rows. The rows themselves are associative arrays where the keys are an one dimension array. This array should only contain unique `string` and/or `integer` values. This array can be specified as the first argument as

- a specific CSV row by providing its offset; **(since version 6.1)**
- a non empty array directly provided;

~~~php
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

If no argument is provided, the first row from the CSV data will be used **(since version 6.1)**.

<p class="message-warning">When a row index is specified it will not appear in the resulting array</p>

~~~php
$data = $reader->fetchAssoc();
// will output something like
// [
//    ['prenoms' => 'Aaron', 'nombre' => '55', 'sexe' => 'M', 'annee' => '2004'],
//    ['prenoms' => 'Abdallah', 'nombre' => '7', 'sexe' => 'M', 'annee' => '2004'],
// ...
// ]
//
//
//
~~~

<p class="message-notice">Prior to version <code>6.1</code> the method worked only with <code>array</code>. Now the method works with <code>array</code> and <code>integer</code>.</p>

### fetchColumn($columnIndex = 0, callable $callable = null)

`fetchColumn` returns a sequential array of all values in a given column from the CSV data.

If no argument is given to the method it will return the first column from the CSV data.
If the column does not exists in the csv data the method will return an array full of `null` value.

~~~php
$data = $reader->fetchColumn(2);
// will return something like this :
// 
// ['john.doe@example.com', 'jane.doe@example.com', ...]
// 
~~~

The methods listed above (`query`, `fetchAll`, `fetchAssoc`, `fetchColumn`) can all take a optional `callable` argument to further manipulate each row before being returned. This callable function can take up to three parameters:

* the current csv row data
* the current csv key
* the current csv iterator object

### fetchOne($offset = 0)

`fetchOne` return one single row from the CSV data. The required argument $offset represent the row index starting at 0. If no argument is given to the method it will return the first row from the CSV data.

~~~php
$data = $reader->fetchOne(3); ///accessing the 4th row (indexing starts at 0)
// will return something like this :
// 
//   ['john', 'doe', 'john.doe@example.com']
//
~~~

### each(callable $callable)

`each` apply a callable function on each CSV row. The callable function:

* **must** return `true` to continue iterating over the CSV;
* can take up to tree parameters:
    * the current csv row data;
    * the current csv key;
    * the current csv iterator object;

The method returns the number of successful iterations.

~~~php
<?php
//re-create the fetchAll method using the each method
$res = [];
$nbIteration = $reader->each(function ($row, $index, $iterator) use (&$res, $func)) {
    $res[] = $func($row, $index, $iterator);
    return true;
});
~~~

## Querying CSV data

You can restrict CSV extract methods output by setting query options. To set those options you will need to use the methods described below. But keep in mind that:

* The query options methods are all chainable *except when they have to return a boolean*;
* The query options methods can be call in any sort of order before any extract method;
* After an extract method call, all query options are cleared;
* The optional extract method callable function is called after all query options have been applied;

<p class="message-info">The options methods are described in the same order as they are applied on the CSV iterator. The order is similar to one found in SQL statement construct.</p>

## Filtering methods

The filtering options **are the first settings applied to the CSV before anything else**. The filters follow the *First In First Out* rule.

### addFilter(callable $callable)

The `addFilter` method adds a callable filter function each time it is called. The function can take up to three parameters:

* the current csv row data;
* the current csv key;
* the current csv iterator object;

### removeFilter(callable $callable)

`removeFilter` method removes an already registered filter function. If the function was registered multiple times, you will have to call `removeFilter` as often as the filter was registered. **The first registered copy will be the first to be removed.**

### hasFilter(callable $callable)

`hasFilter` method checks if the filter function is already registered

### clearFilter()

`clearFilter` method removes all registered filter functions.

## Sorting methods

The sorting options are applied **after the CSV filtering options**. The sorting follow the *First In First Out* rule.

<p class="message-warning">To sort the data <code>iterator_to_array</code> is used which could lead to performance penalty if you have a heavy CSV file to sort
</p>

### addSortBy(callable $callable)

`addSortBy` method adds a sorting function each time it is called. The function takes exactly two parameters which will be filled by pairs of rows.

### removeSortBy(callable $callable)

`removeSortBy` method removes an already registered sorting function. If the function was registered multiple times, you will have to call `removeSortBy` as often as the function was registered. **The first registered copy will be the first to be removed.**

### hasSortBy(callable $callable)

`hasSortBy` method checks if the sorting function is already registered

### clearSortBy()

`clearSortBy` method removes all registered sorting functions.

## Interval methods

The methods enable returning a specific interval of CSV rows. When called more than once, only the last filtering settings is taken into account. The interval is calculated **after filtering and/or sorting but before extracting the data**.

### setOffset($offset = 0)

`setOffset` method specifies an optional offset for the return data. By default the offset equals `0`.

### setLimit($limit = -1)

`setLimit` method specifies an optional maximum rows count for the return data. By default the offset equals `-1`, which translate to all rows.

<p class="message-warning">Both methods have no effect on the `fetchOne` method output.</p>

## A concrete example

Here's an example on how to use the query features of the `Reader` class to restrict the `fetchAssoc` result:

~~~php
function filterByEmail($row) 
{
    return filter_var($row[2], FILTER_VALIDATE_EMAIL);
}

function sortByLastName($rowA, $rowB)
{
    return strcmp($rowB[1], $rowA[1]);
}

$data = $reader
    ->setOffset(3)
    ->setLimit(2)
    ->addFilter('filterByEmail')
    ->addSortBy('sortByLastName')
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

