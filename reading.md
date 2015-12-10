---
layout: default
title: Extracting data from a CSV
---

# Extracting data

To extract data from a CSV document use `League\Csv\Reader` methods.

### fetch

The `fetch` method fetches the next row from the `Iterator` result set.

~~~php
public Reader::fetch(callable $callable = null): Iterator
~~~

The method takes an optional callable parameter to apply to each row of the resultset before returning. The callable signature is as follow:

~~~php
$callable(array $row, int $rowOffset, Iterator $iterator): array
~~~

- `$row`: the CSV current row as an array
- `$rowOffset`: the CSV current row offset
- `$iterator`: the current CSV iterator

#### Example 1

~~~php
$results = $reader->fetch();
foreach ($reader->fetch() as $row) {
    //do something here
}
~~~

#### Example 2 - with a callable

~~~php
$func = function ($row) {
    return array_map('strtouper', $row);
}
$results = $reader->fetch($func);
foreach ($reader->fetch() as $row) {
    //each row member will be uppercased
}
~~~

### fetchAll

`fetchAll` returns a sequential `array` of all rows.

~~~php
public Reader::fetch(callable $callable = null): array
~~~

`fetchAll` behaves exactly like `fetch` with one difference:

- `fetchAll` returns an `array`.

### fetchOne

`fetchOne` return one single row from the CSV data as an `array`.

~~~php
public Reader::fetchOne($offset = 0): array
~~~

The required argument `$offset` represents the row index starting at `0`. If no argument is given the method will return the first row from the CSV data.

#### Example

~~~php
$data = $reader->fetchOne(3); ///accessing the 4th row (indexing starts at 0)
// will return something like this :
//
//   ['john', 'doe', 'john.doe@example.com']
//
~~~

### each

`each` applies a callable function on each CSV row.

~~~php
public Reader::each(callable $callable): int
~~~

The method returns the number of successful iterations.

The callable signature is as follow:

~~~php
$callable(array $row, int $rowOffset, Iterator $iterator): bool
~~~

- `$row`: the CSV current row as an array
- `$rowOffset`: the CSV current row offset
- `$iterator`: the current CSV iterator

The callable must return `true` to continue iterating over the CSV;

#### Example - Counting the CSV total number of rows

~~~php
//count the numbers of rows in a CSV
$nbRows = $reader->each(function ($row) {
    return true;
});
~~~

### fetchAssoc

<p class="message-warning"><strong>BC Break:</strong> Starting with <code>version 8.0.0</code> This method returns a <code>Iterator</code>.</p>

`fetchAssoc` returns an `Iterator` of all rows. The rows themselves are associative arrays where the keys are a one dimension array. This array must only contain unique `string` and/or `scalar` values.

~~~php
public Reader::fetchAssoc(
    mixed $offset_or_keys = 0,
    callable $callable = null
): Iterator
~~~

This `$offset_or_keys` argument can be

- a non empty array directly provided;
- a specific CSV row by providing its offset;

#### Example 1 - Using an array to specify the keys

~~~php
$data = $reader->fetchAssoc(['firstname', 'lastname', 'email']);
// will return something like this :
//
// [
//   ['firstname' => 'john', 'lastname' => 'doe', 'email' => 'john.doe@example.com'],
//   ['firstname' => 'jane', 'lastname' => 'doe', 'email' => 'jane.doe@example.com'],
//   ['firstname' => 'fred', 'lastname' => 'doe', 'email' => 'fred.doe@example.com'],
//   ...
// ]
//
~~~

#### Example 2 - Using a CSV offset

~~~php
$data = $reader->fetchAssoc(0);
// will return something like this :
//
// [
//   ['john' => 'jane', 'doe' => 'doe', 'john.doe@example.com' => 'jane.doe@example.com'],
//   ['john' => 'fred', 'doe' => 'doe', 'john.doe@example.com' => 'fred.doe@example.com'],
//   ...
// ]
//
~~~

#### Notes

- If the number of values in a CSV row is lesser than the number of named keys, the method will add `null` values to compensate for the missing values.
- If the number of values in a CSV row is greater that the number of named keys the exceeding values will be drop from the result set.
- If an offset is used, it's content will be skipped in the result set.
- If no argument is provided, the first row from the CSV data will be used

#### The optional callable argument

<p class="message-warning"><strong>BC Break:</strong> The <code>callable</code> expects a row with the indexes already applied to it.</p>

The method takes an optional callable which signature is as follow:

~~~php
$callable(array $row, int $rowOffset, Iterator $iterator): array
~~~

- `$row`: the CSV current row combined with the submitted indexes **(new in version 8.0.0)**
- `$rowOffset`: the CSV current row offset
- `$iterator`: the current CSV iterator

#### Example 3 - Using a callable

~~~php
$func = function ($row) {
    $row['date'] => DateTimeImmutable::createFromFormat($row['date'], 'd-m-Y');
};
foreach ($reader->fetchAssoc(['firstname', 'lastname', 'date']) as $row) {
    $row['date']->format('Y-m-d H:i:s');
    //because this cell contain a `DateTimeInterface` object
}
~~~

### fetchColumn

<p class="message-warning"><strong>BC Break:</strong> Starting with <code>version 8.0.0</code> This method returns a <code>Iterator</code>.</p>

`fetchColumn` returns a `Iterator` of all values in a given column from the CSV data.

~~~php
public Reader::fetchColumn(
    int $columnIndex = 0,
    callable $callable = null
): Iterator
~~~

If for a given row the column does not exist, the row will be skipped.

#### Example 1 - with a given column index

~~~php
$data = $reader->fetchColumn(2);
// will return something like this :
//
// ['john.doe@example.com', 'jane.doe@example.com', ...]
//
~~~

#### The optional callable argument

<p class="message-warning"><strong>BC Break:</strong> The <code>callable</code> expects the column value as its first parameter</p>

The method takes an optional callable which signature is as follow:

~~~php
$callable(string $value, int $offsetIndex, Iterator $iterator): mixed
~~~

- `$value`: the CSV current column value **(new to version 8.0.0)**
- `$offsetIndex`: the CSV current row offset
- `$iterator`: the current CSV iterator

#### Example 2 - with a callable

~~~php
foreach ($reader->fetchColumn(2, 'strtoupper') as $value) {
    echo $value; //display 'JOHN.DOE@EXAMPLE.COM'
}
~~~

### fetchPairs

<p class="message-notice">new feature introduced in <code>version 8.0</code></p>

The `fetchPairs` method returns data in an `Iterator` of key-value pairs, as an associative array with a single entry per row.

~~~php
public Reader::fetchPairs(
    int $offsetIndex = 0,
    int $valueIndex = 1,
    callable $callable = null
): Iterator
~~~

In this associative array:

- The key is taken from the submitted column index parameter (ie: `$offsetIndex`).
- The value is taken from the submitted column value parameter (ie: `$valueIndex`).

#### Example 1 - default usage

~~~php
$data = $reader->fetchPairs(1, 4);
// will return something like this :
// [
//   'john' => 'doe',
//   'jane' => 'doe',
//   ...
// ];
~~~

#### Notes

- If no `$offsetIndex` is provided it default to `0`;
- If no `$valueIndex` is provided it default to `1`;
- If no cell is found corresponding to `$offsetIndex` the row is skipped;
- If no cell is found corresponding to `$valueIndex` the `null` value is used;

#### The optional callable argument

The method takes an optional callable which signature is as follow:

~~~php
$callable(array $pairs, int $rowOffset, Iterator $iterator): array
~~~

- `$pairs`: an array where
    - the first value contains the value of the offset column index
    - the second value contains the value of the value column index
- `$rowOffset`: the CSV current row offset
- `$iterator`: the current CSV iterator

#### Example 2 - with a callable

~~~php
$func = function ($row) {
    return [
        strtoupper($row[0]),
        strtolower($row[1]),
    ];
}
foreach ($reader->fetchPairs() as $firstname => $lastname) {
    // echo $firstname; // 'JOHN'
    // echo $lastname; // 'doe'
}
~~~


~~~php
$data = $reader->fetchPairsWithoutDuplicates(1, 0);
// will return something like this :
// [
//   'doe' => 'jane',
//   ...
// ];
~~~

### fetchPairsWithoutDuplicates

<p class="message-notice">new feature introduced in <code>version 8.0</code></p>

The `fetchPairsWithoutDuplicates` method returns data in an `array` of key-value pairs, as an associative array with a single entry per row.

~~~php
public Reader::fetchPairsWithoutDuplicates(
    int $offsetIndex = 0,
    int $valueIndex = 1,
    callable $callable = null
): array
~~~

`fetchPairsWithoutDuplicates` behaves exactly like `fetchPairs` with two differences:

- `fetchPairsWithoutDuplicates` returns an `Array`
- When using `fetchPairsWithoutDuplicates` entries in the associative array will be overwritten if there are duplicates values in the column index.