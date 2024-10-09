---
layout: default
title: Converting Csv records character encoding
---

# Charset conversion

The `CharsetConverter` class converts your CSV records using the `mbstring` extension and its [supported character encodings](http://php.net/manual/en/mbstring.supported-encodings.php).

## Settings

```php
public CharsetConverter::inputEncoding(string $input_encoding): self
public CharsetConverter::outputEncoding(string $output_encoding): self
```

The `inputEncoding` and `outputEncoding` methods set the object encoding properties. By default, the input encoding and the output encoding are set to `UTF-8`.

When building a `CharsetConverter` object, the methods do not need to be called in any particular order, and may be called multiple times. Because the `CharsetConverter` is immutable, each time its setter methods are called they return a new object without modifying the current one.

<p class="message-warning">If the submitted charset is not supported by the <code>mbstring</code> extension an <code>OutOfRangeException</code> will be thrown.</p>

## Conversion

```php
public CharsetConverter::convert(iterable $records): iterable
```

`CharsetConverter::convert` converts the collection of records according to the encoding settings.

```php
use League\Csv\CharsetConverter;

$csv = new SplFileObject('/path/to/french.csv', 'r');
$csv->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);

$encoder = (new CharsetConverter())->inputEncoding('iso-8859-15');
$records = $encoder->convert($csv);
```

The resulting data is converted from `iso-8859-15` to the default `UTF-8` since no output encoding charset was set using the `CharsertConverter::outputEncoding` method.

## CharsetConverter as a Writer formatter

```php
public CharsetConverter::__invoke(array $record): array
```

Using the `CharsetConverter::__invoke` method, you can register a `CharsetConverter` object as a record formatter using the [Writer::addFormatter](/9.0/writer/#record-formatter) method.

```php
use League\Csv\CharsetConverter;
use League\Csv\Writer;

$encoder = (new CharsetConverter())
    ->inputEncoding('utf-8')
    ->outputEncoding('iso-8859-15')
;

$writer = Writer::createFromPath('/path/to/your/csv/file.csv');
$writer->addFormatter($encoder);

$writer->insertOne(["foo", "bébé", "jouet"]);
//all 'utf-8' characters are now automatically encoded into 'iso-8859-15' charset
```

## CharsetConverter as a PHP stream filter

```php
public static CharsetConverter::addTo(AbstractCsv $csv, string $input_encoding, string $output_encoding): AbstractCsv
public static CharsetConverter::register(): void
public static CharsetConverter::getFiltername(string $input_encoding, string $output_encoding): string
```

### Usage with CSV objects

If your CSV object supports PHP stream filters then you can use the `CharsetConverter` class as a PHP stream filter using the library [stream filtering mechanism](/9.0/connections/filters/) instead.

The `CharsetConverter::addTo` static method:

- registers the `CharsetConverter` class under the generic filtername `convert.league.csv.*` if it is not registered yet;
- configures the stream filter using the supplied parameters;
- adds the configured stream filter to the submitted CSV object;

```php
use League\Csv\CharsetConverter;
use League\Csv\Writer;

$writer = Writer::createFromPath('/path/to/your/csv/file.csv');
CharsetConverter::addTo($writer, 'utf8', 'iso-8859-15');

$writer->insertOne(["foo", "bébé", "jouet"]);
//all 'utf-8' characters are now automatically encoded into 'iso-8859-15' charset
```

### Usage with PHP stream resources

To use this stream filter outside `League\Csv` objects you need to:

- register the stream filter using `CharsetConverter::register` method.
- use `CharsetConverter::getFiltername` with one of PHP's attaching stream filter functions with the correct arguments as shown below:

```php
use League\Csv\CharsetConverter;

CharsetConverter::register();

$resource = fopen('/path/to/my/file', 'r');
$filter = stream_filter_append(
    $resource,
    CharsetConverter::getFiltername('utf-8', 'iso-8859-15'),
    STREAM_FILTER_READ
);

while (false !== ($record = fgetcsv($resource))) {
    //$record is correctly encoded
}
```

<p class="message-info">If your system supports the <code>iconv</code> extension you should use PHP's built in iconv stream filters instead for better performance.</p>

<p class="message-info">new in version <code>8.17.0</code></p>

Tbe code above can be simplified using one of the two static methods:

```php
use League\Csv\CharsetConverter;

$resource = fopen('/path/to/my/file', 'r');
$filter = CharsetConverter::appendTo($resource, 'utf-8', 'iso-8859-15');
echo stream_get_contents($resource); // the return string is converted from 'utf-8' to 'iso-8859-15'
```

The `appendTo` and `prependTo` static methods will make sure to register the stream filter before attaching it to the
resource.
