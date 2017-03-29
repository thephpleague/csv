---
layout: default
title: CSV document constraint Builder
---

# Constraint Builder

~~~php
<?php

public Statement::where(callable $callable): self
public Statement::orderBy(callable $callable): self
public Statement::offset(int $offset): self
public Statement::limit(int $limit): self
public Statement::columns(array $columns): self
public Statement::process(Reader $reader): RecordSet
~~~

The `League\Csv\Statement` class is a constraint builder to help ease selecting records from a CSV document created using the `League\Csv\Reader` class.

When building a constraint, the methods do not need to be called in any particular order, and may be called multiple times. Because the `Statement` object is immutable, each time its constraint methods are called they will return a new `Statement` object without modifying the current `Statement` object.

<p class="message-info"><strong>Tips:</strong> Because the <code>Statement</code> object is independent of the <code>Reader</code> object it can be re-use on multiple <code>Reader</code> objects.</p>

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

`Statement::Limit` specifies an optional maximum records count for the return data. By default if no limit is provided the limit equals `-1`, which translate to all records.

<p class="message-notice">When called multiple times, each call override the last settings for these options.</p>

## Select constraint

This option enables mapping and selecting specific columns from each record.

~~~php
<?php

public Statement::columns(array $columns): self
~~~

The single parameter is an associative array where:

- the key represents the specified key from the `Reader`
- the value represents the key alias to be used by the `RecordSet` object.

The `Statement::columns` option is the last to be applied. So you can not use the alias with the `Statement::where` or the `Statement::orderBy` methods.

<p class="message-info"><strong>Tips:</strong> To reset the <code>columns</code> value, you need to provide an empty array.</p>

<p class="message-notice">When called multiple times, each call override the last settings for this option.</p>


### If the Reader object has no header

~~~php
<?php

use League\Csv\Statement;

$stmt = (new Statement())
    ->columns(['firstname', 'lastname', 'email'])
;

// is equivalent to:

$stmt = (new Statement())
    ->columns([
        0 => 'firstname',
        1 => 'lastname',
        2 => 'email',
    ])
;
~~~

### If the Reader object has a header

~~~php
<?php

use League\Csv\Statement;

$stmt = (new Statement())
    ->columns(['firstname', 'lastname', 'email'])
;

// is equivalent to:

$stmt = (new Statement())
    ->columns([
        'firstname' => 'firstname',
        'lastname' => 'lastname',
        'email' => 'email',
    ])
;
~~~

<p class="message-warning">If a <code>Reader</code> object has a header and the column uses undefined header value a <code>RuntimeException</code> is triggered.</p>

## Apply the constraints to a CSV document

~~~php
<?php

public Statement::process(Reader $reader): RecordSet
~~~

This method processes a [Reader](/9.0/reader/) object and returns the found records as a [RecordSet](/9.0/reader/records) object.

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

$reader = Reader::createFromPath('/path/to/file.csv');
$stmt = (new Statement())
    ->offset(3)
    ->limit(2)
    ->where('filterByEmail')
    ->orderBy('sortByLastName')
    ->columns(['firstname', 'lastname', 'email'])
;

$records = $stmt->process($reader);
~~~

<p class="message-info"><strong>Tips:</strong> this method is equivalent of <a href="/9.0/reader/#selecting-csv-records">Reader::select</a>.</p>

