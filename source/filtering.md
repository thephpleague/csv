---
layout: layout
title: Filtering
---

# Stream Filtering

*available since version 5.5*

To ease performing operations on the CSV as it is being read from or written to. the `Reader` and `Writer` classes now include methods to ease PHP stream filtering usage.

<p class="message-warning"><strong>Warning:</strong> because of <code>SplFileObject</code> restricted stream filter support, a <code>LogicException</code> exception will be thrown if you try to use the API under other circumstances.</p>

To avoid the exception you can make sure the Stream Filter API is available by using the `supportsStreamFilter` method, which returns `true` if you can safely use the API:

~~~.language-php
use \League\Csv\Reader;
use \League\Csv\Writer;

$reader = Reader::createFromPath('/path/to/my/file.csv');
$reader->supportsStreamFilter(); //return true

$writer = new Writer(new SplTempFileObject);
$writer->supportsStreamFilter(); //return false the API can not be use
~~~

## Stream Filter API

To be able to use the stream filtering mechanism you need to:

* set the class filtering mode;
* attached stream filters to your object as a collection;

As a consequence:

* The filters will be automatically applied when the stream filter mode matches the method you are using.
* The filters will not be cleared between method calls unless specified;
* The filters will not be copied to the new class when using `newReader` or `newWriter` methods;

### Setting and getting the object stream filter mode

The stream filter mode property is set using PHP internal stream filter constant `STREAM_FILTER_*`, but unlike `fopen`, the mode is attached to the object and not to a stream filter.

* `setStreamFilterMode($mode)`: set the object stream filter mode **and** remove all previously attached stream filters;
* `getStreamFilterMode()`: returns the current stream filter mode;

By default:

- when using the `Reader` class the property is equal to `STREAM_FILTER_READ`;
- when using the `Writer` class the property is equal to `STREAM_FILTER_WRITE`;
- If you instantiate the class using a PHP filter meta wrapper (ie: `php://filter/`), the mode will be the one used by the meta wrapper;

~~~.language-php
use \League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/my/file.csv');
$current_mode = $reader->getStreamFilterMode(); //returns STREAM_FILTER_READ
$reader->setStreamFilterMode(STREAM_FILTER_WRITE);
//this means that any filter you will set will have no effect when reading the CSV
//all previously attached stream filters if they existed have been removed
$current_mode = $reader->getStreamFilterMode(); //returns STREAM_FILTER_WRITE
~~~

### Managing Stream filter

To manage your registered stream filter collection you can use the following methods

- `appendStreamFilter($filtername)` : adds a stream filter at the bottom of the collection
- `prependStreamFilter($filtername)` : adds a stream filter at the top of the collection
- `removeStreamFilter($filtername)` : removes a stream filter from the collection
- `hasStreamFilter($filtername)` : check the presence of a stream filter in the collection
- `clearStreamFilter()`: removes all the currently attached filters.

The `$filtername` parameter is a string that represents the filter as registered using php `stream_filter_register` function or one of PHP internal stream filter.

See below an example using `League\Csv\Reader` to illustrate:

~~~.language-php
use \League\Csv\Reader;

stream_filter_register('convert.utf8decode', 'MyLib\Transcode');
// 'MyLib\Transcode' is a class that extends PHP's php_user_filter class

$reader = Reader::createFromPath('/path/to/my/chinese.csv');
$reader->appendStreamFilter('str.toupper');
$reader->appendStreamFilter('str.rot13');
$reader->prependStreamFilter('convert.utf8decode');
$reader->removeStreamFilter('str.rot13');
foreach ($reader as $row) {
	//each row cell now contains uppercases string that have been UTF8 decoded
}
~~~

## Limitations

### Writer class on Editing Mode

<p class="message-warning"><strong>Warning:</strong> To preserve file cursor position during editing and because of <code>SplFileObject</code> restricted stream filter support, the stream filter mode and the stream filter collection are frozen after the first insert is made using any of the <code>insert*</code> method.</p>

~~~.language-php
use League\Csv\Writer;

$writer = Writer::createFromPath('/path/to/my/file.csv');
$writer->setDelimiter(',');
$writer->addStreamFilter('str.toupper');
//first insert -> file.csv will contain uppercased data.
$writer->insertOne(['bill', 'gates', 'bill@microsoft.com']);
//this  will have no effect and won't return any error!!
$writer->addStreamFilter('str.rot13');
//The inserted array will only be uppercased!!
$writer->insertOne('steve,job,job@apple.com');

echo $writer; //the newly added rows are all uppercased
~~~

## Example

Please review [the stream filtering example](https://github.com/thephpleague/csv/blob/master/examples/stream.php) and the attached [FilterTranscode](https://github.com/thephpleague/csv/blob/master/examples/lib/FilterTranscode.php) Class to understand how to use the filtering mechanism to convert a CSV into another charset. 

The `FilterTranscode` class is not attached to the Library because converting you CSV may depend on the extension you choose, in PHP you can use the following extensions : 
    * The [mbstring](http://php.net/mbstring);
    * The [iconv](http://php.net/iconv);
    * The [intl](http://php.net/intl);