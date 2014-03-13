---
layout: layout
title: Reading & Filtering
---

# Reading & Filtering

## Extracting data from the CSV

To extract data use `League\Csv\Reader` methods.

### query($callable = null)

`query` return a Iterator that represents the CSV. This Iterator can further be manipulated as you wish.

~~~.language-php
$data = $reader->query();
foreach ($data as $line_index => $row) {
    //do something here
}
~~~

### fetchAll($callable = null)

`fetchAll` returns a sequential array of all rows. It is a equivalent of the `query` method but it returns
an array. This means that you can for instance, `count` the CSV rows. 

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
$nb_rows = count($data);
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

The methods listed above (`query`, `fetchAll`, `fetchAssoc`, `fetchCol`) can all take a optional `callable` argument to further manipulate each row before being returned. This callable function can take up to three parameters:

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

### each($callable)

`each` apply a callable function on each CSV row. The callable function:

* **must** return true to continue iterating over the CSV;
* can take up to tree parameters:
    * the current csv row data;
    * the current csv key;
    * the current csv iterator object;

The method returns the number of successful iterations.

~~~.language-php
<?php
//re-create the fetchAll method using the each method
$res = [];
$nbIteration = $reader->each(function ($row, $index, $iterator) use (&$res, $func)) {
    $res[] = $func($row, $index, $iterator);
    return true;
});
~~~

## CSV query options

You can further manipulate the CSV extract methods behavior using the query options. To set those options you will need to use the methods described below. But keep in mind that:

* The query methods are all chainable *except when they have to return a boolean*;
* The query methods can be call in any sort of order before any extract method;
* After an extract method call, all query options are cleared.

## Filtering methods

The filtering methods enable adding and/or removing multiple filters to your CSV. The filters follow the First In First Out rule.

### addFilter($callable)

`addFilter` method adds a callable function to filter the CSV data. This function can take up to three parameters:

* the current csv row data;
* the current csv key;
* the current csv iterator object;


<p class="message-warning">The <code>setFilter</code> method has been deprecated and will be remove in the next major version release. For backward compatibility, the method is now a alias of the <code>addFilter</code> method.</p>

### removeFilter($callable)

`removeFilter` method removes an already registered filter. If the same filter is registered multiple times, you will have to call `removeFilter` as often as the filter was registered. **The first registered filter will be the first to be removed.**

### hasFilter($callable)

`hasFilter` method verifies if a `$callable` filter is already registered

### clearFilter()

`clearFilter` method removes all registered filter functions.

## Sorting methods

The sorting methods enable adding and/or removing multiple sorting functions to your CSV. The sorting follow the First In First Out rule.

<p class="message-warning">To sort the data <code>iterator_to_array</code> is used which could lead to performance penalty if you have a heavy CSV file to sort
</p>

### addSortBy($callable)

`addSortBy` method adds a sorting function to sort the CSV data. The function takes exactly two parameters which will be filled by pairs of rows.

<p class="message-warning">The <code>setSortBy</code> method has been deprecated and will be remove in the next major version release. For backward compatibility, the method is now a alias of the <code>addSortBy</code> method.</p>

### removeSortBy($callable)

`removeSortBy` method removes an already registered sorting function. If the same sorting function is registered multiple times, you will have to call removeSortBy as often as the filter was registered.  **The first registered sorting function will be the first to be removed.**

### hasSortBy($callable)

`hasSortBy` method verifies if a sorting function is already registered

### clearSortBy()

`clearSortBy` method removes all registered sorting functions.

## Interval methods

The methods enable returning a specific interval of rows. When called more than once, only the last filtering settings is taken into account.

### setOffset($offset = 0)

`setOffset` method specifies an optional offset for the return results. By default the offset equals `0`.

### setLimit($limit = -1)

`setLimit` method specifies an optional maximum rows count for the return results. By default the offset equals `-1`, which translate to all rows.

<p class="message-warning">Both methods have no effect on the `fetchOne` method output.</p>

## Using the query features

Here's an example on how to use the query features of the `Reader` class to restrict the `fetchAssoc` result:

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

