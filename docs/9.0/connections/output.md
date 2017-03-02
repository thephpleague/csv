---
layout: default
title: CSV document output
---

# CSV document output

~~~php
<?php

public AbstractCsv::__toString(void): string
public AbstractCsv::output(string $filename = null): int
~~~

Once your CSV document is loaded, you can print or enable downloading the CSV document using the methods below.

<p class="message-info"><strong>Tips:</strong> Even though you can use the following methods with the <code>League\Csv\Writer</code> object. It is recommended to do so with the <code>League\Csv\Reader</code> class to avoid losing the file cursor position and getting unexpected results when inserting new data.</p>

## Printing the document

Returns the string representation of the CSV document

~~~php
<?php

public AbstractCsv::__toString(void): string
~~~

Use the `echo` construct on the instantiated object or use the `__toString` method to show the CSV full content.

### Example

~~~php
<?php

use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/my/file.csv');
echo $reader;
// or
echo $reader->__toString();
~~~

## Downloading the document

If you only wish to make your CSV document downloadable use the `output` method to force the use of the output buffer on the CSV content.

~~~php
<?php

public AbstractCsv::output(string $filename = null): int
~~~

The method returns the number of characters read from the handle and passed through to the output.

The output method can take an optional argument `$filename`. When present you
can even remove more headers.

### default usage

~~~php
<?php

use League\Csv\Reader;

header('content-type: text/csv; charset=UTF-8');
header('content-disposition: attachment; filename="name-for-your-file.csv"');

$reader = Reader::createFromPath('/path/to/my/file.csv');
$reader->output();
~~~

### Using the $filename argument

~~~php
<?php

use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/my/file.csv');
$reader->output("name-for-your-file.csv");
~~~

<p class="message-info"><strong>Tips:</strong> The methods output <strong>are affected by</strong> <a href="/9.0/connections/bom/">the output BOM sequence</a> or the supplied <a href="/9.0/connections/filters/">PHP stream filters</a>.</p>
