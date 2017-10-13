---
layout: default
title: CSV document constraint Builder
---

# Constraint Builder

~~~php
<?php

class Statement
{
    public function limit(int $limit): self
    public function offset(int $offset): self
    public function orderBy(callable $callable): self
    public function process(Reader $reader, array $header = []): ResultSet
    public function where(callable $callable): self
}
~~~

The `League\Csv\Statement` class is a constraint builder to help ease selecting records from a CSV document created using the `League\Csv\Reader` class.

When building a constraint, the methods do not need to be called in any particular order, and may be called multiple times. Because the `Statement` object is immutable, each time its constraint methods are called they will return a new `Statement` object without modifying the current `Statement` object.

<p class="message-info">Because the <code>Statement</code> object is independent of the <code>Reader</code> object it can be re-use on multiple <code>Reader</code> objects.</p>

## Filtering constraint

The filters attached using the `Statement::where` method **are the first settings applied to the CSV before anything else**. This option follow the *First In First Out* rule.

~~~php
<?php

public Statement::where(callable $callable): self
~~~

The callable filter signature is as follows:

~~~php
<?php

function(array $record [, int $offset [, Iterator $iterator]]): self
~~~

It takes up to three parameters:

- `$record`: the CSV current record as an array
- `$offset`: the CSV current record offset
- `$iterator`: the current CSV iterator

## Sorting constraint

The sorting options are applied **after the Statement::where options**. The sorting follows the *First In First Out* rule.

<p class="message-warning"><strong>Warning:</strong> To sort the data <code>iterator_to_array</code> is used, which could lead to a performance penalty if you have a heavy CSV file to sort
</p>


`Statement::orderBy` method adds a sorting function each time it is called.

~~~php
<?php

public Statement::orderBy(callable $callable): self
~~~

The callable sort function signature is as follows:

~~~php
<?php

function(array $recordA, array $recordB): int
~~~

The sort function takes exactly two parameters, which will be filled by pairs of records.

## Interval constraint

The interval methods enable returning a specific interval of CSV records. When called more than once, only the last filtering settings is taken into account. The interval is calculated **after applying Statement::orderBy options**.

The interval API is made of the following method

~~~php
<?php

public Statement::offset(int $offset): self
public Statement::limit(int $limit): self
~~~

`Statement::offset` specifies an optional offset for the return data. By default if no offset was provided the offset equals `0`.

`Statement::limit` specifies an optional maximum records count for the return data. By default if no limit is provided the limit equals `-1`, which translate to all records.

<p class="message-notice">When called multiple times, each call override the last settings for these options.</p>

## Processing a CSV document

~~~php
<?php

public Statement::process(Reader $reader, array $header = []): ResultSet
~~~

This method processes a [Reader](/9.0/reader/) object and returns the found records as a [ResultSet](/9.0/reader/resultset) object.

~~~php
<?php

use League\Csv\Reader;
use League\Csv\Statement;

function filterByEmail(array $record): bool
{
    return (bool) filter_var($record[2], FILTER_VALIDATE_EMAIL);
}

function sortByLastName(array $recordA, array $recordB): int
{
    return strcmp($recordB[1], $recordA[1]);
}

$reader = Reader::createFromPath('/path/to/file.csv', 'r');
$stmt = (new Statement())
    ->offset(3)
    ->limit(2)
    ->where('filterByEmail')
    ->orderBy('sortByLastName')
;

$records = $stmt->process($reader);
~~~

Just like the `Reader:getRecords`, the `Statement::process` method takes an optional `$header` argument to allow mapping CSV fields name to user defined header record.

~~~php
<?php

use League\Csv\Reader;
use League\Csv\Statement;

function filterByEmail(array $record): bool
{
    return (bool) filter_var($record[2], FILTER_VALIDATE_EMAIL);
}

function sortByLastName(array $recordA, array $recordB): int
{
    return strcmp($recordB[1], $recordA[1]);
}

$reader = Reader::createFromPath('/path/to/file.csv', 'r');
$stmt = (new Statement())
    ->offset(3)
    ->limit(2)
    ->where('filterByEmail')
    ->orderBy('sortByLastName')
;

$records = $stmt->process($reader, ['firstname', 'lastname', 'email']);
~~~
