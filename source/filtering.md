---
layout: layout
title: Filtering
---

# Stream Filtering

*available since version 5.4*

Sometimes you may want to perform operations on the CSV as it is being read from or written to. To ease this type of manipulation a Stream Filtering Plugin mechanism has been developped. This mechanism is based on a PHP Interface.

## StreamFilterInterface

Any object that implements the `League\Csv\Stream\StreamFilterInterace` interface and that extends PHP native `php_user_filter` class will be able to filter on the fly a PHP I/O stream.

The interface contains the following methods:

* `StreamFilterInterace::getFilterName`: **a static method** that returns the filter registration name;
* `StreamFilterInterace::registerFilter` : **a static method** that registers the class into PHP Stream Filters;
* `StreamFilterInterace::__toString`: returns the generated filter path;
* `StreamFilterInterace::setFilterMode`: sets the stream filter mode using one of PHP stream filter constants: 
	* `STREAM_FILTER_READ`: the filter will be use when reading from the CSV;
	* `STREAM_FILTER_WRITE`: the filter will be use when writing to the CSV;
	* `STREAM_FILTER_ALL`: the filter will be use when reading from **and**  when writing to the CSV;
* `StreamFilterInterace::getFilterMode`: returns the current filter mode;
* `StreamFilterInterace::getFilterUri`: returns the PHP full filter meta wrapper string;

and redeclare the public methods from `php_user_filter`

* `StreamFilterInterace::onCreate`: called when creating the filter;
* `StreamFilterInterace::onClose`: called when closing the filter;
* `StreamFilterInterace::filter`: called when applying the filter;

Once your class is ready you can specify it as an optional argument at the end of the following methods signatures:

* `Reader::__construct`;
* `Writer::__construct`;
* `Reader::getWriter`;
* `Writer::getReader`;

During instantiation, the stream filter object is:

* taken into account when `$path` is a string or a `SplFileInfo` object;
* ignore when `$path` is a `SplFileObject`;

When switching from one class to the other the stream filter object is taken into account whenever the path is valid.


## StreamFilterTrait

To ease the interface implementation the `League\Csv\Stream\StreamFilterTrait` already implements the following method:

* `StreamFilterInterace::getFilterName`;
* `StreamFilterInterace::registerFilter`;
* `StreamFilterInterace::setFilterMode`;
* `StreamFilterInterace::getFilterMode`;
* `StreamFilterInterace::getFilterUri`;

You just need to reference this trait in your object to ease your object implementation.

<p class="message-warning"><strong>warning:</strong> If <code>setFilterMode</code> is not used the filter mode is set to <code>STREAM_FILTER_ALL</code> when using this trait.</p>

## Stream Filters Plugins bundled with the library

These plugins/extensions are added to ease CSV manipulations but:

* you are not required to use them;
* they are not restricted to `League\Csv`;
* they are a good starting point to help you understand and use the feature;

A simple implementation of the `League\Csv\StreamFilterInteface` using the `League\Csv\Stream\StreamFilterTrait` needs to provide:

* a static `$name` for you plugin. By convention you should prepend you plugin name with `stream.plugin.XXXX` where `XXXX` represents you plugin.
* implements at least the following methods: 
	* `__toString`;
	* `onCreate`;
	* `filter`;

The following plugins are bundled with the library

### Transcode

This class helps transcode on the fly any given file from one charset to another.
The following methods were added:

* `setEncodingFrom($encoding)`: set the file encoding charset;
* `getEncodingFrom`: get the file encoding charset;
* `setEncodingTo($encoding)`: set the type of encoding to convert to;
* `getEncodingTo`: get the type of encoding to convert to;

This plugin requires the multibytes string extension.

See below an example using `League\Csv\StreamPlugins\Transcode` to illustrate:

~~~.language-php
use \League\Csv\Reader;
use \League\Csv\StreamPlugins\Transcode;

$transcode = new Transcode; //registration is done by the constructor
$transcode->setEncodingFrom('Big5');
$transcode->setEncodingTo('UTF-8');
$transcode->setFilterMode(STREAM_FILTER_READ);
$reader = new Reader('/path/to/my/chinese.csv', 'r', $transcode);
foreach ($reader as $row) {
	//each row cell has been converted
}

//the class can be use with any PHP function that supports I/O streams
$path = '/path/to/my/chinese-encoded.txt';
readfile($transcode->getFilterUri($path));
~~~

### Collection

This plugin is special as it does not filter the stream directly even if you register it but enables registering and applying multiple stream Filter plugins on a single stream. the registered filters follow the *the First In First Out* rule.

This class implements the following interfaces

* `StreamFilterInterface`;
* `IteratorAggregate`;
* `Countable`;

It also has the following methods:

* `add(StreamFilterInterace $stream)`: adds a stream to the collection;
* `remove(StreamFilterInterace $stream)`: removes a stream from the collection. if the stream is registerd multiple times you will have to call the method as often as the filter was registerd. **The first registered copy will be the first to be removed**;
* `has(StreamFilterInterace $stream)`: check to see if a stream filter plugin is already in the collection
* `clear()`: remove all previously attached stream filter from the objet.

Because of the way PHP registers internally its stream filters the only filter mode that is taken into account is the one of the collection **not** the one of each individual Stream Filter Plugin.

In the example below, the data is first encrypted and then the encrypted data is uppercased.

~~~.language-php
use \League\Csv\StreamPlugins\Collection as StreamFilterCollection;
use \MyLib\UpperCaseFilter;
use \MyLib\EncryptFilter;

$bag = new StreamFilterCollection;
$bag->setFilterMode(STREAM_FILTER_WRITE);

$encrypt_filter = new EncryptFilter;
$encrypt_filter->setFilterMode(STREAM_FILTER_WRITE); //ignore by $bag

$upper_filter = new UpperCaseFilter;
$upper_filter->setFilterMode(STREAM_FILTER_READ); //ignore by $bag

$bag->add($encrypt_filter);
$bag->add($upper_filter);
//the data to be added to the CSV
$data = [
	[..., ...],
	...
];

$writer = new Writer('/path/to/my/file.csv', 'w', $bag);
$writer->insertAll($data);
$reader = $writer->getReader('r', $bag);
foreach ($reader as $row) {
	//nothing will happen as $bag filter mode is STREAM_FILTER_WRITE
	//so the data is seen as Encrypted + Uppercased 
}
~~~

Another commented example can be found in the [example folder](https://github.com/thephpleague/csv/blob/master/examples/stream.php "Stream Filter Plugins examples").