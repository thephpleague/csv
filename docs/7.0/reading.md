---
layout: default
title: Extracting data from a CSV
---

# Extracting data

To extract data from a CSV document use `League\Csv\Reader` methods.

## Fetching CSV data

### query(callable $callable = null)

<p class="message-warning">This method is deprecated since version <code>7.2.0</code> and will be remove in the next major release</p>

The `query` method prepares and issues queries on the CSV data. It returns an `Iterator` that represents the result that you can further manipulate as you wish.

~~~php
<?php

$data = $reader->query();
foreach ($data as $lineIndex => $row) {
    //do something here
}
~~~

### fetch(callable $callable = null)

<p class="message-notice">This method is introduced in version <code>7.2.0</code></p>

The `fetch` method returns an `Iterator`.

~~~php
<?php

foreach ($reader->fetch() as $row) {
    //do something here
}
~~~

### fetchAll(callable $callable = null)

`fetchAll` returns a sequential array of all rows.

~~~php
<?php

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

`fetchAssoc` returns a sequential array of all rows. The rows themselves are associative arrays where the keys are an one dimension array. This array must only contain unique `string` and/or `integer` values.

This array keys can be specified as the first argument as

- a specific CSV row by providing its offset; **(since version 6.1)**
- a non empty array directly provided;

Using a non empty array:

~~~php
<?php

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
<?php

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
- If an offset is used, its content will be skipped in the result set.

### fetchColumn($columnIndex = 0, callable $callable = null)

`fetchColumn` returns a sequential array of all values in a given column from the CSV data.

If for a given row the column does not exist, the row will be skipped.

~~~php
<?php

$data = $reader->fetchColumn(2);
// will return something like this :
//
// ['john.doe@example.com', 'jane.doe@example.com', ...]
//
~~~

<div class="message-warning">
<strong>BC break starting with <code>version 7.0</code> :</strong>
<ul>
<li>This method no longer adds <code>null</code> on an non existing column index.</li>
<li>The cell skipping is done on the callable result.</li>
</ul>
</div>

### Using a callable to modify the returned resultset

The methods listed above (`query`, `fetchAll`, `fetchAssoc`, `fetchColumn`) can all take a optional `callable` argument to further manipulate each row before being returned. This callable function can take up to three parameters:

* the current csv row data
* the current csv key
* the current csv iterator object

~~~php
<?php

$data = $reader->fetchAll(function ($row) {
    return array_map('strtoupper', $row);
});
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

<p class="message-warning">In case of the <code>fetchAssoc</code> method, it's the callable result which is combine to the array key.</p>

### fetchOne($offset = 0)

`fetchOne` return one single row from the CSV data. The required argument $offset represent the row index starting at 0. If no argument is given to the method it will return the first row from the CSV data.

~~~php
<?php

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
<?php

<?php
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
