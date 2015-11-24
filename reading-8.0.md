---
layout: default
title: Extracting data from a CSV
---

# Extracting data

To extract data from a CSV document use `League\Csv\Reader` methods.

## Return types

To enable dealing with huge CSV files in an efficient way version 8.0.0 introduces a way to modify the return type for some of its methods. By default the `Reader` methods will return an `array` which is optimized for small CSV. You can now set the method to return a `Iterator` to save memory when dealing with larger files. For the feature, the `Reader` exposes two new constants:

- `Reader::TYPE_ARRAY`: When set the next call to a supported method will return an `Array`;
- `Reader::TYPE_ITERATOR`: When set the next call to a supported method will return an `Iterator`;

By default, the return type is set to `Reader::TYPE_ARRAY`.

At any given time you can access the return type to be used on the next call using the feature getter method.

~~~php
$reader->getReturnType(); //returns Reader::TYPE_ARRAY
$reader->setReturnType(Reader::TYPE_ITERATOR);
$reader->getReturnType(); //returns Reader::TYPE_ITERATOR
$result = $reader->fetchAssoc(); //$result is an iterator
$reader->getReturnType(); //returns Reader::TYPE_ARRAY
//everytime a query is issued the return type is resetted to Reader::TYPE_ARRAY
~~~

### fetch(callable $callable = null)

<p class="message-info">This method <strong>is not affected</strong> by the <code>Reader</code> return type.</p>

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

<p class="message-info">This method <strong>is not affected</strong> by the <code>Reader</code> return type.</p>

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

<p class="message-info">This method <strong>is not affected</strong> by the <code>Reader</code> return type.</p>

`fetchOne` return one single row from the CSV data. The required argument $offset represent the row index starting at 0. If no argument is given to the method it will return the first row from the CSV data.

~~~php
$data = $reader->fetchOne(3); ///accessing the 4th row (indexing starts at 0)
// will return something like this :
//
//   ['john', 'doe', 'john.doe@example.com']
//
~~~

### each(callable $callable)

<p class="message-info">This method <strong>is not affected</strong> by the <code>Reader</code> return type.</p>

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

### fetchAssoc($offset_or_keys = 0, callable $callable = null)

<p class="message-notice">This method <strong>is affected</strong> by the <code>Reader</code> return type feature.</p>

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

<p class="message-notice">This method <strong>is affected</strong> by the <code>Reader</code> return type feature.</p>

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

<p class="message-notice">This method <strong>is affected</strong> by the <code>Reader</code> return type feature.</p>

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

<div class="message-warning">The behavior of this method changed with the return type selected:</div>

- When using `Reader::TYPE_ARRAY` if there are duplicates values in the column index, entries in the associative array will be overwritten.
- When using `Reader::TYPE_ITERATOR` no overwrite occurs as the return type is created using a PHP `Generator`.

~~~php
$reader->setReturnType(READER::TYPE_ARRAY);
$data = $reader->fetchPairs(1, 0);
// will return something like this :
// [
//   'doe' => 'jane',
//   ...
// ];

$reader->setReturnType(READER::TYPE_ITERATOR);
$data = $reader->fetchPairs(1, 0);
foreach($data as $key => $value) {
    //first row will have $key = 'doe' and value = 'john'
    //second row will have $key = 'doe' and value = 'jane'
}
~~~