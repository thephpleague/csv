---
layout: default
title: Upgrading from 6.x to 7.x
permalink: upgrading/7.0/
---

# Upgrading from 6.x to 7.x

## Installation

If you are using composer then you should update the require section of your `composer.json` file.

~~~
composer require league/csv:~7.0
~~~

This will edit (or create) your `composer.json` file.

## Added features

To improving writing speed you can now control prior to CSV addition:

- column consistency validation (already present)
- null handling validation (using the new `Writer::NULL_HANDLING_DISABLED` constant)
- any data validation (using the new `Writer::useValidation` method)

Please [refer to the documentation](/inserting/) for more information.

## Improved features

### newline

The newline feature introduced during the 6.X series is completed by

- adding the setter and getter methods to the `Reader` class.
- adding the `$newline` character as a second argument of the `createFromString` named constructor. When set, the method internally use the `setNewline` method to make sure the property is correctly set for future use.

### newReader and newWriter

All the CSV properties are now copied to the new instance when using both methods.

### BOM

When using the `__toString` or `output` methods the input BOM if it exists is stripped from the output.

## Backward Incompatible Changes

### PHP ini settings

**If you are on a Mac OS X Server**, add the following lines before using the library to help [PHP detect line ending in Mac OS X](http://php.net/manual/en/function.fgetcsv.php#refsect1-function.fgetcsv-returnvalues).

~~~php
if (! ini_get("auto_detect_line_endings")) {
    ini_set("auto_detect_line_endings", '1');
}

//the rest of the code continue here...
~~~

### CSV properties

- Starting with version 7, the default SplFileObject flags used are `SplFileObject::READ_CSV` and `SplFileObject::DROP_NEW_LINE`. As previously these flags can not be overidden by the developper to ensure consistency in the methods used.
- All CSV properties setter methods no longer provides default values. When used the method must provide a value.

### Detecting CSV Delimiters

Starting with version 7.0, each found delimiter index represents the character occurences in the specify CSV data.

~~~php
use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/your/csv/file.csv');

$delimiters_list = $reader->detectDelimiterList(10, [' ', '|']);
if (count($delimiters_list)) {
	foreach ($delimiters_list as $occurences => $delimiter) {
		echo "$delimiter appeared $occurences times in 10 CSV row", PHP_EOL;
	}
	//$occurences can not be less than 1
	//since it would mean that the delimiter is not used
}
//if you are only interested in getting
// the most use delimiter you can still do as follow
if (count($delimiters_list)) {
	$delimiter = array_shift($delimiters);
}
~~~

### CSV conversion methods

`jsonSerialize`, `toXML` and `toHTML` methods returns data is affected by the `Reader` query options methods. You can directly narrow the CSV conversion into a json string, a `DOMDocument` object or a HTML table if you filter your data prior to using the conversion method.

As with any other `Reader` extract method, the query options are resetted after a call to the above methods.

Because prior to version 7.0 the conversion methods were not affected, you may have to update your code accordingly.

Old behavior:

~~~php
use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/your/csv/file.csv');
$reader->setOffset(1);
$reader->setLimit(5);
$reader->toHTML(); //would convert the full CSV
~~~

New behavior:

~~~php
use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/your/csv/file.csv');
$reader->setOffset(1);
$reader->setLimit(5);
$reader->toHTML(); //will only convert the 5 specified rows
~~~

Of course, since the query options methods do not exist on the `Writer` class. The changed behavior does not affect the latter class.