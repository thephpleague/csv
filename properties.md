---
layout: default
title: Setting and Accessing CSV settings
---

# CSV properties

Once your object is [instantiated](/instantiation/) you can optionally set several CSV properties. The following methods works on both the `Reader` and the `Writer` class.

## Accessing and Setting CSV properties

### The delimiter character

~~~php
$csv->setDelimiter(';');
$delimiter = $csv->getDelimiter(); //returns ";"
~~~
If no delimiter is set the default delimiter is `,`.

### The enclosure character

~~~php
$csv->setEnclosure('|');
$enclosure = $csv->getEnclosure(); //returns "|"
~~~
If no enclosure is set the default enclosure is `"`.

### The escape character

~~~php
$csv->setEscape('\\');
$escape = $csv->getEscape(); //returns "\"
~~~
If no escape is set the default escape is `\`.

### The SplFileObject flags

The `League\Csv` relies internally on the `SplFileObject` class. In order to fine tune the class behavior you can adjust the [flags](http://php.net/manual/en/class.splfileobject.php#splfileobject.constants) used.

~~~php
$csv->setFlags(SplFileObject::READ_AHEAD|SplFileObject::SKIP_EMPTY);
$flags = $csv->getFlags(); //returns an integer
~~~
By default the flags used are `SplFileObject::READ_CSV` and `SplFileObject::DROP_NEW_LINE`.

<p class="message-warning">To get an expected behaviour, you can not remove the default flags you can only add non defined ones.</p>

### The newline sequence

Because the php `fputcsv` implementation has a hardcoded `"\n"`, we need to be able to replace the last `LF` code with one supplied by the developper for more interoperability between CSV packages on different platforms. The newline sequence will be appended to each CSV newly inserted line.

~~~php
$csv->setNewline("\r\n");
$newline = $csv->getNewline(); //returns "\r\n"
~~~
If no newline is set the default newline is `\n`;

<p class="message-notice">Since version 7.0, the <code>$newline</code> getter and setter methods are available on the <code>Reader</code> class.</p>

### The BOM character

To improve interoperability with programs interacting with CSV, you can now manage the presence of a <abbr title="Byte Order Mark">BOM</abbr> character in your CSV content.

You can detect the current BOM character used if any with the `getInputBOM` method. This method returns `null` or the currently used BOM character.

~~~php
$bom = $csv->getInputBOM();
~~~

You can of course set the outputting BOM you want your CSV to be associated with.

~~~php
$csv->setOutputBOM(Reader::BOM_UTF8);
$bom = $csv->getOutputBOM(); //returns "\xEF\xBB\xBF"
~~~
The default output `BOM` character is set to `null`.

<p class="message-info">Please refer to <a href="/bom/">the BOM character dedicated documentation page</a> for more informations on how the library manage the BOM character.</p>

### The encoding charset

The library assumes that your data is UTF-8 encoded. Before converting your data in another format ( JSON, XML, HTML), you need to make sure it is the case.

The recommended way to transcode your CSV in a UTF-8 compatible charset is to use the <a href="/filtering/">library stream filtering mechanism</a>.

When this is not applicable you can fallback to setting the CSV original encoding charset as below.

~~~php
$reader->setEncodingFrom('iso-8859-15');
echo $reader->getEncodingFrom(); //returns iso-8859-15;
~~~

By default the encoding charset is set to `UTF-8`.

## Detecting CSV delimiter

### detectDelimiterList($nbRows = 1, array $delimiters = [])

If you are no sure about the delimiter you can ask the library to detect it for you using the `detectDelimiterList` method. **This method will only give you a hint**.

The method takes two arguments:

* the number of rows to scan (default to `1`);
* the possible delimiters to check (you don't need to specify the following delimiters as they are already checked by the method: `",", ";", "\t"`);

~~~php
$reader = Reader::createFromPath('/path/to/your/csv/file.csv');

$reader->setEnclosure('"');
$reader->setEscape('\\');
$reader->setFlags(SplFileObject::READ_AHEAD|SplFileObject::SKIP_EMPTY);

$delimiters_list = $reader->detectDelimiterList(10, [' ', '|']);
// $delimiters_list can be the following
// [
//     20 => '|',
//     3 => ';'
// ]
// This is a inconsistent CSV with:
// - the delimiter "|" appearing 20 times in the 10 first rows
// - the delimiter ";" appearing 3 times in the 10 first rows
~~~

The more rows and delimiters you add, the more time and memory consuming the operation will be. The method returns an `array` of the delimiters found.

* If a single delimiter is found the array will contain only one delimiter;
* If multiple delimiters are found the array will contain the found delimiters sorted descendingly according to their occurences in the defined rows set;
* If no delimiter is found or your CSV is composed of a single column, the array will be empty;

<p class="message-warning"><strong>BC Break:</strong> Starting with version <code>7.0</code>, the index of each found delimiter represents the occurence of the found delimiter in the selected rows.</p>

Whenever a user creates a new CSV object using the `newWriter` or the `newReader` methods, the current CSV object properties are copied to the new instance.