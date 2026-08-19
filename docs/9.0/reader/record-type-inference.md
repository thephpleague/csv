---
layout: default
title: Schema inference
description: Detect your CSV field type using an ergonomic and deterministic type inference 
---

# Schema inference

CSV files do not contain type information: every value is initially read as text. Schema inference allows you to inspect a CSV and automatically determine the most appropriate type for each column.

The inferred schema can then be used to parse the records into their corresponding PHP values.

For example, given:

```csv
id,name,active,amount
1,Alice,true,12.50
2,Bob,false,8
```

schema inference can determine that:

* `id` is numeric;
* `name` is a string;
* `active` is boolean;
* `amount` is numeric.

The feature is available through `Reader::inferSchema()` and `Reader::inferRecords()`.

## Inferring the schema

Use `inferSchema()` when you want to inspect or reuse the schema inferred from the CSV:

```php
$schema = $reader->inferSchema();
```

The returned `Schema` associates each column with the field type inferred from its values.

For example:

```php
foreach ($schema as $column => $field) {
    echo $column, ': ', $field->name(), PHP_EOL;
}
```

The schema can then be used to parse the CSV:

```php
$records = $schema->parse($reader);
```

This is useful when you need to inspect or modify the inferred schema before processing the records.

## Inferring records

When you simply want to obtain records using the inferred types, use `inferRecords()`:

```php
foreach ($reader->inferRecords() as $record) {
    // ...
}
```

The records contain values parsed according to the inferred schema.

For example, the CSV:

```csv
id,name,active,amount
1,Alice,true,12.50
2,Bob,false,8
```

can produce records equivalent to:

```php
[
    1,
    'Alice',
    true,
    12.50,
]
```

and:

```php
[
    2,
    'Bob',
    false,
    8,
]
```

`inferRecords()` is essentially a convenience for inferring the schema and applying it to the records:

```php
$schema = $reader->inferSchema();

foreach ($schema->parse($reader) as $record) {
    // ...
}
```

The schema is inferred once and then applied consistently to the records.

## How inference works

Schema inference does not need to scan the entire CSV.

By default, the inspector examines a limited number of records and uses them to determine the type of each column. The inferred schema is then applied to all records.

This makes schema inference suitable for large CSV files where inspecting every record would be unnecessary.

### Controlling the sample size

The number of records inspected can be configured with an `Inspector`:

```php
$inspector = Inspector::default(sampleLimit: 100);

$schema = $reader->inferSchema($inspector);
```

or:

```php
$records = $reader->inferRecords($inspector);
```

The sample limit only controls **schema inference**. It does not limit the number of records returned by `inferRecords()`.

For example, with a sample limit of `100`, the first 100 records are used to determine the schema, but `inferRecords()` can still return every record in the CSV.

The default sample size is `10`.

## Controlling the inferred types

The `Inspector` also controls which field types are considered during inference.

The default inspector uses the default `FieldList`:

```php
$inspector = Inspector::default();
```

If the available field types need to be customized, provide a different field list:

```php
$inspector = Inspector::default()
    ->withFields($fieldList);
```

For example, a field type can be added to the default list:

```php
$fieldList = FieldList::default()
    ->append(new MyField());

$inspector = Inspector::default()
    ->withFields($fieldList);
```

The available built-in field types cover common values such as booleans, numbers, JSON, dates and times, enumerations, sets, and strings.

Only the fields included in the `FieldList` are considered during schema inference.

## Built-in field types

The inspection engine provides a field implementation for each supported field type:

| Field           | Description                                                             |
| --------------- | ----------------------------------------------------------------------- |
| `BooleanField`  | Detects and parses boolean values.                                      |
| `NumericField`  | Detects and parses numeric values.                                      |
| `DatetimeField` | Detects and parses date and time values.                                |
| `TimeField`     | Detects and parses time values.                                         |
| `JsonField`     | Detects and parses JSON values.                                         |
| `EnumField`     | Detects and parses values belonging to an enumeration.                  |
| `SetField`      | Detects and parses values representing a set.                           |
| `StringField`   | Handles values as strings.                                              |
| `CustomField`   | Allows application-specific types to be added to the inference process. |

The default `FieldList` currently includes:

```php
FieldList::default();
```

which provides:

```php
new BooleanField(),
new NumericField(),
new JsonField(),
```

Other built-in fields can be added when required:

```php
$fieldList = FieldList::default()
    ->append(
        new DatetimeField(),
        new TimeField(),
    );
```

This distinction is important: **a field being available does not mean that it is automatically used during inference**. Only fields present in the `FieldList` are considered by the `Inspector`.

## Custom field types

If the built-in field types do not cover a particular kind of value, a custom field can be added to the inspector.

For simple cases, `CustomField` can be used without implementing the `Field` interface:

```php
$postalCode = new CustomField(
    fieldParser: function (mixed $value): ?string {
        if (!is_string($value) || !preg_match('/^\d{5}$/', $value)) {
            return null;
        }

        return $value;
    },
    fieldTypeName: 'postal_code',
);
```

Then add it to the field list:

```php
$inspector = Inspector::default()
    ->withFields(
        FieldList::default()->append($postalCode),
    );
```

The custom field will then participate in schema inference alongside the built-in fields.

## Choosing between the methods

Use `inferSchema()` when you need to **inspect, reuse, or modify the inferred schema**:

```php
$schema = $reader->inferSchema();
```

Use `inferRecords()` when you simply want to **iterate over records with inferred types**:

```php
foreach ($reader->inferRecords() as $record) {
    // ...
}
```

In both cases, schema inference is performed from a sample of the CSV and the resulting schema is then used consistently to parse the records.

## Reading records

A Tabular data provides three ways to iterate over its records:

* `getRecords()` reads the values as they appear in the CSV;
* `getRecordsAsObject()` converts each record into a specific object;
* `inferRecords()` infers the types of the columns and parses the records accordingly.

The differences become clearer when the same data is read using each method.

### A CSV with mixed data

Consider the following CSV:

```php
use League\Csv\Reader;

$doc = <<<CSV
name;age;city;id;gender
Alice;25;New York;3391f7c0-d059-4e90-a73a-14f3140c6870;F
Bob;30;London;;M
Charlie;20;Berlin;3391f7c0-d059-4e90-a73a-14f3140c6870;F
David;35;Paris;42fe384c-9dab-483c-b8e2-44c73a5e9043;R
Frank;40;Tokyo;42fe384c-9dab-483c-b8e2-44c73a5e9043;
CSV;

$document = Reader::fromString($doc);
$document->setHeaderOffset(0);
```

- the `age` column contains numbers;
- the `id` column contains a UUID;
- the `gender` is intended to contain values from this enum:

```php
enum Gender
{
    case M;
    case F;
}
```

Notice that the CSV contains an invalid gender value:

```text
David;35;Paris;42fe384c-9dab-483c-b8e2-44c73a5e9043;R
```

This value is useful for illustrating the difference between the three APIs.

### `getRecords()`: read the CSV as-is

`getRecords()` does not interpret the values.

```php
$record = iterator_to_array($document)[4];
```

The record is returned with all values as strings:

```php
[
    'name' => 'David',
    'age' => '35',
    'city' => 'Paris',
    'id' => '42fe384c-9dab-483c-b8e2-44c73a5e9043',
    'gender' => 'R',
]
```

No validation or type conversion takes place.

Use `getRecords()` when you want to handle the CSV values yourself.

### `inferRecords()`: infer the types

`inferRecords()` examines the CSV and builds a schema from the values it finds.

```php
$record = iterator_to_array($document->inferRecords())[4];
```

With the default inspector, `age` is recognized as numeric and is therefore returned as an integer:

```php
[
    'name' => 'David',
    'age' => 35,
    'city' => 'Paris',
    'id' => '42fe384c-9dab-483c-b8e2-44c73a5e9043',
    'gender' => 'R',
]
```

The `gender` value remains a string because the default field list does not know that this column represents the `Gender` enum.

This illustrates an important property of inference:

> `inferRecords()` only applies the types that can be inferred from the configured field list.

#### Inferring the schema

Before parsing records, the reader can infer a schema describing the types found in each column:

```php
$schema = $document->inferSchema();
```

The schema inferred with the default inspector is:

```php
$schema->types();

// [
//     'name'   => 'string',
//     'age'    => 'numeric',
//     'city'   => 'string',
//     'id'     => 'string',
//     'gender' => 'string',
// ]
```

When the custom inspector is provided:

```php
$schema = $document->inferSchema($inspector);

$schema->types();

// [
//     'name'   => 'string',
//     'age'    => 'numeric',
//     'city'    => 'string',
//     'id'     => 'string(uuid)',
//     'gender' => 'enum(Gender)',
// ]
```

The difference comes from the fields available to the inspector. The custom inspector includes fields
capable of recognizing UUIDs and the `Gender` enum, allowing the schema to be more specific.

The inferred schema is then used by `inferRecords()` to determine how each value should be parsed:

```php
$document->inferRecords($inspector);
```

This means that `inferRecords()` does not independently decide how each value should be converted.
It first infers a schema and then applies that schema to the records.

So the flow is:

```text
CSV
 │
 ▼
inferSchema($inspector)
 │
 ▼
Schema
 │
 ▼
parse()
 │
 ▼
typed records
```

In short:

* **`inferSchema()`** tells you *what the CSV is understood to contain*.
* **`inferRecords()`** gives you the records *according to that understanding*.

### `inferRecords()` with a custom inspector

The inference can be configured when the CSV contains domain-specific types.

For example:

```php
$fields = FieldList::default()->append(
    StringField::cases(confidenceThreshold: .8),
    new EnumField(
        enumClass: Gender::class,
        confidenceThreshold: .5,
    ),
);

$inspector = new Inspector(fieldList: $fields);
```

The inspector now knows that `Gender` is a possible type for a column.

Using it with `inferRecords()`:

```php
$record = iterator_to_array(
    $document->inferRecords($inspector)
)[4];
```

produces:

```php
[
    'name' => 'David',
    'age' => 35,
    'city' => 'Paris',
    'id' => '42fe384c-9dab-483c-b8e2-44c73a5e9043',
    'gender' => null,
]
```

Because `"R"` is not a valid case of `Gender`, the `EnumField` cannot parse it and returns `null`.

This is an important difference from `getRecordsAsObject()`: **inferred parsing is tolerant of values that cannot be parsed by the inferred field**.

### `getRecordsAsObject()`: enforce a known structure

When the target object is known, `getRecordsAsObject()` can explicitly define how each column should be converted.

For example:

```php
final readonly class Poi
{
    public function __construct(
        public string $name,
        public int $age,
        public string $city,
        #[MapCell(
            column: 'gender',
            options: ['className' => Gender::class],
        )]
        public ?Gender $gender,
        #[MapCell(column: 'id')]
        public ?string $identifier,
    ) {}
}
```

Reading the records as `Poi` objects:

```php
$record = iterator_to_array(
    $document->getRecordsAsObject(Poi::class)
)[4];
```

does **not** return a record.

Instead, conversion fails because `"R"` cannot be converted to a `Gender` enum case, and an exception is thrown.

This makes `getRecordsAsObject()` appropriate when the input is expected to conform to a known structure and invalid data should be rejected.

### Comparing the three approaches

The same `"R"` value therefore produces different results:

| Method                           |  `age` | `gender = "R"` |
| -------------------------------- | -----: | -------------- |
| `getRecords()`                   | `"35"` | `"R"`          |
| `inferRecords()`                 |   `35` | `"R"`          |
| `inferRecords($inspector)`       |   `35` | `null`         |
| `getRecordsAsObject(Poi::class)` |   `35` | **Exception**  |

The difference is not simply about type conversion. Each method has a different relationship with the input data:

| Method                 | Purpose               | Type knowledge      | Invalid value          |
| ---------------------- | --------------------- | ------------------- | ---------------------- |
| `getRecords()`         | Read raw CSV data     | None                | Returned as-is         |
| `inferRecords()`       | Infer and parse data  | Discovered from CSV | Parsed value or `null` |
| `getRecordsAsObject()` | Map to a known object | Explicitly defined  | Exception              |

#### Which one should I use?

Use **`getRecords()`** when you want the raw CSV values and will handle conversion yourself.

Use **`inferRecords()`** when you want the library to discover useful types from the CSV without having to define a complete data model.

Use **`getRecordsAsObject()`** when you have a known object model and want the CSV to be converted according to that model, with conversion failures treated as errors.

In summary:

> **`getRecords()` preserves the input, `inferRecords()` interprets the input, and `getRecordsAsObject()` validates the input against an explicit object model.**

