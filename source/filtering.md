---
layout: layout
title: Filtering
---

# Stream Filtering

*available since version 5.5*

Sometimes you may want to perform operations on the CSV as it is being read from or written to. To ease this type of manipulation The `Reader` and `Writer` classes now include methods to ease stream filter usage.

<p class="message-warning"><strong>Warning:</strong> For backward compatibility, PHP Stream Filtering can not be applied when a <code>SplFileObject</code> was use to instantiate the class. A <code>RuntimeException</code> exception will be thrown if you try to use API.</p>

## Stream Filter API

The properties of the API are not: 

* cleared between calls;
* copied to the new class when using `Writer::createReader` and/or `Reader::createWriter` methods;

### Setting and getting the object stream filter Mode

Because of `SplFileObject` restricted PHP stream filter support, the stream filter mode is object based and not filter specific.

The class stream filter mode property is set using PHP internal stream filter constant `STREAM_FILTER_*` and the `setStreamFilterMode($mode)` method.

Whenever you change the class stream filter mode the stream filters are cleared.

You can retrieve the class stream filter mode using `getStreamFilterMode()` method. By default:

- when using the Reader class the property is equal to `STREAM_FILTER_READ`;
- when using the Writer class the property is equal to `STREAM_FILTER_WRITE`;

<p class="message-warning"><strong>Warning:</strong> If you instantiate the class using a PHP filter meta wrapper, the mode will be the one used by the meta wrapper;</p>

~~~.language-php
use \League\Csv\Reader;

$reader = new Reader('/path/to/my/file.csv');
$reader->setStreamFilterMode(STREAM_FILTER_WRITE);
//this means that any filter you will set will have no effect when reading the CSV
$current_mode = $reader->getStreamFilterMode(); //return STREAM_FILTER_WRITE
~~~

### Managing Stream filter

To manage the stream you can use the following methods

- `appendStreamFilter($filtername)` : adds a stream filter at the bottom of the stream filter collection
- `appendStreamFilter($filtername)` : adds a stream filter at the top of the stream filter collection
- `removeStreamFilter($filtername)` : removes a stream filter from the collection
- `hasStreamFilter($filtername)` : check the presence of a stream filter in the collection

`$filtername` represents the filter as registered using php `stream_filter_register` function.

- `clearStreamFilter()`

The `clearStreamFilter` method removes all the currently attached filters.

See below an example using `League\Csv\Reader` to illustrate:

~~~.language-php
use \League\Csv\Reader;

stream_filter_register('convert.utf8decode', 'MyLib\Transcode');
// 'MyLib\Transcode' is a class that extends PHP's php_user_filter class

$reader = new Reader('/path/to/my/chinese.csv');
$reader->appendStreamFilter('str.toupper');
$reader->appendStreamFilter('str.rot13');
$reader->prependStreamFilter('convert.utf8decode');
$reader->removeStreamFilter('str.rot13');
foreach ($reader as $row) {
	//each row cell now contains uppercases string that have been UTF8 decoded
}
~~~

## Limitations

### Writer class on Writing Mode

<p class="message-warning"><strong>Warning:</strong> Because of <code>SplFileObject</code> restricted stream filter support, to preserve file cursor position during writing, the stream filtering properties and settings are fixed after the first insert is made.</p>

~~~.language-php
use \League\Csv\Writer;

$writer = new Writer('/path/to/my/file.csv');
$writer->setDelimiter(',');
$writer->addStreamFilter('str.toupper');
//first insert -> file.csv will contain uppercased data.
$writer->insertOne(['bill', 'gates', 'bill@microsoft.com']);
//Both methods below will have no effect and won't return any error!!
$writer->addStreamFilter('str.rot13');
//The inserted array will only be uppercased!!
$writer->insertOne('steve,job,job@apple.com');

echo $writer; //the newly added rows are all uppercased
~~~