---
layout: default
title: Inserting new data into a CSV
redirect_from: /inserting/
---

# Inserting Data

To create or update a CSV use the following `League\Csv\Writer` methods.

<p class="message-info">When creating a file using the library, first insert all the data that need to be inserted before starting manipulating the CSV. If you manipulate your data before insertion, you may change the file cursor position and get unexpected results.</p>

## Adding new data

The `Writer` class performs a number of actions while inserting your data into the CSV. When submitting data for insertion the class will proceed as describe below for each row.

The `Writer` class will:

- See if the row is an `array`, if not it will try to convert it into a proper `array`;
- If supplied, formatters will further format the given `array`;
- If supplied, validators will validate the formatted `array` according to their rules;
- While writing the data to your CSV document, if supplied, <a href="/8.0/filtering/">stream filters</a> will apply further formatting to the inserted row;
- If needed the newline sequence will be updated;

To add new data to your CSV the `Writer` class uses the following methods

### Writer::insertOne

`insertOne` inserts a single row.

```php
public Writer::insertOne(mixed $row): Writer
```

This method takes a single argument `$row` which can be

- an `array`;
- a `string`;
- or an `object` implementing the `__toString` method.

#### Example

```php
use League\Csv\Writer;

class ToStringEnabledClass
{
    private $str;

    public function __construct($str)
    {
        $this->str = $str;
    }

    public function __toString()
    {
        return $this->str;
    }
}

$writer = Writer::createFromPath('/path/to/saved/file.csv', 'w+');
$writer->insertOne(['john', 'doe', 'john.doe@example.com']);
$writer->insertOne("'john','doe','john.doe@example.com'");
$writer->insertOne(new ToStringEnabledClass("john,doe,john.doe@example.com"))
```

In the above example, all the CSV records are saved to the `/path/to/saved/file.csv` file.

### Writer::insertAll

`insertAll` inserts multiple rows.

```php
public Writer::insertAll(mixed $rows): Writer
```

This method takes a single argument `$row` which can be

- a `array`
- or a `Traversable` object

to add several rows to the CSV data.

#### Example

```php
use League\Csv\Writer;

$rows = [
    [1, 2, 3],
    ['foo', 'bar', 'baz'],
    "'john','doe','john.doe@example.com'",
    new ToStringEnabledClass("john,doe,john.doe@example.com")
];

$writer = Writer::createFromFileObject(new SplTempFileObject());
$writer->insertAll($rows); //using an array
$writer->insertAll(new ArrayIterator($rows)); //using a Traversable object
```

## Row formatting

### CSV Formatter

A formatter is a `callable` which accepts an `array` on input and returns the same array formatted according to its inner rules.

```php
function(array $row): array
```

You can attach as many formatters as you want to the `Writer` class to manipulate your data prior to its insertion. The formatters follow the *First In First Out* rule when inserted, deleted and/or applied.

### Formatter API

The formatter API comes with the following public API:

```php
public Writer::addFormatter(callable $callable): Writer
public Writer::removeFormatter(callable $callable): Writer
public Writer::hasFormatter(callable $callable): bool
public Writer::clearFormatters(void): Writer
```

- `addFormatter`: Adds a formatter to the formatter collection;
- `removeFormatter`: Removes an already registered formatter. If the formatter was registered multiple times, you will have to call `removeFormatter` as often as the formatter was registered. **The first registered copy will be the first to be removed.**
- `hasFormatter`: Checks if the formatter is already registered
- `clearFormatters`: removes all registered formatters.

```php
use League\Csv\Writer;

$formatter = function ($row) {
    return array_map('strtoupper', $row);
};
$writer = Writer::createFromFileObject(new SplTempFileObject());
$writer->addFormatter($formatter);
$writer->insertOne(['john', 'doe', 'john.doe@example.com']);

$writer->__toString();
//will display something like JOHN,DOE,JOHN.DOE@EXAMPLE.COM
```

## Row validation

### CSV validator

A validator is a `callable` which takes a `array` as its sole argument and returns a boolean.

```php
function(array $row): bool
```

The validator **must** return `true` to validate the submitted row.

Any other expression, including truthy ones like `yes`, `1`,... will make the `insertOne` method throw an `League\Csv\Exception\InvalidRowException`.

As with the new formatter capabilities, you can attach as many validators as you want to your data prior to its insertion. The row data is checked against your supplied validators **after being formatted**.

### Validator API

The validator API comes with the following public API:

```php
public Writer::addValidator(callable $callable, string $validatorName): Writer
public Writer::removeValidator(string $validatorName): Writer
public Writer::hasValidator(string $validatorName): bool
public Writer::clearValidators(void): Writer
```

- `addValidator`: Adds a validator each time it is called. The method takes two parameters:
  - A `callable` which takes an `array` as its unique parameter;
  - The validator name which is **required**. If another validator was already registered with the given name, it will be overridden.
- `removeValidator`: Removes an already registered validator by using the validator registrated name.
- `hasValidator`: Checks if the validator is already registered
- `clearValidators`: removes all registered validator.

### Validation failed

If the validation failed a `League\Csv\Exception\InvalidRowException` is thrown by the `Writer` object.
This exception extends PHP's `InvalidArgumentException` by adding two public getter methods

```php
public InvalidRowException::getName(void): string
public InvalidRowException::getData(void): array
```

- `InvalidRowException::getName`: returns the name of the failed validator
- `InvalidRowException::getData`: returns the invalid data submitted to the validator

#### Validation example

```php
use League\Csv\Writer;
use League\Csv\Exception\InvalidRowException;

$writer->addValidator(function (array $row) {
    return 10 == count($row);
}, 'row_must_contain_10_cells');
try {
    $writer->insertOne(['john', 'doe', 'john.doe@example.com']);
} catch (InvalidRowException $e) {
    echo $e->getName(); //display 'row_must_contain_10_cells'
    $e->getData();//will return the invalid data ['john', 'doe', 'john.doe@example.com']
}
```

## Bundled formatters and validators

### Null value validator

The `League\Csv\Plugin\ForbiddenNullValuesValidator` class validates the absence of `null` values

```php
use League\Csv\Writer;
use League\Csv\Plugin\ForbiddenNullValuesValidator;

$validator = new ForbiddenNullValuesValidator();
$writer = Writer::createFromPath('/path/to/your/csv/file.csv');
$writer->addValidator($validator, 'null_as_exception');
$writer->insertOne(["foo", null, "bar"]);
// will throw an League\Csv\Exception\InvalidRowException
```

### Null value formatting

The `League\Csv\Plugin\SkipNullValuesFormatter` class skips cell using founded `null` values

```php
use League\Csv\Writer;
use League\Csv\Plugin\SkipNullValuesFormatter;

$formatter = new SkipNullValuesFormatter();

$writer = Writer::createFromPath('/path/to/your/csv/file.csv');
$writer->addFormatter($formatter);
$writer->insertOne(["foo", null, "bar"]);
//the actual inserted row will be ["foo", "bar"]
```

### Records consistency check

The `League\Csv\Plugin\ColumnConsistencyValidator` class validates the inserted record column count consistency.

```php
use League\Csv\Writer;
use League\Csv\Plugin\ColumnConsistencyValidator;

$validator = new ColumnConsistencyValidator();
$validator->autodetectColumnsCount();
$validator->getColumnsCount(); //returns -1

$writer = Writer::createFromPath('/path/to/your/csv/file.csv');
$writer->addValidator($validator, 'column_consistency');

$writer->insertOne(["foo", null, "bar"]);
$nb_column_count = $validator->getColumnsCount(); //returns 3
```

## Stream filtering

Some data formatting can still occur while writing the data to the CSV document after validation using the [Stream Filters capabilities](/8.0/filtering/).

## Handling newline

Because the php `fputcsv` implementation has a hardcoded `\n`, we need to be able to replace the last `LF` code with one supplied by the developer for more interoperability between CSV packages on different platforms. The newline sequence will be appended to each CSV newly inserted line.

At any given time you can get and modify the `$newline` property using the `getNewline` and `setNewline` methods described in <a href="/8.0/properties/">CSV properties documentation page</a>.

```php
use League\Csv\Writer;

$writer = Writer::createFromFileObject(new SplFileObject());
$newline = $writer->getNewline(); // equals "\n";
$writer->setNewline("\r\n");
$newline = $writer->getNewline(); // equals "\r\n";
$writer->insertOne(["one", "two"]);
echo $writer; // displays "one,two\r\n";
```

<p class="message-info">Please refer to <a href="/8.0/bom/">the BOM character dedicated documentation page</a> for more information on how the library manage the BOM character.</p>
