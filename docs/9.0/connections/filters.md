---
layout: default
title: Controlling PHP Stream Filters
---

# Stream Filters

~~~php
<?php

public AbstractCsv::hasStreamFilter(string $filtername): bool
public AbstractCsv::supportsStreamFilter(void): bool
public AbstractCsv::addStreamFilter(string $filtername): AbstractCsv
~~~

To ease performing operations on the CSV as it is being read from or written to, you can add PHP stream filters to the `Reader` and `Writer` connections.

## Detecting stream filter support

~~~php
<?php

public AbstractCsv::supportsStreamFilter(void): bool
~~~

Tells whether the stream filter API is supported

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

## Adding a stream filter

~~~php
<?php

public AbstractCsv::addStreamFilter(string $filtername): AbstractCsv
public AbstractCsv::hasStreamFilter(string $filtername): bool
~~~

The `$filtername` parameter is a string that represents the filter as registered using php `stream_filter_register` function or one of PHP internal stream filter.

The `AbstractCsv::addStreamFilter` method adds a stream filter to the connection.

<p class="message-notice">Because of the way PHP stream filters are added, you will add multiple times the same filter if you call this method with the same argument.</p>

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

<p class="message-info">To clear any attached stream filter you need to call the <code>__destruct</code> method.</p>

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
