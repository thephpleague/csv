---
layout: default
title: Extracting data from a CSV
---

# Extracting data

To extract data from a CSV document use `League\Csv\Reader` methods.

### fetch(callable $callable = null)

<p class="message-notice">This method is introduced in version <code>7.2.0</code></p>

The `fetch` method Fetches the next row from the `Iterator` result set.

~~~php
$results = $reader->fetch();
foreach ($reader->fetch() as $row) {
    //do something here
}
~~~

The method takes an optional parameter, a callable, to apply to each row of the results before returning. This callable expected:

- the CSV current row as an array
- the CSV current row offset
- the current iterator

~~~php
$func = function ($row) {
    return array_map('strtouper', $row);
}
$results = $reader->fetch($func);
foreach ($reader->fetch() as $row) {
    //each row member will be uppercased
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

The method takes an optional parameter, a callable, to apply to each row of the results before returning. This callable expected:

- the CSV current row as an array
- the CSV current row offset
- the current iterator

~~~php
$func = function ($row) {
    return array_map('strtouper', $row);
}
$data = $reader->fetchAll($func);
// will return something like this :
//
// [
//   ['JOHN', 'DOE', 'JOHN.DOE@EXAMPLE.COM'],
//   ['JANE', 'DOE', 'JANE.DOE@EXAMPLE.COM'],
//   ...
// ]
//
$nb_rows = count($data);
~~~

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
* can take up to three parameters:
    * the current csv row data;
    * the current csv key;
    * the current csv iterator object;

The method returns the number of successful iterations.

~~~php
//re-create the fetchAll method using the each method
$res = [];
$func = null;
$nbIteration = $reader->each(function ($row, $index, $iterator) use (&$res, $func)) {
    if (is_callable($func)) {
        $res[] = $func($row, $index, $iterator);
        return true;
    }
    $res[] = $row;
    return true;
});
~~~

#### Fetching modes

To enable dealing with huge CSV files in an efficient way version 8.0.0 introduces a fetch mode to modify the return type for the remaining methods describe hereafter. The `Reader` exposes these modes through the uses of two new constants:

- `Reader::FETCH_ARRAY`: When set the next call to one of these method will return an `Array`;
- `Reader::FETCH_ITERATOR`: When set the next call to one of these method will return an `Iterator`;

By default, the `Reader` fetch mode is set to `Reader::FETCH_ARRAY`.

~~~php
$reader->getFetchMode(); //returns Reader::FETCH_ARRAY
$reader->setFetchMode(Reader::FETCH_ITERATOR);
$reader->getFetchMode(); //returns Reader::FETCH_ITERATOR
$result = $reader->fetchAssoc(); //$result is an iterator
$reader->getFetchMode(); //returns Reader::FETCH_ARRAY //once the query is issued the fetch mode is resetted to Reader::FETCH_ARRAY
~~~

### fetchAssoc($offset_or_keys = 0, callable $callable = null)

`fetchAssoc` returns a sequential array of all rows. The rows themselves are associative arrays where the keys are an one dimension array. This array must only contain unique `string` and/or `integer` values.

This array keys can be specified as the first argument as

- a specific CSV row by providing its offset; **(since version 6.1)**
- a non empty array directly provided;

Using a non empty array:

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

Using a specific offset:

~~~php
$data = $reader->fetchAssoc();
// will return something like this :
//
// [
//   ['john' => 'jane', 'doe' => 'doe', 'john.doe@example.com' => 'jane.doe@example.com'],
//   ['john' => 'fred', 'doe' => 'doe', 'john.doe@example.com' => 'fred.doe@example.com'],
//   ...
// ]
//
~~~

Of note:

- If the number of values in a CSV row is lesser than the number of named keys, the method will add `null` values to compensate for the missing values.
- If the number of values in a CSV row is greater that the number of named keys the exceeding values will be drop from the result set.
- If no argument is provided, the first row from the CSV data will be used
- If an offset is used, it's content will be skipped in the result set.

The method takes an second optional parameter, a callable, to apply to each row of the results before returning. This callable expected:

- the CSV current row with the indexes already attached **(new to version 8.0.0)**
- the CSV current row offset
- the current iterator

<div class="message-warning">
<strong>BC break starting with <code>version 8.0</code> The <code>callable</code> expects a row with the index already applied to it.</strong>
</div>

~~~php
$func = function ($row) {
    $row['data'] => DateTimeImmutable::createFromFormat($row['date'], 'd-m-Y');
};
$data = $reader->fetchAssoc(['firstname', 'lastname', 'date']);
$data[0]['date']->format('Y-m-d H:i:s'); //because this cell contain a `DateTimeInterface` object
~~~

### fetchColumn($columnIndex = 0, callable $callable = null)

`fetchColumn` returns a sequential array of all values in a given column from the CSV data.

If for a given row the column does not exist, the row will be skipped.

~~~php
$data = $reader->fetchColumn(2);
// will return something like this :
//
// ['john.doe@example.com', 'jane.doe@example.com', ...]
//
~~~

The method takes an second optional parameter, a callable, to apply to each row of the results before returning. This callable expected:

- the CSV current column value **(new to version 8.0.0)**
- the CSV current row offset
- the current iterator

<div class="message-warning">
<strong>BC break starting with <code>version 8.0</code> The <code>callable</code> expects the column value as its first parameter</strong>
</div>

~~~php
$data = $reader->fetchColumn(2, 'strtoupper');
// will return something like this :
//
// ['JOHN.DOE@EXAMPLE.COM', 'JANE.DOE@EXAMPLE.COM', ...]
//
~~~

### fetchPairs($offsetColumnIndex = 0, $valueColumnIndex = 1, callable $callable = null)

The `fetchPairs` method returns data in an array of key-value pairs, as an associative array with a single entry per row. The key of this associative array is taken from the submitted column index parameter. If not parameter is given the first CSV column will be used. The value is taken from the submitted column value parameter. If no parameter is given the second CSV column is used.

~~~php
$data = $reader->fetchPairs();
// will return something like this :
// [
//   'john' => 'doe',
//   'jane' => 'doe',
//   ...
// ];
~~~

The method takes a third optional parameter, a callable, to apply to each row of the results before returning. This callable expected:

- an array containing two value, the first value represents the resulting offset and the second value the resulting value.
- the CSV current row offset
- the current iterator

~~~php
$func = function ($row) {
    return [
        strtoupper($row[0]),
        strtolower($row[1]),
    ];
}
$data = $reader->fetchPairs();
// will return something like this :
// [
//   'JOHN' => 'doe',
//   'JANE' => 'doe',
//   ...
// ];
~~~

<div class="message-warning">The behavior of this method changed with the mode selected:</div>

When using `Reader::fetchPairs`:

- with the `Reader::FETCH_ARRAY` mode if there are duplicates values in the column index, entries in the associative array will be overwritten.
- with the `Reader::FETCH_ITERATOR` no overwrite occurs.

~~~php
$reader->setFetchMode(READER::FETCH_ARRAY);
$data = $reader->fetchPairs(1, 0);
// will return something like this :
// [
//   'doe' => 'jane',
//   ...
// ];

$reader->setFetchMode(READER::FETCH_ITERATOR);
$data = $reader->fetchPairs(1, 0);
foreach($data as $key => $value) {
    //first row will have $key = 'doe' and value = 'john'
    //second row will have $key = 'doe' and value = 'jane'
}
~~~