---
layout: default
title: CSV document constraint Builder
---

# Constraint Builders

The package provides two (2) convenient ways to query the `Reader` and the `ResultSet` instances. They
can be used to perform manipulation independently of the instance giving you more controls over
which records you want to access from your input document.

## Statement

The first mechanism is the `League\Csv\Statement` class which is a constraint builder that more or less
mimic the behaviour of query builders in the database world. It can filter, order and limit the records
to be shown. It does so by adding and combining constraints. Once the constraint is built, it will
process your input and always return a [ResultSet](/9.0/reader/resultset) instance. Of note, the resulting constraint
can be applied on multiple documents as the instance is immutable and completely independent of
the input.

### Retrieving all the rows

<p class="message-info">Starting with version <code>9.6.0</code>, the class exposes the
<code>Statement::create</code> named constructor to ease object creation.</p>

To start using the `Statement` class you should use the `create` method. It returns a valid instance
ready to already process your document or on which you can add more constraints. Because the
`Statement` object is immutable, each time its constraint methods are called they will
return a new `Statement` object without modifying the current `Statement` object.
Once your constraint is ready to be used, use its `process` method on a `TabularDataReader` class.

```php
use League\Csv\Reader;
use League\Csv\Statement;

$reader = Reader::createFromPath('/path/to/file.csv');
$records = Statement::create()->process($reader);
// $records is a League\Csv\ResultSet instance
```

The `process` method returns a new `TabularDataReader` on which each constraint have been applied.
If no constraint has been added the return object will contain the same data as its input.

### Where clauses

To filter the records from your input you may use the `where` method. The method can be
called multiple time and each time it will add another constraint filter. This option
follows the *First In First Out* rule. The filter excepts a callable with the following
signature:

```php
function(array $record, string|int $key): bool;
```

<p class="message-notice">The <code>$key</code> argument is optional and is only used if you want to filter the row over its offset.</p>

If you omit the `$key` argument, the callable is similar to the one used by `array_filter`.
For example the following filter will remove all the records whose `3rd` field does not
contain a valid `email`:

```php
use League\Csv\Reader;
use League\Csv\Statement;

$reader = Reader::createFromPath('/path/to/file.csv');
$records = Statement::create()
    ->where(fn (array $record): bool => false !== filter_var($record[2] ?? '', FILTER_VALIDATE_EMAIL))
    ->process($reader);
// $records is a League\Csv\ResultSet instance
```

<p class="message-info">New since version <code>9.16.0</code></p>

To ease the `Statement::where` usage the following methods are introduced: `andWhere`, `whereNot`, `orWhere` and `xorWhere`;

These methods are used to filter the record based on their columns value. Instead of using a callable,
the methods require three (3) arguments. The first argument is the column to filter on. It can be
as a string (the column name, if it exists) or an integer (the column offset, negative indexes
are supported). The second argument is a valid comparison operator in a case-insensitive
way. The third argument is the value you want to compare the column value with.

As an example the `Statement` instance below will select the records whose 2nd cell value
is the integer `10` or where the `birthdate` column contains a date string representation
that match the submitted regular expression.

```php
use League\Csv\Reader;
use League\Csv\Statement;

$reader = Reader::createFromPath('/path/to/file.csv');
$records = Statement::create()
    ->andWhere(1, '=', '10') //filtering is done of the second column
    ->orWhere('birthdate', 'regexp', '/\d{1,2}\/\d{1,2}\/\d{2,4}/') //filtering is done on the `birthdate` column
    ->whereNot('firstname', 'starts_with', 'P') //filtering is done case-sensitively on the first character of the column value
    ->process($reader);
// $records is a League\Csv\ResultSet instance
```

The methods support the basic comparison operators using their strict version in PHP:

- equals: `=` or `EQ`, `IS`, or `EQUAL`;
- not equals: `!=`, `<>`, `NEQ`, `IS NOT` or `NOT EQUAL`;
- greater than: `>`, `GT` or `GREATER THAN`;
- greater than or equal: `>=`, `GTE` or `GREATER THAN OR EQUAL`;
- lesser than: `>`, `LT` or `LESSER THAN`;
- lesser than or equal: `>=`, `LTE` or `LESSER THAN OR EQUAL`;

The following parameter can only be used if the submitted value is an `array`
as PHP's `in_array` function is used for comparison. If the value to compare
is a scalar value or `null`, `in_array` is used on strict mode otherwise the
comparison is relaxed.

- in: `IN`;
- not in: `NIN`;

```php
use League\Csv\Statement;

$constraints = Statement::create()->orWhere('direction', 'not in', ['east', 'north']);
```

The following parameter can only be used if the submitted value is a tuple
represented as a PHP's `array` as a list where the first argument represents
the range minimal value and the second argument, the range maximal value.

- between: `BETWEEN`;
- not between: `NBETWEEN`, `NOT_BETWEEN` or `NOT BETWEEN`;

```php
use League\Csv\Statement;

$constraints = Statement::create()->andWhere('points', 'between', [3, 5]);
```

The following parameters can only be used if the submitted value **and** the column value are `string`.

- contains: `CONTAINS`;
- does not contain: `NCONTAIN`, `NOT_CONTAIN`, `NOT CONTAIN`, `DOES NOT CONTAIN`;
- starts with: `STARTS_WITH`;
- end with: `ENDS_WITH`;
- regexp: `REGEXP`;
- not regexp: `NREGEXP`, `NOT_REGEXP`, `NOT REGEXP`;

Internally they use one of the following PHP's function `str_contains`, `str_starts_with`,
`str_ends_wtih` or `preg_match`.

- All operators can be written in a case-insensitive way.
- If the operator is unknown or invalid, a `StatementError` exception will be triggered.
- If the specified column could not be found during process an `StatementError` exception is triggered;
- If the `value` is incorrect according to the operator constraints an `InvalidArgument` exception will be triggered.

For complex constraints you can, instead of specifying an simple operator, choose to specificy
a callback. In that case the callback method will be evaluated with the value of the specified
column.

```php
use League\Csv\Reader;
use League\Csv\Statement;

$curDate = new DateTimeImmutable();

$reader = Reader::createFromPath('/path/to/file.csv');
$records = Statement::create()
    ->andWhere(1, '=', '10') //filtering is done of the second column
    ->orWhere('birthdate', fn (string $value): bool => DateTimeImmutable::createFromFormat('Y-m-d', $value) < $curDate) //filtering is done on the `birthdate` column
    ->whereNot('firstname', 'starts_with', 'P') //filtering is done case-sensitively on the first character of the column value
    ->process($reader);
// $records is a League\Csv\ResultSet instance
```

To enable comparing two columns with each other the following methods are also added:
`andWhereColumn`, `whereNotColumn`, `orWhereColumn` and `xorWhereColumn`

The only distinction with their value counterparts is in the third argument. Instead of specifying
a value, it specifies another column (via its string name or integer name) to compare columns
with each other.

```php
use League\Csv\Reader;
use League\Csv\Statement;

$reader = Reader::createFromPath('/path/to/file.csv');
$records = Statement::create()
    ->andWhereColumn('created_at', '<', 'update_at') //filtering is done on both column value
    ->whereNotColumn('fullname', 'starts_with', 4)   //filtering is done on both column but the second column is specified via its offset
    ->process($reader);
// $records is a League\Csv\ResultSet instance
```

For complex constraints you can, instead of specifying an simple operator, choose to specificy
a callback. In that case the callback method will be evaluated with the value of both columns.

```php
use League\Csv\Reader;
use League\Csv\Statement;

$reader = Reader::createFromPath('/path/to/file.csv');
$records = Statement::create()
     ->andWhereColumn('created_at', '<', 'update_at') //filtering is done on both column value
    ->andWhereOffset(
        'fullname', 
        fn (string $valuefirst, string $valueSecond): bool =>  strlen($valuefirst) != strlen($valueSecond), 
        4
    ) 
    ->process($reader);
```

To enable comparison around the record offset the following methods are also added:
`andWhereOffset`, `whereNotOffset`, `orWhereOffset` and `xorWhereOffset`

The method will only interact with the record offset as such you can only design an operator
and the value with which you want to campare the offset with.

```php
use League\Csv\Reader;
use League\Csv\Statement;

$reader = Reader::createFromPath('/path/to/file.csv');
$records = Statement::create()
    ->andWhereOffset('<', 100) //filtering is done on the offset value only
    ->process($reader);
// $records is a League\Csv\ResultSet instance
```

For complex constraint you can, instead of specifying an operator and a value, choose to
only specificy a callback. In that case the callback method will be evaluated with the value of the column
and/or of its offset.

```php
use League\Csv\Reader;
use League\Csv\Statement;

$reader = Reader::createFromPath('/path/to/file.csv');
$records = Statement::create()
    ->andWhereOffset(fn (string|int $value): bool => fmod((float) $value, 2) == 0) 
       // filtering is done on the record offset value
       // records are kept only if the value is even.
    ->process($reader);
```

For more complex queries you can use the classes and Enums defined under the `League\Csv\Query` namespace.
They are used internally by the `Statement` class to implement all the new `where` methods and can be
used independently to help create your own where expression as shown in the following example:

```php
use League\Csv\Query;

$data = [
    ['volume' => 67, 'edition' => 2],
    ['volume' => 86, 'edition' => 1],
    ['volume' => 85, 'edition' => 6],
    ['volume' => 98, 'edition' => 2],
    ['volume' => 86, 'edition' => 6],
    ['volume' => 67, 'edition' => 7],
];

$criteria = Query\Constraint\Criteria::xany(
    Query\Constraint\Column::filterOn('volume', 'gt', 80),
    fn (mixed $record, int|string $key) => Query\Row::from($record)->field('edition') < 6
);

$filteredData = array_filter($data, $criteria, ARRAY_FILTER_USE_BOTH));
//Filtering an array using the XOR logical operator
```

As shown in the example the `Criteria` class also combines `Closure` conditions, which means
that you can use a callable whose signature matches the one use for the `where` method.

### Ordering

The `orderBy` method allows you to sort the results of the applied constraints. Just like
with filtering the method can be called multiple and the *First In First Out* rule is
also applied. The callable accepted is similar to the one used by the `usort` function.
As an example let's order the records according to the lastname found on the records.

```php
use League\Csv\Query;
use League\Csv\Reader;
use League\Csv\Statement;

$reader = Reader::createFromPath('/path/to/file.csv');
$records = Statement::create()
    ->orderBy(fn (mixed $rA, mixed $rB): int => strcmp(Query\Row::from($rB)->field(1) ?? '', Query\Row::from($rA)->field(1) ?? '')))
    ->process($reader);
// $records is a League\Csv\ResultSet instance
```

<p class="message-warning"><strong>Warning:</strong> To sort the data <code>iterator_to_array</code> is used,
which could lead to a performance penalty if you have a heavy tabular data reader to sort</p>

<p class="message-info">New since version <code>9.16.0</code></p>

The `orderByAsc` and `orderByDesc` methods are simpler version of the `orderBy` method.
Instead of requiring a callable, it requires 2 arguments, the tabular data column to
sort the document with. It can be as a string (the column name, if it exists) or an
integer (the column offset, negative indexes are supported). And an optional
callback to improve sorting results if needed. If no callback sorting algorithn is
given, sorting is done using the `<=>` spaceship operator. A sorting callback is
a `Closure` that can be used with PHP's `usort` or `uasort` method.

```php
use League\Csv\Reader;
use League\Csv\Statement;

$reader = Reader::createFromPath('/path/to/file.csv');
$records = Statement::create()
    ->orderByDesc(1) //descending order according to the data of the 2nd column
    ->orderByAsc('foo', strcmp(...)) //ascending order according a callback compare function
    ->process($reader);
// $records is a League\Csv\ResultSet instance
```

if you need to create more complex ordering you may align calls to `orderByAsc` and `orderByDesc` **or**
use the `orderBy` method with the classes defined under the `League\Csv\Query` namespace as shown below:

```php

use League\Csv\Query;
use League\Csv\Reader;
use League\Csv\Statement;

$sort = Query\Ordering\MultiSort::all(
    Query\Ordering\Column::sortBy(1, 'desc'),
    Query\Ordering\Column::sortBy('foo', 'asc', strcmp(...)),
);

$reader = Reader::createFromPath('/path/to/file.csv');
$records = Statement::create()->orderBy($sort)->process($reader);
// Will return the same content as in the previous example.
```

### Limit and Offset

You can use the `limit` and `offset` methods to limit the number of records returned. When called more than once,
only the last filtering setting will be taken into account. The `offset` specifies an optional offset for
the returned data. By default, if no offset is provided the offset equals `0`. On the other hand, the
`limit` method specifies an optional maximum records count for the returned data. By default, if
no limit is provided the limit equals `-1`, which translates to all records. We can for instance
limit the number of records to at most `5` starting from the `10`th found record.

```php
use League\Csv\Reader;
use League\Csv\Statement;

$reader = Reader::createFromPath('/path/to/file.csv');
$records = Statement::create()
    ->limit(5)
    ->offset(9)
    ->process($reader);
// $records is a League\Csv\ResultSet instance
```

<p class="message-notice">When called multiple times, each call overrides the last setting for these options.</p>

### Selecting columns

<p class="message-info">new in version <code>9.15.0</code>.</p>

You may not always want to select all columns from the tabular data. Using the `select` method,
you can specify which columns to use. The column can be specified by their name, if the instance
`getHeader` returns a non-empty array, or you can default to using the column offset. You
can even mix them both.

```php
use League\Csv\Reader;
use League\Csv\Statement;

$reader = Reader::createFromPath('/path/to/file.csv');
$records = Statement::create()
    ->select(1, 3, 'field')
    ->process($reader);
// $records is a League\Csv\ResultSet instance with only 3 fields
```

While we explain each method separately it is understood that you could use them all together
to query your CSV document as you want like in the following example.

```php
use League\Csv\Reader;
use League\Csv\Statement;

$constraints = Statement::create()
    ->select('Integer', 'Text', 'Date and Time')
    ->andWhere('Float', '<', 1.3)
    ->orderByDesc('Integer')
    ->offset(2)
    ->limit(5);

$document = <<<CSV
Integer,Float,Text,Multiline Text,Date and Time
1,1.11,Foo,"Foo
Bar",2020-01-01 01:01:01
2,1.22,Bar,"Bar
Baz",2020-02-02 02:02:02
3,1.33,Baz,"Baz
Foo",2020-03-03 03:03:03
CSV;

$csv = Reader::createFromString($document);
$csv->setHeaderOffset(0);
$csv->addFormatter(fn (array $record) => [...$record, ...['Float' => (float) $record['Float'], 'Integer' => (int) $record['Integer']]])
$records = $constraints->process($csv);
//returns a ResultSet containing records which validate all the constraints.
```

Since a `Statement` instance is independent of the CSV document you can re-use it on different CSV
documents or `TabularDataReader` instances if needed.

## FragmentFinder

<p class="message-info">This mechanism is introduced with version <code>9.12.0</code>.</p>
<p class="message-warning">This implementation is marked as experimetal since version <code>9.12.0</code>.
The public API is stable but the implementation and returned value will change in the next version to
take into account edge cases and improve error and selection handling. It is recommended to avoid using
the current implementation <strong>or</strong> restrict its usage for simple selection in version 9.</p>

The second mechanism is based on [RFC7111](https://www.rfc-editor.org/rfc/rfc7111) and allow selecting
part of your document according to its rows, columns or cells coordinates. The RFC, and thus, our class
assume that your data is column size consistent and, in absence of a specified header, it will use the
first record as reference to determine the input number of columns.

The RFC defines three (3) types of selections and the `FragmentFinder` class supports them all.

You can select part of your data according to:

- its row index using an expression that starts with the `row` keyword;
- its column index using an expression that starts with the `col` keyword;
- its cell coordinates using an expression that starts with the `cell` keyword;

<p class="message-warning">While this package uses 0-indexed as PHP, the RFC7111 uses 1-indexed
to designate columns and rows which might seems inconsistent with the rest of the package.</p>

Here are some selection example:

- `col=5` : will select the column `4`;
- `col=5-7` : will select the columns `4` to `6` included;
- `row=5-*` : will select all the remaining rows of the document starting from the `4th` row.
- `cell=5,2-8,9` : will select the cells located between row `4` and column `1` and row `7` and column `8`;

Of note, the RFC allows for multiple selections, separated by a `;`. which are translated
as `OR` expressions. To strictly cover The RFC the class exposes the `find` method
which returns an iterable containing the results of all found fragments as distinct `TabulatDataReader`
instances.

<p class="message-info">This <code>find</code> method is introduced with version <code>9.17.0</code>.</p>
<p class="message-notice">The <code>findAll</code> method is deprecated, you should use <code>find</code> instead.</p>
<p class="message-warning">If some selections are invalid no error is returned; the invalid selection is skipped from the returned value.</p>

To restrict the returned values you may use the `findFirst` and `findFirstOrFail` methods.
Both methods return on success a `TabularDataReader` instance. While the `first` method
always return the first selection found if it is not empty or `null`; `firstOrFail` **MUST** return a non-empty
`TabularDataReader` instance or throw. It will also throw if the expression syntax is
invalid while all the other methods just ignore the error.

For example, with the following partially invalid expression:

```php
use League\Csv\Reader;
use League\Csv\FragmentFinder;

$reader = Reader::createFromPath('/path/to/file.csv');
$finder = FragmentFinder::create();

$finder->find('row=7-5;8-9', $reader);         // return an Iterator<TabularDataReader>
$finder->findFirst('row=7-5;8-9', $reader);       // return an TabularDataReader
$finder->findFirstOrFail('row=7-5;8-9', $reader); // will throw
```

- `FragmentFinder::find` returns an Iterator containing a single `TabularDataReader` because the first selection
is invalid;
- `FragmentFinder::findFirst` returns the single valid `TabularDataReader`
- `FragmentFinder::findFirstOrFail` throws a `SyntaxError`.

Both classes, `FragmentFinder` and `Statement` returns an instance that implements the `TabularDataReader` interface
which returns the found data in a consistent way.
