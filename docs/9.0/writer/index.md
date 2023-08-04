---
layout: default
title: CSV document Writer connection
---

# Writer connection

The `League\Csv\Writer` class extends the general connections [capabilities](/9.0/connections/) to create or update a CSV document.

<p class="message-warning">When inserting records into a CSV document using <code>League\Csv\Writer</code>, first insert all the data that need to be inserted before starting manipulating the CSV. If you manipulate your CSV document before insertion, you may change the file cursor position and erase your data.</p>

## Inserting records

```php
public Writer::insertOne(array $record): int
public Writer::insertAll(iterable $records): int
```

`Writer::insertOne` inserts a single record into the CSV document while `Writer::insertAll` adds several records. Both methods return the length of the written data.

`Writer::insertOne` takes a single argument, an `array` which represents a single CSV record.
`Writer::insertAll` takes a single argument a PHP iterable which contains a collection of CSV records.

```php
use League\Csv\Writer;

$records = [
    [1, 2, 3],
    ['foo', 'bar', 'baz'],
    ['john', 'doe', 'john.doe@example.com'],
];

$writer = Writer::createFromPath('/path/to/saved/file.csv', 'w+');
$writer->insertOne(['john', 'doe', 'john.doe@example.com']);
$writer->insertAll($records); //using an array
$writer->insertAll(new ArrayIterator($records)); //using a Traversable object
```

In the above example, all CSV records are saved to `/path/to/saved/file.csv`

If the record can not be inserted into the CSV document a `League\Csv\CannotInsertRecord` exception is thrown. This exception extends `League\Csv\Exception` and adds the ability to get the record on which the insertion failed.

```php
use League\Csv\CannotInsertRecord;
use League\Csv\Writer;

$records = [
    [1, 2, 3],
    ['foo', 'bar', 'baz'],
    ['john', 'doe', 'john.doe@example.com'],
];

try {
    $writer = Writer::createFromPath('/path/to/saved/file.csv', 'r');
    $writer->insertAll($records);
} catch (CannotInsertRecord $e) {
    $e->getRecord(); //returns [1, 2, 3]
}
```

<p class="message-info">Since version <code>9.2.0</code> you can provide an empty string for the escape character to enable better <a href="https://tools.ietf.org/html/rfc4180">RFC4180</a> compliance.</p>

```php
use League\Csv\Writer;

$record = ['"foo"', 'foo bar', 'baz ', 'foo\\"bar'];

$writer = Writer::createFromString();
$writer->insertOne($record);
$writer->setEscape('');
$writer->insertOne($record);
echo $writer->toString();
// """foo""","foo bar","baz ","foo\"bar"
// """foo""","foo bar","baz ","foo\""bar"
```

<p class="message-notice">The addition of this new feature means the deprecation of <a href="/9.0/interoperability/rfc4180-field/">RFC4180Field</a>.</p>

<p class="message-notice">You still need to set the end of line sequence as shown below</p>

## Handling end of line

Because PHP's `fputcsv` implementation uses a hardcoded `\n`, we need to be able to replace the last `LF` code
with one supplied by the developer for more interoperability between CSV packages on different platforms.
The end of line sequence will be appended to each newly inserted CSV record.

### Description

<p class="message-notice"><code>setEndOfline</code> and <code>getEndOfLine</code> are available since version
<code>9.10.0</code>.</p>

```php
public Writer::setEndOfline(string $sequence): self
public Writer::getEndOfLine(void): string
```

### Example

```php
use League\Csv\Writer;

$writer = Writer::createFromFileObject(new SplFileObject());
$writer->getEndOfLine();        //returns "\n";
$writer->setEndOfLine("\r\n");
$writer->getEndOfLine();        //returns "\r\n";
$writer->insertOne(["one", "two"]);
echo $writer->toString();       //displays "one,two\r\n";
```

<p class="message-notice"><code>setNewline</code> and <code>getNewLine</code> are deprecated since version <code>9.10.0</code>.</p>
<p class="message-info">The default end of line sequence is <code>\n</code>;</p>
<p class="message-warning">If you are using a non-seekable CSV document, changing the end of line character will trigger an exception.</p>

## Flushing the buffer

For advanced usages, you can now manually indicate when the flushing mechanism occurs while adding new content to your CSV document.

### Description

```php
public Writer::setFlushThreshold(?int $treshold): self
public Writer::getFlushThreshold(void): ?int
```

By default, `getFlushTreshold` returns `null`.

<p class="message-info"><code>Writer::insertAll</code> always flushes its buffer when all records are inserted, regardless of the threshold value.</p>
<p class="message-info">If set to <code>null</code> the inner flush mechanism of PHP's <code>fputcsv</code> will be used.</p>

## Force Enclosure

<p class="message-info">Since version <code>9.10.0</code> you can provide control the presence of enclosure around all records.</p>

```php
public Writer::forceEnclosure(): self
public Writer::relaxEnclosure(): self
public Writer::encloseAll(): bool
```

By default, the `Writer` adds enclosures only around records that requires them. For all other records no enclosure character is present,
With this feature, you can force the enclosure to be present on every record entry or CSV cell. The inclusion will respect
the presence of enclosures inside the cell content as well as the presence of PHP's escape character.

```php
<?php
use League\Csv\Writer;

$collection = [
    [1, 2],
    ['value 2-0', 'value 2-1'],
    ['to"to', 'foo\"bar'],
];

$writer = Writer::createFromString();
$writer->encloseAll(); //return false;
$writer->forceEnclosure();
$writer->encloseAll(); //return true;
$writer->insertAll($collection);
echo $writer->toString(), PHP_EOL;

// the CSV file will contain enclosed cell.
// Double quote are not added in presence of the 
// escape character as per PHP's CSV writing documentation

// "1","2"
// "value 2-0","value 2-1"
// "to""to","foo\"bar"
```

## Records filtering

```php
public Writer::addFormatter(callable $callable): self
public Writer::addValidator(callable $callable, string $validatorName): self
```

Sometimes you may want to format and/or validate your records prior to their insertion into your CSV document. The `Writer` class provides a formatter and a validator mechanism to ease these operations.

### Writer::addFormatter

#### Record Formatter

A formatter is a `callable` which accepts a single CSV record as an `array` on input and returns an array representing the formatted CSV record according to its inner rules.

```php
function(array $record): array
```

#### Adding a Formatter to a Writer object

You can attach as many formatters as you want to the `Writer` class using the `Writer::addFormatter` method. Formatters are applied following the *First In First Out* rule.

```php
use League\Csv\Writer;

$formatter = function (array $row): array {
    return array_map('strtoupper', $row);
};
$writer = Writer::createFromFileObject(new SplTempFileObject());
$writer->addFormatter($formatter);
$writer->insertOne(['john', 'doe', 'john.doe@example.com']);

echo $writer->toString();
//will display JOHN,DOE,JOHN.DOE@EXAMPLE.COM
```

### Writer::addValidator

#### Record Validator

A validator is a `callable` which takes a single CSV record as an `array` as its sole argument and returns a `boolean` to indicate if it satisfies the validator's inner rules.

```php
function(array $record): bool
```

The validator **must** return `true` to validate the submitted record.

Any other expression, including truthy ones like `yes`, `1`,... will make the `insertOne` method throw an `League\Csv\CannotInsertRecord` exception.

#### Adding a Validator to a Writer object

As with the formatter capabilities, you can attach as many validators as you want using the `Writer::addValidator` method. Validators are applied following the *First In First Out* rule.

<p class="message-warning">The CSV record is checked against your supplied validators <strong>after it has been formatted</strong>.</p>

`Writer::addValidator` takes two (2) **required** parameters:

- A validator `callable`;
- A validator name. If another validator was already registered with the given name, it will be overridden.

On failure a `League\Csv\CannotInsertRecord` exception is thrown.
This exception will give access to:

- the validator name;
- the record which failed the validation;

```php
use League\Csv\Writer;
use League\Csv\CannotInsertRecord;

$writer->addValidator(function (array $row): bool {
    return 10 == count($row);
}, 'row_must_contain_10_cells');

try {
    $writer->insertOne(['john', 'doe', 'john.doe@example.com']);
} catch (CannotInsertRecord $e) {
    echo $e->getName(); //displays 'row_must_contain_10_cells'
    $e->getData();//returns the invalid data ['john', 'doe', 'john.doe@example.com']
}
```
