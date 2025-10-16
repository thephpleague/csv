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

$reader = Reader::from('/path/to/my/file.csv', 'r');
$reader->supportsStreamFilterOnRead(); //returns true
$reader->supportsStreamFilterOnWrite(); //returns false

$writer = Writer::from(new SplTempFileObject());
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

$reader = Reader::from('/path/to/my/file.csv', 'r');
$reader->supportsStreamFilter(); //returns true
$reader->getStreamFilterMode(); //returns STREAM_FILTER_READ

$writer = Writer::from(new SplTempFileObject());
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
public AbstractCsv::appendStreamFilterOnRead(string $filtername, mixed $params = null): self
public AbstractCsv::prependStreamFilterOnRead(string $filtername, mixed $params = null): self
public AbstractCsv::appendStreamFilterOnWrite(string $filtername, mixed $params = null): self
public AbstractCsv::prependStreamFilterOnReadWrite(string $filtername, mixed $params = null): self
public AbstractCsv::hasStreamFilter(string $filtername): bool
```

The `AbstractCsv::addStreamFilter` method adds a stream filter to the connection.

<div class="message-notice">
<ul>
<li><code>addStreamFilter</code> is deprecated since version <code>9.21.0</code></li>
<li><code>appendStreamFilterOnRead</code> is available since <code>9.21.0</code></li>
<li><code>prependStreamFilterOnRead</code> is available since <code>9.21.0</code></li>
<li><code>appendStreamFilterOnWrite</code> is available since <code>9.21.0</code></li>
<li><code>prependStreamFilterOnWrite</code> is available since <code>9.21.0</code></li>
</ul>
</div>

- The `$filtername` parameter is a string that represents the filter as registered using php `stream_filter_register` function or one of [PHP internal stream filter](http://php.net/manual/en/filters.php).
- The `$params` : This filter will be added with the specified parameters to the end of the list.

The `appendStreamFilterOn*` methods add the stream filter at the end of the stream filter chain whereas
`prependStreamFilterOn*` methods add the stream filter at the start of the chain. Both methods share
the same arguments and the same return type.

<p class="message-warning">Each time your call a method with the same argument the corresponding filter is attached again.</p>

The `AbstractCsv::hasStreamFilter` method tells whether a specific stream filter is already attached to the connection.

```php
use League\Csv\Reader;
use MyLib\Transcode;

stream_filter_register('convert.utf8decode', Transcode::class);
// 'MyLib\Transcode' is a class that extends PHP's php_user_filter class

$reader = Reader::from('/path/to/my/chinese.csv', 'r');
if ($reader->supportsStreamFilterOnRead()) {
    $reader->appendStreamFilterOnRead('convert.utf8decode');
    $reader->appendStreamFilterOnRead('string.toupper');
}

$reader->hasStreamFilter('string.toupper'); //returns true
$reader->hasStreamFilter('string.tolower'); //returns false

foreach ($reader as $row) {
    // each row cell now contains strings that have been:
    // first UTF8 decoded and then uppercased
}
```

## Stream filters removal

Stream filters attached **with** `addStreamFilter`, `appendStreamFilterOn*`, `prependStreamFilterOn*` are:

- removed on the CSV object destruction.

Conversely, stream filters added **without** the feature are:

- not detected by the library.
- not removed on object destruction.

```php
use League\Csv\Reader;
use MyLib\Transcode;

stream_filter_register('convert.utf8decode', Transcode::class);
$fp = fopen('/path/to/my/chines.csv', 'r');
stream_filter_append($fp, 'string.rot13'); //stream filter attached outside of League\Csv
$reader = Reader::from($fp);
$reader->prependStreamFilterOnRead('convert.utf8decode');
$reader->prependStreamFilterOnRead('string.toupper');
$reader->hasStreamFilter('string.rot13'); //returns false
$reader = null;
// 'string.rot13' is still attached to `$fp`
// filters added using `addStreamFilter` are removed
```

## Bundled stream filters

The library comes bundled with the following stream filters:

- [EncloseField](/9.0/interoperability/enclose-field/) stream filter to force field enclosure on write;
- [RFC4180Field](/9.0/interoperability/rfc4180-field/) stream filter to read or write RFC4180 compliant CSV field;
- [CharsetConverter](/9.0/converter/charset/) stream filter to convert your CSV document content using the `mbstring` extension;

## Custom Stream Filter

<p class="message-info">Available since version <code>9.21.0</code></p>

Sometimes you may encounter a scenario where you need to create a specific stream filter
to resolve your issue. Instead of having to put up with the hassle of creating a
fully fledged stream filter, the package provides a simple feature to register any callback
as a stream filter and to use it either with a CSV class or with PHP stream resources.

### Registering the callback

Before using your custom stream filter you will first need to register it via the `CallbackStreamFilter` class.
This class is a global registry that will store all your custom callbacks filters.

```php
use League\Csv\CallbackStreamFilter;

CallbackStreamFilter::register('myapp.to.upper', strtoupper(...));
```

<p class="message-warning"><code>CallbackStreamFilter::register</code> registers your callback
globally. So you only need to register it once. Preferably in your container definition if you
are using a framework.</p>

The callback signature is the following

```php
callable(string $bucket [, mixed $params]): string
```

- the `$bucket` parameter represents the chunk of the stream you will be operating on.
- the `$params` represents an additional, **optional**, parameter you may pass onto the callback when it is being attached.

Once registered you can apply the filter via its `$filtername`. It is possible to register multiple times the same callback
but each registration needs to be done with a unique name otherwise an exception will be triggered.

You can always check for the existence of your registered filter by calling the `CallbackStreamFilter::isRegistered` method.
The method will only return `true` for filters registered via the class; otherwise `false` is returned.

```php
CallbackStreamFilter::isRegistered('myapp.to.upper'); 
//returns true - exists; was registered in the previous example
CallbackStreamFilter::isRegistered('myapp.to.lower'); 
//returns false - does not exist; is not registered by StreamFilter
CallbackStreamFilter::isRegistered('string.tolower'); 
//returns false - exits, is registered by PHP itself not by StreamFilter
```

The class lists all the registered filter names by calling the

```php
CallbackStreamFilter::registeredFilterNames(); // returns a list
```

And also returning a specific registered callback via the `callback` method.

```php
CallbackStreamFilter::callback($filtername); // returns a Closure
```

If no callback associated to `$filtername` exists, an exception will be thrown.

<p class="message-info">To avoid conflict with already registered stream filters a best
practice is to namespace your own filters by using a unique prefix. Instead of
naming it <code>string.to.lower</code> you should name it <code><strong>myapp.</strong>string.to.lower</code>
where <code>myapp</code> is specific for your own codebase.</p>

### Applying the callback

Once your custom stream filter registered you can either:

- directly use your CSV accessing class API as described above in the documentation;
- use PHP native API if you know it;
- use the package `StreamFilter` class which exposes an simpler API

The `StreamFilter` can be used with any registered `filter` and enable attaching them using the following
public static methods:

- `StreamFilter::appendOnReadTo`
- `StreamFilter::appendOnWriteTo`
- `StreamFilter::prependOnReadTo`
- `StreamFilter::prependOnWriteTo`

They will all add the filter to the stream filter chain attached to the structure
(League/CSV objects or PHP stream resource). They all share the same signature and only differ in:

- where in the chain the filter is added (at the top or at the bottom of the stream filter chain);
- which mode (read or write) will be used;
- their return value may be a `Reader` or a `Writer` instance or a reference to the attached stream filter.

To illustrate their usage let's check the two examples below, one with the `Reader` class and another one
with a PHP stream resource.

### Usage with CSV objects

Let's imagine we have a CSV document using the return carrier character (`\r`) as the end of line character.
This type of document is parsable by the package but only if you enable the deprecated `auto_detect_line_endings` ini setting.

If you no longer want to rely on that feature which has been deprecated since PHP 8.1 and will be
removed from PHP once PHP9.0 is released, you can, as an alternative, use the `StreamFilter`
instead to replace the offending character with a supported alternative.

```php
use League\Csv\CallbackStreamFilter;
use League\Csv\Reader;
use League\Csv\StreamFilter;

$csv = "title1,title2,title3\r". 
     . "content11,content12,content13\r"
     . "content21,content22,content23\r";

$document = Reader::fromString($csv);
$document->setHeaderOffset(0);

CallbackStreamFilter::register(
    'myapp.replace.eol', 
    fn (string $bucket): string => str_replace("\r", "\n", $bucket)
);
StreamFilter::appendOnReadTo($document, 'myapp.replace.eol');

return $document->first();
// returns [
//    'title1' => 'content11',
//    'title2' => 'content12',
//    'title3' => 'content13',
// ]
```

The `appendOnReadTo` method will check for the availability of the filter via its
name `myapp.replace.eol`. If  it is not present a `LogicException` will be
thrown, otherwise it will attach the filter to the CSV document object at the
bottom of the stream filter chain using the reading mode.

<p class="message-warning">On read, the CSV document content is <strong>never changed or replaced</strong>.
However, on write, the changes <strong>are persisted</strong> into the created document.</p>

### Usage with streams

<p class="message-notice">In the following example we will use the optional <code>$params</code> parameter
to add a specific behaviour to our callback</p>

```php
use League\Csv\StreamFilter;

$csv = <<<CSV
title1,title2,title3
content11,content12,content13
content21,content22,content23
CSV;

$stream = tmpfile();
fwrite($stream, $csv);

// We first check to see if the callback is not already registered
// without the check a LogicException would be thrown on
// usage or on callback registration
if (!CallbackStreamFilter::isRegistered('myapp.replace.string')) {
    CallbackStreamFilter::register(
        'myapp.replace.string',
        function (string $bucket, array $params): string {
            return str_replace(
                $params['search'], 
                $params['replace'], 
                $bucket
            );
        }
    );
}

$sfp = StreamFilter::appendOnReadTo(
    $stream, 
    'myapp.replace.string', 
    [
        'search' => ['content', '1', '2', '3'],
        'replace' => ['contenu ', 'A', 'B', 'C'],
    ],
);

rewind($stream);
$data = [];
while (($record = fgetcsv($stream, 1000, ',')) !== false) {
    $data[] = $record;
}
var_dump($data[1]);
//returns ['contenu AA', 'contenu AB', 'contenu AC']

StreamFilter::remove($sfp); //we remove the stream filter from the $stream resource

rewind($stream);
$altData = [];
while (($record = fgetcsv($stream, 1000, ',')) !== false) {
    $altData[] = $record;
}
var_dump($altData[1]);
//returns ['content11', 'content12', 'content13']

fclose($stream);
```

When using one of the attaching methods with a resource, the method returns a stream reference
that you can use later on if you wish to remove the stream filter. When using the method with
the `Reader` and/or the `Writer` class, the methods return the CSV class instance because
both classes manage the filter lifecycle themselves and automatically remove them on
the class destruction.
