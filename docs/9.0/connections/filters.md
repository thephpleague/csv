---
layout: default
title: Controlling PHP Stream Filters
---

# Stream Filters

To ease performing operations on the CSV document as it is being read from or written to, the `Reader` and `Writer` objects allow attaching PHP stream filters to them.

## Detecting stream filter support

<p class="message-notice">Since version <code>9.7.0</code> the detection mechanism is simplified</p>

The following methods are added:

- `supportsStreamFilterOnRead` tells whether the stream filter API on reading mode is supported by the CSV object;
- `supportsStreamFilterOnWrite` tells whether the stream filter API on writing mode is supported by the CSV object;

```php
public AbstractCsv::supportsStreamFilterOnRead(void): bool
public AbstractCsv::supportsStreamFilterOnWrite(void): bool
```

```php
use League\Csv\Reader;
use League\Csv\Writer;

$reader = Reader::createFromPath('/path/to/my/file.csv', 'r');
$reader->supportsStreamFilterOnRead(); //returns true
$reader->supportsStreamFilterOnWrite(); //returns false

$writer = Writer::createFromFileObject(new SplTempFileObject());
$writer->supportsStreamFilterOnRead(); //returns false, the API can not be used
$writer->supportsStreamFilterOnWrite(); //returns false, the API can not be used
```

<p class="message-notice">The following methods still work but are deprecated since version <code>9.7.0</code></p>

```php
public AbstractCsv::supportsStreamFilter(void): bool
public AbstractCsv::getStreamFilterMode(void): int
```

The `supportsStreamFilter` tells whether the stream filter API is supported by the current object whereas the `getStreamFilterMode` returns the filter mode used to add new stream filters to the CSV object.
The filter mode value is one of the following PHP's constant:

- `STREAM_FILTER_READ` to add stream filter on read
- `STREAM_FILTER_WRITE` to add stream filter on write

Regardless of the stream filter API being supported by a specific CSV object, `getStreamFilterMode` will always return a value.

```php
use League\Csv\Reader;
use League\Csv\Writer;

$reader = Reader::createFromPath('/path/to/my/file.csv', 'r');
$reader->supportsStreamFilter(); //returns true
$reader->getStreamFilterMode(); //returns STREAM_FILTER_READ

$writer = Writer::createFromFileObject(new SplTempFileObject());
$writer->supportsStreamFilter(); //returns false, the API can not be used
$writer->getStreamFilterMode(); //returns STREAM_FILTER_WRITE
```

<p class="message-warning">A <code>League\Csv\Exception</code> exception will be thrown if you use the API on an object where <code>supportsStreamFilter</code> returns <code>false</code>.</p>

### Cheat sheet

Here's a table to quickly determine if PHP stream filters works depending on how the CSV object was instantiated.

| Named constructor      |   supports stream      |
|------------------------|------------------------|
| `createFromString`     |         true           |
| `createFromPath`       |         true           |
| `createFromStream`     |         true           |
| `createFromFileObject` |       **false**        |

## Adding a stream filter

```php
public AbstractCsv::addStreamFilter(string $filtername, mixed $params = null): self
public AbstractCsv::hasStreamFilter(string $filtername): bool
```

The `AbstractCsv::addStreamFilter` method adds a stream filter to the connection.

- The `$filtername` parameter is a string that represents the filter as registered using php `stream_filter_register` function or one of [PHP internal stream filter](http://php.net/manual/en/filters.php).

- The `$params` : This filter will be added with the specified parameters to the end of the list.

<p class="message-warning">Each time your call <code>addStreamFilter</code> with the same argument the corresponding filter is registered again.</p>

The `AbstractCsv::hasStreamFilter` method tells whether a specific stream filter is already attached to the connection.

```php
use League\Csv\Reader;
use MyLib\Transcode;

stream_filter_register('convert.utf8decode', Transcode::class);
// 'MyLib\Transcode' is a class that extends PHP's php_user_filter class

$reader = Reader::createFromPath('/path/to/my/chinese.csv', 'r');
if ($reader->supportsStreamFilterOnRead()) {
    $reader->addStreamFilter('convert.utf8decode');
    $reader->addStreamFilter('string.toupper');
}

$reader->hasStreamFilter('string.toupper'); //returns true
$reader->hasStreamFilter('string.tolower'); //returns false

foreach ($reader as $row) {
    // each row cell now contains strings that have been:
    // first UTF8 decoded and then uppercased
}
```

## Stream filters removal

Stream filters attached **with** `addStreamFilter` are:

- removed on the CSV object destruction.

Conversely, stream filters added **without** `addStreamFilter` are:

- not detected by the library.
- not removed on object destruction.

```php
use League\Csv\Reader;
use MyLib\Transcode;

stream_filter_register('convert.utf8decode', Transcode::class);
$fp = fopen('/path/to/my/chines.csv', 'r');
stream_filter_append($fp, 'string.rot13'); //stream filter attached outside of League\Csv
$reader = Reader::createFromStream($fp);
$reader->addStreamFilter('convert.utf8decode');
$reader->addStreamFilter('string.toupper');
$reader->hasStreamFilter('string.rot13'); //returns false
$reader = null;
// 'string.rot13' is still attached to `$fp`
// filters added using `addStreamFilter` are removed
```

## Bundled stream filters

The library comes bundled with the following stream filters:

- [RFC4180Field](/9.0/interoperability/rfc4180-field/) stream filter to read or write RFC4180 compliant CSV field;
- [CharsetConverter](/9.0/converter/charset/) stream filter to convert your CSV document content using the `mbstring` extension;
- [SkipBOMSequence](/9.0/connections/bom/) stream filter to skip your CSV document BOM sequence if present;
