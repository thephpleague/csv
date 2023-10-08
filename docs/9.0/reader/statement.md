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

<p class="message-warning"><strong>Warning:</strong> since version <code>9.12.0</code> the optional
<code>$header</code> argument used by the <code>process</code> method is deprecated.</p>

### Where clauses

To filter the records from your input you may use the `where` method. The method can be
called multiple time and each time it will add another constraint filter. This option
follows the *First In First Out* rule. The filter excepts a callable similar to the
one used by `array_filter`. For example the following filter will remove all the
records whose `3rd` field does not contain a valid `email`:

```php
use League\Csv\Reader;
use League\Csv\Statement;

$reader = Reader::createFromPath('/path/to/file.csv');
$records = Statement::create()
    ->where(fn (array $record): bool => false !== filter_var($record[2] ?? '', FILTER_VALIDATE_EMAIL))
    ->process($reader);
// $records is a League\Csv\ResultSet instance
```

### Ordering

The `orderBy` method allows you to sort the results of the applied constraints. Just like
with filtering the method can be called multiple and the *First In First Out* rule is
also applied. The callable accepted is similar to the one used by the `usort` function.
As an example let's order the records according to the lastname found on the records.

```php
use League\Csv\Reader;
use League\Csv\Statement;

$reader = Reader::createFromPath('/path/to/file.csv');
$records = Statement::create()
    ->orderBy(fn (array $rA, $rB): int => strcmp($rB[1] ?? '', $rA[1] ?? '')))
    ->process($reader);
// $records is a League\Csv\ResultSet instance
```

<p class="message-warning"><strong>Warning:</strong> To sort the data <code>iterator_to_array</code> is used,
which could lead to a performance penalty if you have a heavy CSV file to sort</p>

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

## FragmentFinder

<p class="message-info">This mechanism is introduced with version <code>9.12.0</code>.</p>

The second mechanism is based on [RFC7111](https://www.rfc-editor.org/rfc/rfc7111) and allow selecting
part of your document according to its rows, columns or cells coordinates. The RFC, and thus, our class
assume that your data is column size consistant and, in absence of a specified header, it will use the
first record as reference to determine the input number of columns.

The RFC defines three (3) types of selections and the `FragmentFinder` class supports them all.

You can select part of your data according to:

- its row index using an expression that starts with the `row` keyword;
- its column index using an expression that starts with the `col` keyword;
- its cell coordinates using an expression that starts with the `cell` keyword;

Here are some selection example:

- `col=5` : will select the column `4`;
- `col=5-7` : will select the columns `4` to `6` included;
- `row=5-*` : will select all the remaining rows of the document starting from the `4th` row.
- `cell=5,2-8,9` : will select the cells located between row `4` and column `1` and row `7` and column `8`;

Of note, the RFC allows for multiple selections, separated by a `;`. which are translated
as `OR` expressions. To strictly cover The RFC the class exposes the `findAll` method
which returns an iterable containing the results of all found fragments as distinct `TabulatDataReader`
instances.

<p class="message-warning">If some selections are invalid no error is returned; the invalid
selection is skipped from the returned value.</p>

To restrict the returned values you may use the `findFirst` and `findFirstOrFail` methods.
Both methods return on success a `TabularDataReader` instance. While the `first` method
always return the first selection found or `null`; `firstOrFail` **MUST** return a
`TabularDataReader` instance or throw. It will also throw if the expression syntax is
invalid while all the other methods just ignore the error.

For example, with the following partially invalid expression:

```php
use League\Csv\Reader;
use League\Csv\FragmentFinder;

$reader = Reader::createFromPath('/path/to/file.csv');
$finder = FragmentFinder::create();

$finder->findAll('row=7-5;8-9', $reader);         // return an Iterator<TabulatDataReader>
$finder->findFirst('row=7-5;8-9', $reader);       // return an TabulatDataReader
$finder->findFirstOrFail('row=7-5;8-9', $reader); // will throw
```

- `FragmentFinder::findAll` returns an Iterator containing a single `TabularDataReader` because the first selection
is invalid;
- `FragmentFinder::findFirst` returns the single valid `TabularDataReader`
- `FragmentFinder::findFirstOrFail` throws a `SyntaxError`.

Both classes, `FragmentFinder` and `Statement` returns an instance that implements the `TabularDataReader` interface
which returns the found data in a consistent way.
