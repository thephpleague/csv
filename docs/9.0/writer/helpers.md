---
layout: default
title: Bundled Writer helpers
---

# Bundled insertion helpers

## Column consistency validator

The `League\Csv\ColumnConsistency` class validates the inserted record column count consistency.

This class constructor accepts a single argument `$column_count` which sets the column count value and validates each record length against the given value. If the value differs, a `CannotInsertRecord` exception will be thrown.

If `$column_count` equals `-1`, the object will lazy set the column count value according to the next inserted record and therefore will also validate it. On the next insert, if the given value differs, a `CannotInsertRecord` exception is triggered.
At any given time you can retrieve the column count value using the `ColumnConsistency::getColumnCount` method.

```php
use League\Csv\Writer;
use League\Csv\ColumnConsistency;

$validator = new ColumnConsistency();
$writer = Writer::from('/path/to/your/csv/file.csv');
$writer->addValidator($validator, 'column_consistency');
$validator->getColumnCount(); //returns -1
$writer->insertOne(["foo", "bar", "baz"]);
$validator->getColumnCount(); //returns 3
$writer->insertOne(["foo", "bar"]); //will trigger a CannotInsertRecord exception
```

<p class="message-info">The default column count is set to <code>-1</code>.</p>

## Charset formatter

[League\Csv\CharsetConverter](/9.0/converter/charset/) will help you encode your records depending on your settings.

## Enum Formatter

The `League\Csv\EnumFormatter` class allows serializing `Enum` introduced in `PHP8.1` as CSV field value.

The class is **an immutable formatter** designed to convert PHP enums (`UnitEnum`) into scalar or
serializable values suitable for CSV export (or similar flat formats).

It can be used directly as a callable and supports multiple exclusive formatting strategies.

| Strategy     | Description                                         | Named constructors |
|--------------|-----------------------------------------------------|--------------------|
| **Name**     | Uses the enum case name (`UnitEnum::name`)          | `usingName`        |
| **Value**    | Uses the backed value of a `BackedEnum`             | `usingValue`       |
| **JSON**     | Uses `jsonSerialize()` for `JsonSerializable` enums | `usingJson`        |
| **Callback** | Uses a user-defined callable                        | `usingCallback`    |

### Encoding a single enum

You can encode a single enum manually using the `encode()` method:

```php
use League\Csv\EnumFormatter;

$value = EnumFormatter::useJson()->encode(Pure::Foo);
// returns "foo"
```

If an enum cannot be serialized using the selected strategy, a `TypeError` is thrown:

```php
use League\Csv\EnumFormatter;

$formatter = EnumFormatter::useNative();
$value = $formatter->encode(Pure::Foo);
// Enum `Pure` cannot be serialized for CSV.
```

### Encoding Enum found in records

The class can be used as a `Closure` to convert enum found in a `array`. 

```php
use League\Csv\EnumFormatter;

enum Status
{
    case Active;
    case Inactive;
}

$record = [
    'id' => 1,
    'status' => Status::Active,
];

$formatter = EnumFormatter::usingName();
$result = $formatter($record);
// ['id' => 1, 'status' => 'Active']
```

The class can also be used as a Formatter by the `Writer` as follows:

```php
use League\Csv\Writer;
use League\Csv\EnumFormatter;

enum Pure implements JsonSerializable
{
    case Foo;
    case Bar;

    public function jsonSerialize(): string
    {
        return strtolower($this->name);
    }
}

$arr = ['city' => Pure::Foo, 'habitants' => 7_000_000];
$doc = Writer::fromString();
$doc->addFormatter(EnumFormatter::usingCallback(fn (UnitEnum $value) => 'fourty-two'));
$doc->insertOne($arr);

$doc->toString();
// returns "fourty-two,7000000 \n",
```
