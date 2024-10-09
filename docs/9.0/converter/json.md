---
layout: default
title: Converting a CSV into a JSON
---

# JSON conversion

The `JsonConverter` converts or store a collection into a JSON structure.

<p class="message-warning">Because we are building a <code>JSON</code> structure, the <code>JsonConverter</code> object
throws generic <code>SPL Exception</code> instead of <code>League\Csv\Exception</code>.</p>

To reduce memory usage, the converter transform one record at a time. This means that the class object settings are
geared toward a single element and not the whole claass,

## Settings

Prior to converting your collection into a JSON structure, you may wish to configure it.

### JSON encode flags

```php
public JsonConverter::addFlags(int ...$flag): self
public JsonConverter::removeFlags(int ...$flag): self
public JsonConverter::useFlags(int ...$flag): bool
```

These methods set the JSON flags to be used during conversion. The method handles all the
flags supported by PHP `json_encode` function.

If you prefer a more expressive way for setting the flags you can use `with*` and `without*` methods
whose name are derived from PHP JSON constants.

```php
$converter = JsonConverter::create()
    ->addFlags(JSON_PRETTY_PRINT, JSON_UNESCAPED_SLASHES, JSON_FORCE_OBJECT)
    ->removeFlags(JSON_HEX_QUOT);

//is equivalent to

$converter = JsonConverter::create()
    ->withPrettyPrint()
    ->withUnescapedSlashes()
    ->withForceObject()
    ->withoutHexQuot();
```

<p class="message-notice">The class always uses the <code>JSON_THROW_ON_ERROR</code> to enable stop the collection
conversion in case of an error. That's why adding or removing the flag using the methods will have no effect on its
usage, the flag is <strong>ALWAYS</strong> set.</p>

To quickly check which flags is being used, calle the `JsonConverter::useFlags` method. As for the other methods
a more expressive way exists.

```php
$converter = JsonConverter::create()
    ->addFlags(JSON_PRETTY_PRINT, JSON_UNESCAPED_SLASHES, JSON_FORCE_OBJECT)
    ->removeFlags(JSON_HEX_QUOT);
    
$converter->useFlags(JSON_PRETTY_PRINT, JSON_FORCE_OBJECT); //returns true both flags are used
$converter->useFlags(JSON_PRETTY_PRINT, JSON_HEX_QUOT); //returns false at least one of the flag is not set
$converter->usePrettyPrint();  // returns true the flag is used
$converter->useThrowOnError(); // returns true the flag is always used
$converter->useHexQuot();      // returns false the flag is not used
```

### Json encode depth

```php
public JsonConverter::depth(int $depth): self
```

This method sets the JSON depth value during conversion. The method is a proxy to using the
`json_encode` depth parameter.

### Json encode indentation

```php
public JsonConverter::indentSize(int $indentSize): self
```

This method sets the JSON indentation size value if you use the `JSON_PRETTY_PRINT` flag. In
all other situation this value stored via this method is never used. By default, the identation
size is the same as in PHP (ie : 4 characters long).

### Json encode formatter

```php
public JsonConverter::formatter(?callback $formatter): mixed
```

This method allow to apply a callback prior to `json_encode` your collection individual item.
Since the encoder does not rely on PHP's `JsonSerializable` interface but on PHP's `iterable`
structure. The resulting conversion may differ to what you expect. This callback allows you to
specify how each item will be converted. The formatter should return a type that can be handled
by PHP `json_encode` function.

## Conversion

```php
public JsonConverter::convert(iterable $records): iterable<string>
public JsonConverter::encode(iterable $records): string
public JsonConverter::save(iterable $records, mixed $destination, $context = null): int
```

The `JsonConverter::convert` accepts an `iterable` which represents the records collection
and returns a `iterable` structure lazily converted to JSON one item at a time to avoid
high memory usage. The class is built to handle large CSV documents but can be used with
small CSV document file if needed.

The `JsonConverter::encode` and `JsonConverter::save` methods are sugar syntactic methods to
ease storing the JSON in a file or displaying it in its full JSON string representation.

Here's a conversion example:

```php
$document = Reader::createFromPath(__DIR__.'/test_files/prenoms.csv');
$document->setDelimiter(';');
$document->setHeaderOffset(0);

CharsetConverter::addTo($document, 'iso-8859-15', 'utf-8');
$converter = JsonConverter::create()
    ->withPrettyPrint()
    ->withUnescapedSlashes()
    ->depth(2)
    ->indentSize(2)
    ->formatter(function (array $row) {
        $row['nombre'] = (int) $row['nombre'];
        $row['annee'] = (int) $row['annee'];
        $row['sexe']  = $row['sexe'] === 'M' ? 'male' : 'female';

        return $row;
    });

echo $converter->encode($document->slice(3, 2)), PHP_EOL;
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
otherwise an exception will be triggered when json encoding the data.

If we wanted to store the data instead of displaying it we could do the following

```diff
- echo $converter->encode($document), PHP_EOL;
+ $converter->save($document, 'my/new/document.json');
```

the generated CSV will then be stored at the `my/new/document.json` path.
The destination path can be specified using:

- a `SplFileObject` instance;
- a `SplFileInfo` instance;
- a resource created by `fopen`;
- a string;

If you provide a string or a `SplFileInfo` instance:

- the file will be open using the `w` open mode.
- You can provide an additional `$context` parameter, a la `fopen`, to fine tune where and how the JSON file will be stored.
