---
layout: layout
title: Filtering
---

# Stream Filtering

*available since version 5.5*

Sometimes you may want to perform operations on the CSV as it is being read from or written to. To ease this type of manipulation The `Reader` and `Writer` classes now include methods to ease stream filter usage.

<p class="message-warning"><strong>Warning:</strong> For backward compatibility, PHP Stream Filtering can not be applied when a <code>SplFileObject</code> was use to instantiate the class. A <code>RuntimeException</code> exception will be thrown if you try to use API.</p>

## Stream Filter API

The properties and methods of the API are not: 

* resetted between calls;
* copied to the new class when using `Writer::getReader` and/or `Reader::getWriter` methods;

To be consistent with other features in the library you can manipulate stream filter using an API that includes

### Setting and getting the object stream filter Mode

Because of `SplFileObject` restricted stream filter support you can not set the mode on a stream filter base. The class stream filter mode property is set using PHP internal stream filter constant `STREAM_FILTER_*` and the `setStreamFilterMode($mode)` method.

You can retrieve the class stream filter mode using `getStreamFilterMode()` method. By default:

- when using the Reader class the property is equal to `STREAM_FILTER_READ`;
- when using the Writer class the property is equal to `STREAM_FILTER_WRITE`;

~~~.language-php
use \League\Csv\Reader;

$reader = new Reader('/path/to/my/file.csv');
$reader->setStreamFilterMode(STREAM_FILTER_WRITE);
//this means that any filter you will set will have no effect when reading the CSV
$current_mode = $reader->getStreamFilterMode(); //return STREAM_FILTER_WRITE
~~~

### Managing Stream filter

As with other API we are using a `FIFO` approach when adding or removing filters. 

- `addStreamFilter($filtername)`
- `removeStreamFilter($filtername)`
- `hasStreamFilter($filtername)`

`$filtername` represents the filtername as registered using php `stream_filter_register` function or a PHP included stream filter.

- `clearStreamFilter()`

The `clearStreamFilter` method removes all the currently attached filters.

See below an example using `League\Csv\Reader` to illustrate:

~~~.language-php
use \League\Csv\Reader;

stream_filter_register('convert.utf8decode', 'MyLib\Transcode');
// 'MyLib\Transcode' is a class that extends PHP's php_user_filter class

$reader = new Reader('/path/to/my/chinese.csv');
$reader->addStreamFilter('str.toupper');
$reader->addStreamFilter('str.rot13');
$reader->addStreamFilter('convert.utf8decode');
$reader->removeStreamFilter('str.rot13');
foreach ($reader as $row) {
	//each row cell now contains uppercases string that have been UTF8 decoded
}
~~~

## Limitations

### Writer class on Writing Mode

<p class="message-warning"><strong>Warning:</strong> Because of PHP Stream Filter limitation usage on <code>SplFileObject</code> When inserting new data into a CSV, the stream filtering properties and settings are fixed after the first insert is made with the <code>Writer</code> class. Any further insert will have no effect to preserve the file cursor position.</p>

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

### Writer class on Reading Mode

Since the `Writer` and the `Reader` class share the same iteration features. The code below is totally valid. 

~~~.language-php
use \League\Csv\Writer;

$writer = new Writer('/path/to/my/file.csv');
$writer->setDelimiter(',');
$writer->addStreamFilter('str.toupper');
//first insert -> file.csv will contain uppercased data.
$writer->insertOne(['bill', 'gates', 'bill@microsoft.com']);
$writer->addStreamFilter('str.rot13');
//The inserted array will only be uppercased!!
$writer->insertOne('steve,job,job@apple.com');
$writer->setStreamFilterMode(STREAM_FILTER_READ);

echo $writer; //all $row are Uppercased and has a rot13 transform
~~~

**`str.rot13` had no effect on the writing mode but has an effect on the reading mode**.

To avoid this situation you should use `Writer::clearStreamFilter` before using foreach and set the stream filters accordingly;