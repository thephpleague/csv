---
layout: default
title: CSV document interoperability
---

# RFC4180 Field compliance

~~~php
<?php
class RFC4180Field extends php_user_filter
{
    public static function addTo(AbstractCsv $csv): AbstractCsv
    public static function getFiltername(): string
    public static function register(): void
}
~~~

The `RFC4180Field` class enables to work around the following bugs in PHP's native CSV functions:

- [bug #43225](https://bugs.php.net/bug.php?id=43225): `fputcsv` incorrectly handles cells ending in `\` followed by `"`
- [bug #55413](https://bugs.php.net/bug.php?id=55413): `str_getcsv` doesn't remove escape characters
- [bug #74713](https://bugs.php.net/bug.php?id=74713): CSV cell split after `fputcsv()` + `fgetcsv()` round trip.

When using this stream filter you can easily create or read a [RFC4180 compliant CSV document](https://tools.ietf.org/html/rfc4180#section-2) using `League\Csv` connections objects.


<p class="message-warning">Changing the CSV objects control characters <strong>after registering the stream filter</strong> may result in unexpected returned records.</p>


## Usage with CSV objects

~~~php
<?php

public static RFC4180Field::addTo(AbstractCsv $csv): AbstractCsv
~~~

The `RFC4180Field::addTo` method will register the stream filter if it is not already the case and add the stream filter to the CSV object using the following properties:

- the CSV enclosure property;
- the CSV escape property;
- the CSV stream filter mode;

### On records insertion

<p class="message-info">To fully comply with <code>RFC4180</code> you will also need to use <code>League\Csv\Writer::setNewline</code> method.</p>

~~~php
<?php

use League\Csv\RFC4180Field;
use League\Csv\Writer;

$writer = Writer::createFromPath('php://temp');
$writer->setNewline("\r\n"); //RFC4180 Line feed
RFC4180Field::addTo($writer); //adding the stream filter to fix field formatting
$writer->insertAll($iterable_data);
$writer->output('mycsvfile.csv'); //outputting a RFC4180 compliant CSV Document
~~~

### On records extraction


Conversely, to read a RFC4180 compliant CSV document, when using the `League\Csv\Reader` object, just need to add the `League\Csv\RFC4180Field` stream filter as shown below:

~~~php
<?php

use League\Csv\Reader;
use League\Csv\RFC4180Field;

//the current CSV is ISO-8859-15 encoded with a ";" delimiter
$csv = Reader::createFromPath('/path/to/rfc4180-compliant.csv');
RFC4180Field::addTo($csv); //adding the stream filter to fix field formatting

foreach ($csv as $record) {
    //do something meaningful here...
}
~~~

## Usage with PHP stream resources

~~~php
<?php

public static RFC4180Field::register(): void
public static RFC4180Field::getFiltername(): string
~~~

To use this stream filter outside `League\Csv` objects you need to:

- register the stream filter using `RFC4180Field::register` method.
- use `RFC4180Field::getFiltername` with one of PHP's attaching stream filter functions with the correct arguments as shown below:

~~~php
<?php

use League\Csv\RFC4180Field;

RFC4180Field::register();

$resource = fopen('/path/to/my/file', 'r');
$filter = stream_filter_append($resource, RFC4180Field::getFiltername(), STREAM_FILTER_READ, [
    'enclosure' => '"',
    'escape' => '\\',
    'mode' => STREAM_FILTER_READ,
]);

while (false !== ($record = fgetcsv($resource))) {
    //$records correctly parsed
}
~~~