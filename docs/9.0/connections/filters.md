---
layout: default
title: Controlling PHP Stream Filters
---

# Stream Filters

~~~php
<?php

public AbstractCsv::hasStreamFilter(string $filtername): bool
public AbstractCsv::supportsStreamFilter(void): bool
public AbstractCsv::addStreamFilter(string $filtername): self
~~~

To ease performing operations on the CSV document as it is being read from or written to, you can add PHP stream filters to the `Reader` and `Writer` objects.

## Detecting stream filter support

~~~php
<?php

public AbstractCsv::supportsStreamFilter(void): bool
~~~

Tells whether the stream filter API is supported by the current object.

~~~php
<?php

use League\Csv\Reader;
use League\Csv\Writer;

$reader = Reader::createFromPath('/path/to/my/file.csv');
$reader->supportsStreamFilter(); //return true

$writer = Writer::createFromFileObject(new SplTempFileObject());
$writer->supportsStreamFilter(); //return false the API can not be use
~~~

<p class="message-warning"><strong>Warning:</strong> A <code>LogicException</code> exception may be thrown if you try to use the API under certain circumstances without prior validation using <code>supportsStreamFilter</code></p>

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

public AbstractCsv::addStreamFilter(string $filtername): self
public AbstractCsv::hasStreamFilter(string $filtername): bool
~~~

The `$filtername` parameter is a string that represents the filter as registered using php `stream_filter_register` function or one of PHP internal stream filter.

The `AbstractCsv::addStreamFilter` method adds a stream filter to the connection.

<p class="message-warning"><strong>Warning:</strong> Each time your call <code>addStreamFilter</code> with the same argument the corresponding filter is register again.</p>

The `AbstractCsv::hasStreamFilter`: tells whether a stream filter is already attached to the connection.

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

<p class="message-info">Attached stream filters are cleared on the document destruction.</p>

~~~php
<?php

use League\Csv\Reader;

$fp = fopen('/path/to/my/chines.csv', 'r');
$reader = Reader::createFromStream($fp);
if ($reader->supportsStreamFilter()) {
	$reader->addStreamFilter('convert.utf8decode');
	$reader->addStreamFilter('string.toupper');
}

$reader = null;
//only the filters attached using addStreamFilter to `$fp` are removed.
~~~

<p class="message-warning">Only the filters added by the package are removed, filters added to the resource prior to being used in the library are not affected.</p>
