---
layout: default
title: Extracting data from a CSV
---

# Extracting data

To extract data from a CSV document use `League\Csv\Reader` methods.

## Reader::fetch

The `fetch` method fetches the next row from the `Iterator` result set.

~~~php
<?php

public Reader::fetch(callable $callable = null): Iterator
~~~

The method takes an optional callable parameter to apply to each row of the resultset before returning. The callable signature is as follow:

~~~php
<?php

function(array $row [, int $rowOffset [, Iterator $iterator]]): array
~~~

- `$row`: the CSV current row as an array
- `$rowOffset`: the CSV current row offset
- `$iterator`: the current CSV iterator

### Example 1

~~~php
<?php

use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/my/file.csv');
$results = $reader->fetch();
foreach ($results as $row) {
    //do something here
}
~~~

### Example 2 - with a callable

~~~php
<?php

use League\Csv\Reader;

$func = function ($row) {
    return array_map('strtoupper', $row);
};

$reader = Reader::createFromPath('/path/to/my/file.csv');
$results = $reader->fetch($func);
foreach ($results as $row) {
    //each row member will be uppercased
}
~~~

## Reader::fetchAll

`fetchAll` returns a sequential `array` of all rows.

~~~php
<?php

public Reader::fetchAll(callable $callable = null): array
~~~

`fetchAll` behaves exactly like `fetch` with one difference:

- `fetchAll` returns an `array`.

## Reader::fetchOne

`fetchOne` return one single row from the CSV data as an `array`.

~~~php
<?php

public Reader::fetchOne($offset = 0): array
~~~

The required argument `$offset` represents the row index starting at `0`. If no argument is given the method will return the first row from the CSV data.

### Example

~~~php
<?php

use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/my/file.csv');
$data = $reader->fetchOne(3); ///accessing the 4th row (indexing starts at 0)
// will return something like this :
//
//   ['john', 'doe', 'john.doe@example.com']
//
~~~

## Reader::each

`each` applies a callable function on each CSV row.

~~~php
<?php

public Reader::each(callable $callable): int
~~~

The method returns the number of successful iterations.

The callable signature is as follows:

~~~php
<?php

function(array $row [, int $rowOffset [, Iterator $iterator]]): bool
~~~

- `$row`: the CSV current row as an array
- `$rowOffset`: the CSV current row offset
- `$iterator`: the current CSV iterator

The callable must return `true` to continue iterating over the CSV;

### Example - Counting the CSV total number of rows

~~~php
<?php

use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/my/file.csv');

//count the numbers of rows in a CSV
$nbRows = $reader->each(function ($row) {
    return true;
});
~~~

## Reader::fetchAssoc

<p class="message-warning"><strong>BC Break:</strong> Starting with <code>version 8.0.0</code> This method returns an <code>Iterator</code>.</p>

`fetchAssoc` returns an `Iterator` of all rows. The rows themselves are associative arrays where the keys are a one dimension array. This array must only contain unique `string` and/or `scalar` values.

~~~php
<?php

public Reader::fetchAssoc(
    mixed $offset_or_keys = 0,
    callable $callable = null
): Iterator
~~~

This `$offset_or_keys` argument can be

- a non empty array directly provided;
- a specific CSV row by providing its offset;

### Example 1 - Using an array to specify the keys

~~~php
<?php

use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/my/file.csv');
$keys = ['firstname', 'lastname', 'email'];
$results = $reader->fetchAssoc($keys);
// $results is an iterator
foreach ($results as $row) {
// each row will have the following data
//       [
//             'firstname' => 'john',
//             'lastname' => 'doe',
//             'email' => 'john.doe@example.com',
//       ];
//
}
~~~

### Example 2 - Using a CSV offset

~~~php
<?php

$offset = 0;
$results = $reader->fetchAssoc($offset);
// $results is an iterator
foreach ($results as $row) {
// each row will have the following data
//     [
//         'john' => 'jane',
//         'doe' => 'doe',
//         'john.doe@example.com' => 'jane.doe@example.com',
//     ];
//
}
~~~

### Notes

- If the number of values in a CSV row is lesser than the number of named keys, the method will add `null` values to compensate for the missing values.
- If the number of values in a CSV row is greater that the number of named keys the exceeding values will be drop from the result set.
- If an offset is used, its content will be skipped in the result set.
- If no argument is provided, the first row from the CSV data will be used

### The optional callable argument

<p class="message-warning"><strong>BC Break:</strong> The <code>callable</code> expects a row with the indexes already applied to it.</p>

The method takes an optional callable which signature is as follow:

~~~php
function(array $row [, int $rowOffset [, Iterator $iterator]]): array
~~~

- `$row`: the CSV current row combined with the submitted indexes **(new in version 8.0.0)**
- `$rowOffset`: the CSV current row offset
- `$iterator`: the current CSV iterator

### Example 3 - Using a callable

~~~php
<?php

use League\Csv\Reader;

$func = function ($row) {
    $row['date'] = DateTimeImmutable::createFromFormat($row['date'], 'd-m-Y');

    return $row;
};
$keys = ['firstname', 'lastname', 'date'];
$reader = Reader::createFromPath('/path/to/my/file.csv');
foreach ($reader->fetchAssoc($keys, $func) as $row) {
    $row['date']->format('Y-m-d H:i:s');
    //because this cell contain a `DateTimeInterface` object
}
~~~

## Reader::fetchColumn

<p class="message-warning"><strong>BC Break:</strong> Starting with <code>version 8.0.0</code> This method returns a <code>Iterator</code>.</p>

`fetchColumn` returns a `Iterator` of all values in a given column from the CSV data.

~~~php
<?php

public Reader::fetchColumn(
    int $columnIndex = 0,
    callable $callable = null
): Iterator
~~~

If for a given row the column does not exist, the row will be skipped.

### Example 1 - with a given column index

~~~php
<?php

use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/my/file.csv');
$result = $reader->fetchColumn(2);
$data = iterator_to_array($result, false);
// will return something like this :
//
// ['john.doe@example.com', 'jane.doe@example.com', ...]
//
~~~

### The optional callable argument

<p class="message-warning"><strong>BC Break:</strong> The <code>callable</code> expects the column value as its first parameter</p>

The method takes an optional callable which signature is as follow:

~~~php
<?php

function(string $value [, int $offsetIndex [, Iterator $iterator]]): mixed
~~~

- `$value`: the CSV current column value **(new to version 8.0.0)**
- `$offsetIndex`: the CSV current row offset
- `$iterator`: the current CSV iterator

### Example 2 - with a callable

~~~php
<?php

use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/my/file.csv');
foreach ($reader->fetchColumn(2, 'strtoupper') as $value) {
    echo $value; //display 'JOHN.DOE@EXAMPLE.COM'
}
~~~

## Reader::fetchPairs

<p class="message-notice">new feature introduced in <code>version 8.0</code></p>

The `fetchPairs` method returns a `Generator` of key-value pairs.

~~~php
<?php

public Reader::fetchPairs(
    int $offsetIndex = 0,
    int $valueIndex = 1,
    callable $callable = null
): Generator
~~~

- The key is taken from the submitted column index parameter (ie: `$offsetIndex`).
- The value is taken from the submitted column value parameter (ie: `$valueIndex`).

### Example 1 - default usage

~~~php
<?php

use League\Csv\Reader;

$str = <<EOF
john,doe
jane,doe
foo,bar
EOF;

$reader = Reader::createFromString($str);
foreach ($reader->fetchPairs() as $firstname => $lastname) {
    // - first iteration
    // echo $firstname; -> 'john'
    // echo $lastname;  -> 'doe'
    // - second iteration
    // echo $firstname; -> 'jane'
    // echo $lastname;  -> 'doe'
    // - third iteration
    // echo $firstname; -> 'foo'
    // echo $lastname; -> 'bar'
}
~~~

### Notes

- If no `$offsetIndex` is provided it default to `0`;
- If no `$valueIndex` is provided it default to `1`;
- If no cell is found corresponding to `$offsetIndex` the row is skipped;
- If no cell is found corresponding to `$valueIndex` the `null` value is used;

### The optional callable argument

The method takes an optional callable which signature is as follow:

~~~php
<?php

function(array $pairs [, int $rowOffset [, Iterator $iterator]]): array
~~~

- `$pairs`: an array where
    - the first value contains the value of the offset column index
    - the second value contains the value of the value column index
- `$rowOffset`: the CSV current row offset
- `$iterator`: the current CSV iterator

### Example 2 - with a callable

~~~php
<?php

use League\Csv\Reader;

$str = <<EOF
john,doe
jane,doe
foo,bar
EOF;

$func = function ($row) {
    return [
        strtoupper($row[0]),
        strtolower($row[1]),
    ];
}
$reader = Reader::createFromString($str);
foreach ($reader->fetchPairs(1, 0, $func) as $lastname => $firstname) {
    // - first iteration
    // echo $lastname; -> 'DOE'
    // echo $firstname; -> 'john'
    // - second iteration
    // echo $lastname; -> 'DOE'
    // echo $firstname; -> 'jane'
    // - third iteration
    // echo $lastname; -> 'BAR'
    // echo $firstname; -> 'foo'
}
~~~

## Reader::fetchPairsWithoutDuplicates

<p class="message-notice">new feature introduced in <code>version 8.0</code></p>

The `fetchPairsWithoutDuplicates` method returns data in an `array` of key-value pairs, as an associative array with a single entry per row.

~~~php
<?php

public Reader::fetchPairsWithoutDuplicates(
    int $offsetIndex = 0,
    int $valueIndex = 1,
    callable $callable = null
): array
~~~

`fetchPairsWithoutDuplicates` behaves exactly like `fetchPairs` with two differences:

- `fetchPairsWithoutDuplicates` returns an `array`
- When using `fetchPairsWithoutDuplicates` entries in the associative array will be overwritten if there are duplicates values in the column index.

~~~php
<?php

$str = <<EOF
john,doe
jane,doe
foo,bar
EOF;

$reader = Reader::createFromString($str);
$data = $reader->fetchPairsWithoutDuplicates(1, 0);
// will return ['doe' => 'jane', 'foo' => 'bar'];
// the 'john' value has been overwritten by 'jane'
~~~
