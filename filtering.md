---
layout: default
title: Stream Filtering
---

# Stream Filtering

To ease performing operations on the CSV as it is being read from or written to, the `Reader` and `Writer` classes now include methods to ease PHP stream filtering usage.

## Stream Filter API

While in PHP the stream filter mode is attached to its associated filter, in `League\Csv` the filter mode is attached to the CSV object. This means that when you change the filter mode, you also clear all previously attached stream filters.

To be able to use the stream filtering mechanism you need to:

* validate that the stream filter API is active;
* set the class filtering mode;
* attached your stream filters to the CSV object;

### Detecting if the API is active

To be sure that the Stream Filter API is available it is recommend to use the method `isActiveStreamFilter`.

~~~php
<?php

public AbstractCsv::isActiveStreamFilter(void): bool
~~~

`isActiveStreamFilter` returns `true` if you can safely use the API:

~~~php
<?php

use League\Csv\Reader;
use League\Csv\Writer;

$reader = Reader::createFromPath('/path/to/my/file.csv');
$reader->isActiveStreamFilter(); //return true

$writer = Writer::createFromFileObject(new SplTempFileObject());
$writer->isActiveStreamFilter(); //return false the API can not be use
~~~

<p class="message-warning"><strong>Warning:</strong> A <code>LogicException</code> exception may be thrown if you try to use the API under certain circumstances without prior validation using <code>isActiveStreamFilter</code></p>

### Setting and getting the object stream filter mode

The stream filter mode property is set using PHP internal stream filter constant `STREAM_FILTER_*`.

~~~php
<?php

public AbstractCsv::setStreamFilterMode(int $mode): AbstractCsv
public AbstractCsv::getStreamFilterMode(void): int
~~~

Unlike `fopen`, the mode is attached to the object and not to a specific stream filter.

* `setStreamFilterMode`: set the object stream filter mode **and** remove all previously attached stream filters;
* `getStreamFilterMode`: returns the current stream filter mode;

By default:

- when using the `Reader` class the property is equal to `STREAM_FILTER_READ`;
- when using the `Writer` class the property is equal to `STREAM_FILTER_WRITE`;
- If you instantiate the class using a PHP filter meta wrapper (ie: `php://filter/`), the mode will be the one used by the meta wrapper;

~~~php
<?php

use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/my/file.csv');
if ($reader->isActiveStreamFilter()) {
	$current_mode = $reader->getStreamFilterMode(); //returns STREAM_FILTER_READ
	$reader->setStreamFilterMode(STREAM_FILTER_WRITE);
	//this means that any filter you will set will have no effect when reading the CSV
	//all previously attached stream filters if they existed have been removed
	$current_mode = $reader->getStreamFilterMode(); //returns STREAM_FILTER_WRITE
}
~~~

### Managing Stream filter

To manage your registered stream filter collection you can use the following methods:

~~~php
<?php

public AbstractCsv::appendStreamFilter(string $filtername): AbstractCsv
public AbstractCsv::prependStreamFilter(string $filtername): AbstractCsv
public AbstractCsv::removeStreamFilter(string $filtername): AbstractCsv
public AbstractCsv::hasStreamFilter(string $filtername): bool
public AbstractCsv::clearStreamFilter(void): AbstractCsv
~~~

- `appendStreamFilter`: adds a stream filter at the bottom of the collection
- `prependStreamFilter`: adds a stream filter at the top of the collection
- `removeStreamFilter`: removes a stream filter from the collection
- `hasStreamFilter`: check the presence of a stream filter in the collection
- `clearStreamFilter`: removes all the currently attached filters.

The `$filtername` parameter is a string that represents the filter as registered using php `stream_filter_register` function or one of PHP internal stream filter.

Since the stream filters are attached to the CSV object:

* The filters will not be cleared between method calls unless specified
* The filters will not be copied to the new class when using `newReader` or `newWriter` methods

The filters are automatically applied when the stream filter mode matches the method you are using.

See below an example using `League\Csv\Reader` to illustrate:

~~~php
<?php

use League\Csv\Reader;

stream_filter_register('convert.utf8decode', 'MyLib\Transcode');
// 'MyLib\Transcode' is a class that extends PHP's php_user_filter class

$reader = Reader::createFromPath('/path/to/my/chinese.csv');
if ($reader->isActiveStreamFilter()) {
	$reader->appendStreamFilter('string.toupper');
	$reader->appendStreamFilter('string.rot13');
	$reader->prependStreamFilter('convert.utf8decode');
	$reader->removeStreamFilter('string.rot13');
}
foreach ($reader as $row) {
	// each row cell now contains strings that have been:
	// first UTF8 decoded and then uppercased
}
~~~

<p class="message-notice">Starting with version <code>8.1.0</code> you no longer need to URL encode your filter prior to attach it to the CSV object.</p>

~~~php
<?php

use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/my/chinese.csv');
$reader->appendStreamFilter('convert.iconv.UTF-8/ASCII//TRANSLIT');
var_dump($reader->fetchAll());
~~~

## Limitations

### Writer class on Editing Mode

<p class="message-warning"><strong>Warning:</strong> To preserve file cursor position during editing the stream filter mode and the stream filter collection are frozen after the first insert is made using any of the <code>insert*</code> method. Any attempt to modify the stream filter status will fail silently.</p>

~~~php
<?php

use League\Csv\Writer;

$writer = Writer::createFromPath('/path/to/my/file.csv');
$writer->setDelimiter(',');
if ($writer->isActiveStreamFilter()) {
	$writer->addStreamFilter('string.toupper');
}
//first insert -> file.csv will contain uppercased data.
$writer->insertOne(['bill', 'gates', 'bill@microsoft.com']);
if ($writer->isActiveStreamFilter()) {
	//isActiveStreamFilter returns false so this code is never executed
	$writer->addStreamFilter('string.rot13');
}
//this filter is added to the collection but will never be applied!!
$writer->addStreamFilter('string.rot13');
//The inserted array will only be uppercased!!
$writer->insertOne('steve,job,job@apple.com');

echo $writer; //the newly added rows are all uppercased
~~~

## Example

Please review <a href="https://github.com/thephpleague/csv/blob/master/examples/stream.php" target="_blank">the stream filtering example</a> and the attached <a href="https://github.com/thephpleague/csv/blob/master/examples/lib/FilterTranscode.php" target="_blank">FilterTranscode</a> Class to understand how to use the filtering mechanism to convert a CSV into another charset. 

The `FilterTranscode` class is not attached to the Library because converting your CSV may depend on the extension you choose, in PHP you can use the following extensions : 

<ul>
<li><a href="http://php.net/mbstring" target="_blank">The mbstring</a></li>
<li><a href="http://php.net/iconv" target="_blank">The iconv</a></li>
<li><a href="http://php.net/intl" target="_blank">The intl</a></li>
</ul>
