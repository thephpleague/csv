---
layout: default
title: Controlling PHP Stream Filters
---

# Stream Filters

To ease performing operations on the CSV document as it is being read from or written to, you can add PHP stream filters to the `Reader` and `Writer` objects.

## Detecting stream filter support

~~~php
<?php

public AbstractCsv::supportsStreamFilter(void): bool
public AbstractCsv::getStreamFilterMode(void): int
~~~

The `supportsStreamFilter` tells whether the stream filter API is supported by the current object whereas the `getStreamFilterMode` returns the filter mode used to add new stream filters to the CSV object.  
The filter mode value is one of the following PHP's constant:

- `STREAM_FILTER_READ` to add stream filter on read
- `STREAM_FILTER_WRITE` to add stream filter on write

Regardless of stream filter API support by a specific CSV object, `getStreamFilterMode` will always return a value.

~~~php
<?php

use League\Csv\Reader;
use League\Csv\Writer;

$reader = Reader::createFromPath('/path/to/my/file.csv');
$reader->supportsStreamFilter(); //return true
$reader->getStreamFilterMode(); //return STREAM_FILTER_READ

$writer = Writer::createFromFileObject(new SplTempFileObject());
$writer->supportsStreamFilter(); //return false the API can not be use
$writer->getStreamFilterMode(); //return STREAM_FILTER_WRITE
~~~

<p class="message-warning">A <code>LogicException</code> exception will be thrown if you use the API on a object where <code>supportsStreamFilter</code> returns <code>false</code>.</p>

### Cheat sheet

Here's a table to quickly determine if PHP stream filters works depending on how the CSV object was instantiated.

| Named constructor      | `supportsStreamFilter` |
|------------------------|------------------------|
| `createFromString`     |         true           |
| `createFromPath  `     |         true           |
| `createFromStream`     |         true           |
| `createFromFileObject` |       **false**        |


## Adding a stream filter

~~~php
<?php

public AbstractCsv::addStreamFilter(string $filtername, mixed $params = null): self
public AbstractCsv::hasStreamFilter(string $filtername): bool
~~~

The `AbstractCsv::addStreamFilter` method adds a stream filter to the connection.

- The `$filtername` parameter is a string that represents the filter as registered using php `stream_filter_register` function or one of [PHP internal stream filter](http://php.net/manual/en/filters.php).

- The `$params` : This filter will be added with the specified paramaters to the end of the list.

<p class="message-warning">Each time your call <code>addStreamFilter</code> with the same argument the corresponding filter is registered again.</p>

The `AbstractCsv::hasStreamFilter` method tells whether a specific stream filter is already attached to the connection.

~~~php
<?php

use League\Csv\Reader;
use MyLib\Transcode;

stream_filter_register('convert.utf8decode', Transcode::class);
// 'MyLib\Transcode' is a class that extends PHP's php_user_filter class

$reader = Reader::createFromPath('/path/to/my/chinese.csv');
if ($reader->supportsStreamFilter()) {
	$reader->addStreamFilter('convert.utf8decode');
	$reader->addStreamFilter('string.toupper');
}

$reader->hasStreamFilter('string.toupper'); //returns true
$reader->hasStreamFilter('string.tolower'); //returns false

foreach ($reader as $row) {
	// each row cell now contains strings that have been:
	// first UTF8 decoded and then uppercased
}
~~~

## Stream filters removal

Stream filters attached **with** `addStreamFilter` are:

- removed on the CSV object destruction.

Conversely, stream filters added **without** `addStreamFilter` are:

- not detected by the library.
- not removed on object destruction.

~~~php
<?php

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
~~~
