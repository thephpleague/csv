---
layout: default
title: Converting a CSV into a JSON
---

# JSON conversion

The `JsonConverter` converts or store a CSV records collection into a JSON structure.

## Settings

Prior to converting your records collection into a JSON structure, you may wish to configure
the converter. 

<p class="message-warning">Because we are building a <code>JSON</code> structure, the <code>JsonConverter</code> object
throws generic <code>SPL Exception</code> instead of <code>League\Csv\Exception</code>.</p>

### JsonConverter::addFlags and JsonConverter::removeFlags

```php
public JsonConverter::addFlags(int ...$flag): self
public JsonConverter::removeFlags(int ...$flag): self
```

This method sets the JSON flags to be used during conversion. The method handles all the
flags supported by PHP `json_encode` function.

### JsonConverter::depth

```php
public JsonConverter::depth(int $depth): self
```

This method sets the JSON depth value during conversion. The method is a proxy to using the
`json_encode` depth parameter.

### JsonConverter::indentSize

```php
public JsonConverter::indentSize(int $indentSize): self
```

This method sets the JSON indentation size value if you use the `JSON_PRETTY_PRINT` flag. In
all other situation this value stored via this method is never used. By default, the identation
size is the same as in PHP (ie : 4 characters long).

### JsonConverter::formatter

```php
public JsonConverter::formatter(?callback $formatter): mixed
```

This method allow to apply a callback prior to `json_encode` your collection individual item.
Since the encoder does not rely on PHP's `JsonSerializable` interface but on PHP's `iterable`
structure. The expected conversion may differ to what you expect. This callback allows you to
specify how each item will be converted. The formatter should return a type that can be handled
by PHP `json_encode` function.

## Conversion

```php
public JsonConverter::convert(iterable $records): iterable<string>
public JsonConverter::encode(iterable $records): string
public JsonConverter::save(iterable $records, mixed $destination, $context = null): int
```

The `JsonConverter::convert` accepts an `iterable` which represents the records collection 
and returns a `iteratable` structure which will be lazily converted to JSON while avoiding
high memory usage.The class is built to handle large CSV documents but can be used with
small CSV document file if needed.

The `JsonConverter::encode` and `JsonConverter::save` methods a sugar syntactic methods to
ease store the JSON in a file or show it to the world via its full JSON string representation.

Here's a conversion example:

```php
$csv = Reader::createFromPath(__DIR__.'/test_files/prenoms.csv');
$csv->setDelimiter(';');
$csv->setHeaderOffset(0);

CharsetConverter::addTo($csv, 'iso-8859-15', 'utf-8');
$converter = JsonConverter::create()
    ->addFlags(JSON_PRETTY_PRINT, JSON_UNESCAPED_SLASHES)
    ->depth(2)
    ->indentSize(2)
    ->format(function (array $row) {
        $row['nombre'] = (int) $row['nombre'];
        $row['annee'] = (int) $row['annee'];
        $row['sexe']  = $row['sexe'] === 'M' ? 'male' : 'female';

        return $row;
    });

$records = Statement::create()->offset(3)->limit(2)->process($csv);

echo $converter->encode($records), PHP_EOL;
```

This will produce the following response:

```json
[
  {
    "prenoms": "Abdoulaye",
    "nombre": 15,
    "sexe": "male",
    "annee": 2004
  },
  {
    "prenoms": "Abel",
    "nombre": 14,
    "sexe": "male",
    "annee": 2004
  }
]
```

Of note, since the CSV document is not encoded in `UTF-8` we first convert it using the `CharsetConverter` class
otherwise an exceptio will be triggered when json encoding the data.

If we wanted to store the data instead of displaying it we could do the following

```diff
- echo $converter->encode($records), PHP_EOL;
+ $converter->save($records, 'my/new/document.json');
```

the generated CSV will then be stored at the `my/new/document.json` path.
The destination path can be specified using:

- a `SplFileObject` instance;
- a `SplFileInfo` instance;
- a resource created by `fopen`;
- a string;

If you provide a string or a `SplFileInfo` instance the file will be open using
the `w` open mode and you can provide an additional `$context` parameter to fine
tune where and how the JSON file will be stored.
