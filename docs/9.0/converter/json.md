---
layout: default
title: Converting a collection into a JSON
---

# JSON conversion

The `JsonConverter` converts or store a collection into a JSON structure.

<p class="message-warning">Because we are building a <code>JSON</code> structure, the <code>JsonConverter</code> object
throws generic <code>SPL Exception</code> instead of <code>League\Csv\Exception</code>.</p>

To reduce memory usage, the converter transforms one collection element at a time. This means
that the class settings are geared toward a single element and not the whole collection.
The only pre-requisite is that each element of your collection must either be an
object implementing the `JsonSerializable` interface or a PHP structure that
can be encoded via `json_encode`.

## Instantiation

To create a new `JsonConverter` instance you can call its constructor or the `create` named constructor.
The latter is deprecated since version `9.22.0` and will be removed whenever the next major version
is released.

```diff
- JsonConverter::create()->download($record);
+ (new JsonConverter())->download($record);
```

With the release of `PHP8.4`, the parenthesis around the constructor are no longer needed.

```php
(new JsonConverter())->download($record); //old usage (deprecated in PHP8.4+)
new JsonConverter()->download($record); //new and fast-forward method usage
```

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

If you prefer a more expressive way for setting the flags you can use the `with*` and `without*` methods
whose name are derived from PHP JSON constants.

```php
$converter = (new JsonConverter())
    ->addFlags(JSON_PRETTY_PRINT, JSON_HEX_QUOT, JSON_UNESCAPED_SLASHES, JSON_FORCE_OBJECT)
    ->removeFlags(JSON_HEX_QUOT);

//is equivalent to

$converter = (new JsonConverter())
    ->withPrettyPrint()
    ->withHexQuot()
    ->withUnescapedSlashes()
    ->withForceObject()
    ->withoutHexQuot();
```

<p class="message-notice">The class always uses the <code>JSON_THROW_ON_ERROR</code> flag to enable stopping the
conversion in case of an error. That's why adding or removing the flag using the methods will have no effect
on its usage, the flag is <strong>ALWAYS</strong> set.</p>

To quickly check which flags is being used, calle the `JsonConverter::useFlags` method. As for the other methods
a more expressive way exists.

```php
$converter = (new JsonConverter())
    ->addFlags(JSON_PRETTY_PRINT, JSON_UNESCAPED_SLASHES, JSON_FORCE_OBJECT)
    ->removeFlags(JSON_HEX_QUOT);
    
$converter->useFlags(JSON_PRETTY_PRINT, JSON_FORCE_OBJECT); //returns true both flags are used
$converter->useFlags(JSON_PRETTY_PRINT, JSON_HEX_QUOT); //returns false at least one of the flag is not set
$converter->usePrettyPrint();  // returns true the flag is used
$converter->useThrowOnError(); // returns true the flag is always used
$converter->useHexQuot();      // returns false the flag is not used
$converter->flags;             //returns the actual flags value (as used by json_encode)
```

<p class="message-info">Starting with version <code>9.19.0</code> use
<code>JsonConverter::withPrettyPrint</code> to directly set the indent size with the argument <code>$identSize</code>
to its call</p>

```php
$converter = (new JsonConverter())->withPrettyPrint(2);
```

will produce a JSON with an indentation size of `2`.

### Json encode depth

```php
public JsonConverter::depth(int $depth): self
```

This method sets the JSON depth value during conversion. The method is a proxy to using the
`json_encode` depth parameter.

```php
$converter = (new JsonConverter())->depth(2);
$converter->depth; //returns the actual depth value (as used by json_encode) 
```

### Json encode indentation

<p class="message-warning">This method is deprecated as of version <code>9.19.0</code> use
<code>JsonConverter::withPrettyPrint</code> instead and add the <code>$identSize</code> argument
to its call</p>

```php
public JsonConverter::indentSize(int $indentSize): self
```

This method sets the JSON indentation size value if you use the `JSON_PRETTY_PRINT` flag. In
all other situation this value stored via this method is never used. By default, the indentation
size is the same as in PHP (ie : 4 characters long).

```diff
- $converter = (new JsonConverter())->indentSize(2);
+ $converter = (new JsonConverter())->withPrettyPrint(2);
$converter->indentSize; //returns the value used
```

### Json encode formatter

```php
public JsonConverter::formatter(?callable $formatter): self
```

This method allow to apply a callback prior to `json_encode` your collection individual item.
Since the encoder does not rely on PHP's `JsonSerializable` interface but on PHP's `iterable`
structure. The resulting conversion may differ to what you expect. This callback allows you to
specify how each item will be converted. The formatter should return a type that can be handled
by PHP `json_encode` function.

### Chunk Size

<p class="message-notice">available since version <code>9.18.0</code></p>

```php
public JsonConverter::chunkSize(int $chunkSize): self
```

This method sets the number of rows to buffer before convert into JSON string. This allow
for faster conversion while retaining the low memory usage. Of course, the default
chunk size can vary for one scenario to another. The correct size is therefore
left to the user discretion. By default, the value is `500`. The value can not
be lower than one otherwise a exception will be thrown.

```php
$converter = (new JsonConverter())->chunkSize(1_000);
$converter->chunkSize; //returns the value used
```

### JsonConverter::when

<p class="message-info">New feature introduced in version <code>9.22.0</code></p>

This method allows to conditionally create your converter depending on the success or
failure of a condition.

```php
use League\Csv\JsonConverter;

$converter = (new JsonConverter());
if ($condition) {
    $converter = $converter->chunkSize(1_000);
} else {
    $converter = $converter->chunkSize(200);
}
```

becomes

```php
$stmt = (new JsonConverter())
    ->when(
        $condition,
        fn (JsonConverter $c) => $c->chunkSize(1_000),
        fn (JsonConverter $c) => $c->chunkSize(200),
    );
)
```

The `else` expression is not required but if present in **MUST BE** a callable which only
accepts the `JsonConverter` instance and returns `null` or a `JsonConverter` instance.

The only requirements are:

- that the condition is a `boolean` or a callable that returns a `boolean`.
- the callback returns a `JsonConverter` instance or null.

## Conversion

```php
public JsonConverter::convert(iterable $records): iterable<string>
public JsonConverter::encode(iterable $records): string
public JsonConverter::save(iterable $records, mixed $destination, $context = null): int
```

The `JsonConverter::convert` accepts an `iterable` which represents the records collection
and returns a `iterable` structure lazily converted to JSON one item at a time to avoid
high memory usage. The class is built to handle large collection but can be used with
small ones if needed.

The `JsonConverter::encode` and `JsonConverter::save` methods are sugar syntactic methods to
ease storing the JSON in a file or displaying it in its full JSON string representation.

Here's a conversion example:

```php
$document = Reader::from(__DIR__.'/test_files/prenoms.csv');
$document->setDelimiter(';');
$document->setHeaderOffset(0);

CharsetConverter::addTo($document, 'iso-8859-15', 'utf-8');
$converter = (new JsonConverter())
    ->withPrettyPrint(2)
    ->withUnescapedSlashes()
    ->depth(2)
    ->formatter(function (array $row) {
        $row['nombre'] = (int) $row['nombre'];
        $row['annee'] = (int) $row['annee'];
        $row['sexe']  = $row['sexe'] === 'M' ? 'male' : 'female';

        //other attributes of $row are not affected
        //and will be rendered as they are.

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

Of note, if your data is not encoded in `UTF-8` it will trigger a JSON exception. In our example, we first convert
The document data to `utf-8` using the `CharsetConverter` class to avoid the exception triggering.

If we wanted to store the data instead of displaying it we could do the following

```diff
- echo $converter->encode($document), PHP_EOL;
+ $converter->save($document, 'my/new/document.json');
```

the generated JSON will then be stored at the `my/new/document.json` path.
The destination path can be specified using:

- a `SplFileObject` instance;
- a `SplFileInfo` instance;
- a resource created by `fopen`;
- a string;

If you provide a string or a `SplFileInfo` instance:

- the file will be open using the `w` open mode.
- You can provide an additional `$context` parameter, a la `fopen`, to fine tune where and how the JSON file will be stored.

## JSON Format

<p class="message-notice">available since version <code>9.25.0</code></p>

When exporting a collection to JSON, you can choose between two output styles:

- **Standard JSON** – the entire collection is represented as a single JSON array.
- **JSON Lines (NDJSON)** – each record is written as a separate [JSON object on its own line](https://jsonlines.org/).

The `JsonConverter::format` method allows you to configure which style to use:

```php
use League\Csv\JsonConverter;
use League\Csv\JsonFormat;

echo (new JsonConverter())
    ->format(JsonFormat::NdJson)
    ->encode([
        ['city' => 'Accra', 'country' => 'Ghana'],
        ['city' => 'Nouakchott', 'country' => 'Mauritania'],
        ['city' => 'Gaborone', 'country' => 'Botswana'],
    ]);
// returns
// {"city":"Accra","country":"Ghana"}
// {"city":"Nouakchott","country":"Mauritania"}
// {"city":"Gaborone","country":"Botswana"}
```

You can inspect the current format via the `JsonConverter::format` property.
This property always contains one of the `JsonFormat` enum values:

- `JsonFormat::Standard`— produces a single JSON array containing all records.
- `JsonFormat::NdJson` — produces one JSON object per line.
- `JsonFormat::NdJsonHeader` — produces one JSON list per line with the first line representing the file header
- `JsonFormat::NdJsonHeaderLess` — produces one JSON list per line without any header

<p class="message-notice"><code>JsonFormat::NdJsonHeader</code> and <code>JsonFormat::NdJsonHeaderLess</code> are added
in version <code>9.26</code>.</p>

<p class="message-warning">The converter does not restrict JSON encoding options when using
<code>JsonFormat::NdJson</code>. For example, enabling <code>JSON_PRETTY_PRINT</code>
will still work, but it will technically generate a non-compliant JSON Lines output.</p>

## Download

<p class="message-warning">If you are using the package inside a framework please use the framework recommended way instead of the describe mechanism hereafter.</p>

To download the generated JSON you can use the `JsonConverter::download` method. The method returns
the total number of bytes sent just like the `JsonConverter::save` method and enable downloading the JSON on the fly.

### General purpose

```php
use League\Csv\Reader;
use League\Csv\JsonConverter;

$reader = Reader::from('file.csv');
$reader->setHeaderOffset(0);

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Type: application/json; charset=UTF-8');
header('Content-Description: File Transfer');
header('Content-Disposition: attachment; filename="name-for-your-file.json"');

(new JsonConverter())->download($reader);
die;
```

In this scenario, you have to specify all the headers for the file to be downloaded.

### Using a filename

If you want to reduce the number of headers to write you can specify the downloaded filename.

```php
use League\Csv\Reader;
use League\Csv\JsonConverter;

$reader = Reader::from('file.csv');
$reader->setHeaderOffset(0);

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
 //the filename will be the name of the downloaded json as shown by your HTTP client!
(new JsonConverter())->download($reader, 'generated_file.json');
die;
```

<p class="message-notice">The caching headers are given as an example for using additional headers, it is up to the user to decide if those headers are needed or not.</p>
