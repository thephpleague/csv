---
layout: default
title: Query Filtering
---

# Query Filtering

## Query Filters

You can restrict [extract methods](/reading/) and [conversion methods](/converting/) output by setting query options. To set those options you will need to use the methods described below. But keep in mind that:

* The query options methods are all chainable *except when they have to return a boolean*;
* The query options methods can be called in any sort of order before any extract/conversion method;
* After an extract/conversion method call, all query options are cleared;

## Modifying content methods

### AbstractCsv::stripBOM

 This method specifies if the [BOM sequence](/bom/) must be removed or not from the CSV's first cell of the first row.

~~~php
<?php

public AbstractCsv::stripBOM(bool $status): AbstractCsv
~~~

`stripBom`'s only argument `$status` must be a `boolean`.

The actual stripping will take place only if a BOM sequence is detected and the first row is selected in the resultset **or** if its offset is used as the first argument of the `Reader::fetchAssoc` method.

<p class="message-warning">The BOM sequence is never removed from the CSV document, it is only stripped from the resultset.</p>

## Filtering methods

The filtering options **are the first settings applied to the CSV before anything else**. The filters follow the *First In First Out* rule.

### AbstractCsv::addFilter

The `addFilter` method adds a callable filter function each time it is called.

~~~php
<?php

public AbstractCsv::addFilter(callable $callable): AbstractCsv
~~~

The callable filter signature is as follows:

~~~php
<?php

function(array $row [, int $rowOffset [, Iterator $iterator]]): AbstractCsv
~~~

It takes up to three parameters:

- `$row`: the CSV current row as an array
- `$rowOffset`: the CSV current row offset
- `$iterator`: the current CSV iterator

## Sorting methods

The sorting options are applied **after the CSV filtering options**. The sorting follows the *First In First Out* rule.

<p class="message-warning">To sort the data <code>iterator_to_array</code> is used, which could lead to a performance penalty if you have a heavy CSV file to sort
</p>

### AbstractCsv::addSortBy

`addSortBy` method adds a sorting function each time it is called.

~~~php
<?php

public AbstractCsv::addSortBy(callable $callable): AbstractCsv
~~~

The callable sort function signature is as follows:

~~~php
<?php

function(array $row, array $row): int
~~~

The sort function takes exactly two parameters, which will be filled by pairs of rows.

## Interval methods

The interval methods enable returning a specific interval of CSV rows. When called more than once, only the last filtering settings is taken into account. The interval is calculated **after filtering and/or sorting but before extracting the data**.

The interval API is made of the following method

~~~php
<?php

public AbstractCsv::setOffset(int $offset = 0): AbstractCsv
public AbstractCsv::setLimit(int $limit = -1): AbstractCsv
~~~

Where

- `AbstractCsv::setOffset` specifies an optional offset for the return data. By default the offset equals `0`.
- `AbstractCsv::setLimit` specifies an optional maximum rows count for the return data. By default the offset equals `-1`, which translate to all rows.

<p class="message-warning">Both methods have no effect on the <code>fetchOne</code> method output.</p>

## Examples

### Modifying extract methods output

Here's an example on how to use the query features of the `Reader` class to restrict the `fetchAssoc` result:

~~~php
<?php

use League\Csv\Reader;

function filterByEmail($row)
{
    return filter_var($row[2], FILTER_VALIDATE_EMAIL);
}

function sortByLastName($rowA, $rowB)
{
    return strcmp($rowB[1], $rowA[1]);
}

$reader = Reader::createFromPath('/path/to/file.csv');
$data = $reader
    ->stripBom(false)
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

### Modifying conversion methods output

The query options can also modify the output from the conversion methods as shown below with the `toHTML` method.

~~~php
<?php

use League\Csv\Reader;

function filterByEmail($row)
{
    return filter_var($row[2], FILTER_VALIDATE_EMAIL);
}

function sortByLastName($rowA, $rowB)
{
    return strcmp($rowB[1], $rowA[1]);
}

$reader = Reader::createFromPath('/path/to/file.csv');
$data = $reader
    ->stripBom(true)
    ->setOffset(3)
    ->setLimit(2)
    ->addFilter('filterByEmail')
    ->addSortBy('sortByLastName')
    ->toHTML("simple-table");
// $data contains the HTML table code equivalent to:
//
//<table class="simple-table">
//  <tr><td>JANE</td><td>RAMANOV</td><td>JANE.RAMANOV@EXAMPLE.COM</td></tr>
//  <tr><td>JOHN</td><td>DOE</td><td>JOHN.DOE@EXAMPLE.COM</td></tr>
//</table>
//
~~~
