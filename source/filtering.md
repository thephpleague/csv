---
layout: layout
title: Filtering
---

# Stream Filtering

*available since version 5.4*

Sometimes you may want to perform operations on the CSV as it is being read from or written to. To ease this type of manipulation is introducing a Stream Filtering Plugin mechanism based on a PHP Interface. 

## StreamFilterInterface

Any object that implements the `League\Csv\Stream\StreamFilterInterace` interface and that extends PHP native `php_user_filter` class will be able to filter on the fly the CSV data.

The interface contains the following methods:

* `StreamFilterInterace::registerFilter` : **a static method** that registers the class into PHP Stream Filters;
* `StreamFilterInterace::getRegisteredName`: **a static method** that returns the filter name;
* `StreamFilterInterace::getFilterPath`: returns the generated filter path from a given string path;
* `StreamFilterInterace::setFilterMode`: sets the stream filter mode using PHP stream constants 
	* `STREAM_FILTER_READ`: the filter will be use when reading from the CSV;
	* `STREAM_FILTER_WRITE`: the filter will be use when writing to the CSV;
	* `STREAM_FILTER_ALL`: the filter will be use when reading from **and**  when writing to the CSV;
* `StreamFilterInterace::getFilterMode`: returns the current filter mode;
* `StreamFilterInterace::getFilterModePrefix`: returns the string filter prefix;

and redeclare the public methods from `php_user_filter`

* `StreamFilterInterace::onCreate`: called when creating the filter;
* `StreamFilterInterace::onClose`: called when closing the filter;
* `StreamFilterInterace::filter`: called when applying the filter;

Once your class is ready you can specify it as an optional `$stream_filter` argument at the end of the following methods signatures:

* `Reader::__construct`
* `Writer::__construct`
* `Reader::getWriter`
* `Writer::getReader`

<p class="message-warning"><strong>warning:</strong> The stream filter plugin is only taken into account when the <code>$path</code> is a valid string.</p>

## StreamFilterTrait

To ease the interface implementation the `League\Csv\Stream\StreamFilterTrait` already implements the following method:

* `StreamFilterInterace::registerFilter`;
* `StreamFilterInterace::setFilterMode`;
* `StreamFilterInterace::getFilterMode`;
* `StreamFilterInterace::getFilterModePrefix`;
* `StreamFilterInterace::__toString` : a string representation of the full filter path;

You just need to reference this trait in your object to ease your object implementation.

## Stream Filters Plugin bundled with the library

These plugins were added to ease CSV manipulation but you are not required to use them. Nevertheless, they are a good starting point to help you understand and use the feature.

### Transcode

This class helps transcode on the fly any given CSV document from one charset to another.

See below an example using `League\Csv\StreamPlugins\TranscodeFilter` to illustrate:

~~~.language-php

use \League\Csv\StreamPlugins\Transcode;

$transcode = new Transcode;
$transcode->setEncodingFrom('iso-8859-15');
$transcode->setEncodingTo('UTF-8');
$transcode->setFilterMode(STREAM_FITER_READ);
$reader = new Reader('/path/to/my/file.csv', 'r', $transcode);
foreach ($reader as $row) {
	//the content of row is automatically converted
	//from iso-8859-15 to UTF-8 on the fly 
}
~~~

### Collection

This filter is a special one as it does not filter the CSV directly even if you register it but enables registering and applying multiple Stream Filter Plugins on a single CSV. the registered filter follow the *the First In First Out* rule.

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