---
layout: default
title: Bundled Writer helpers
description: Bundled insertion helpers to format and validate records before insertion
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

<p class="message-notice">Available since version <code>9.28</code></p>

The `League\Csv\EnumFormatter` class is **an immutable formatter** designed to convert
PHP enums (`UnitEnum`), introduced in `PHP8.1`, into scalar or serializable values
suitable for CSV export (or similar flat formats).

It can be used directly as a callable and supports multiple exclusive formatting strategies.
Those strategies are exclusive and are chosen via named constructors.

| Strategy     | Description                                         | Named constructors |
|--------------|-----------------------------------------------------|--------------------|
| **Name**     | Uses the enum case name (`UnitEnum::name`)          | `usingName`        |
| **Value**    | Uses the backed value of a `BackedEnum`             | `usingValue`       |
| **JSON**     | Uses `jsonSerialize()` for `JsonSerializable` enums | `usingJson`        |
| **Callback** | Uses a user-defined callable                        | `usingCallback`    |

### Encoding a Single Enum

You can encode a single enum manually using the `encode()` method:

```php
use League\Csv\EnumFormatter;

$value = EnumFormatter::usingJson()->encode(Pure::Foo);
// returns "foo"
```

When using `EnumFormatter::usingJson()`, internally the `Pure` Enum must implement the `JsonSerializable` interface,
the formatter `encode()` method will return the call to the `jsonSerialize()` method. If an enum cannot be
serialized using the selected strategy, a `TypeError` is thrown:

```php
use League\Csv\EnumFormatter;

$formatter = EnumFormatter::usingValue();
$value = $formatter->encode(Pure::Foo);
// Enum `Pure` cannot be serialized for CSV.
```

In this case the `Pure` Enum is not a Backed enum so the `value` property does not exist and can not be called
resulting in a `TypeError` being thrown.

### Encoding Enum in Records

The class can be used as a callable to convert enum found in a `array`.

```php
use League\Csv\EnumFormatter;

enum Status: int
{
    case Active = 1;
    case Inactive = 0;
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
$doc->addFormatter(EnumFormatter::usingCallback(fn (UnitEnum $value) => 'forty-two'));
$doc->insertOne($arr);

$doc->toString();
// returns "forty-two,7000000 \n",
```

When using the `usingCallback()` named constructor the callback takes a single argument
a `UnitEnum` and is expected to return a value suitable for CSV encoding or any encoding
you want to use.

## Type Formatter

<p class="message-notice">Available since version <code>9.30</code></p>

The `League\Csv\TypeFormatter` class is an **immutable formatter** designed to convert PHP values
into strings suitable for CSV export and other flat data formats.

It follows conversion rules similar to those used for URI query parameters.

| Type     | Conversion                                         | Example |
|--------------|-----------------------------------------------------|--------------------|
| **string**     | as is          | "foo" → foo       |
| **null**    | configurable, default to empty field            | `null → empty field (in default configuration)`|
| **bool**     | configurable, default to  “1” / “0”| false → 0 (in default configuration)`        |
| **int** | decimal representation  | -42 → -42   |
| **float** | Serialized using serialize_precision, as with `json_encode()`| 0.1 + 0.2 → 0.30000000000000004   |
| **backed enum** | Backing value, converted according to its type | Suit::Hearts → H   |
| **other types** | Not supported; throws a `TypeError`| TypeError thrown|

<p class="message-notice">The float values <code>INF</code>, <code>-INF</code>, and <code>NAN</code> are serialized as strings.</p>
<p class="message-notice">Objects implementing <code>Stringable</code> are not supported and will cause a <code>TypeError</code> to be thrown.</p>

### Encoding a Single Value

You can encode a single value manually using the `encode()` method:

```php
use League\Csv\TypeFormatter;

$value = new TypeFormatter()->encode(Pure::Foo);
// returns "foo"
```

### Boolean and Null Values

You can customize how boolean and `null` values are converted by providing the corresponding values when instantiating the formatter.
By default, the following rules are applied:

- `true` is converted to `1`
- `false` is converted to `0`
- `null` is converted to the empty string

```php
use League\Csv\TypeFormatter;
$formatter = new TypeFormatter(nullValue: 'NONE', trueValue: "t", falseValue: "f");
$formatter->encode(null);
// returns "NONE"
$formatter->encode(true);
// returns "t"
$formatter->encode(false);
// returns "f"
```

### Usage with a Record and the Writer class

The class can be used as a callable to convert records fields.

```php
use League\Csv\TypeFormatter;

enum Status: int
{
    case Active = 1;
    case Inactive = 0;
}

$record = [
    'id' => 1,
    'is_connected' => true,
    'status' => Status::Active,
];

$formatter = new TypeFormatter(trueValue: 'yes');
$result = $formatter($record);
// ['id' => 1, 'is_connected' => 'yes', 'status' => 1]
```

The class can also be used as a Formatter by the `Writer` as follows:

```php
use League\Csv\Writer;
use League\Csv\TypeFormatter;

enum City: string
{
    case Brussels = 'bruxelles';
    case Berlin = 'berlin';
}

$arr = ['city' => City::Brussels, 'habitants' => 2_000_000];
$doc = Writer::fromString();
$doc->addFormatter(new TypeFormatter());
$doc->insertOne($arr);

$doc->toString();
// returns "bruxelles,2000000\n",
```
