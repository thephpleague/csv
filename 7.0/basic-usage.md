---
layout: default
title: Basic Usage
---

# Basic usage

<p class="message-info"><strong>Tips:</strong> Even though you can use the following methods with the <code>League\Csv\Writer</code> object. It is recommended to do so with the <code>League\Csv\Reader</code> class to avoid losing the file cursor position and getting unexpected results when inserting new data.</p>

Once your CSV object is [instantiated](/7.0/instantiation) and [configured](/7.0/properties/), you can start interacting with the data using a number of methods available to you. For starters, you can iterate over your newly object to extract each CSV row using the `foreach` construct.

~~~php
<?php

$reader = Reader::createFromPath('/path/to/my/file.csv');
foreach ($reader as $index => $row) {
    //do something meaningful here with $row !!
    //$row is an array where each item represent a CSV data cell
    //$index is the CSV row index
}
~~~

<p class="message-notice">You can do more complex iterations <a href="/7.0/reading/">using the query methods</a> available on the <code>League\Csv\Reader</code> class only.</a></p>

## Outputting the CSV

### __toString()

Use the `echo` construct on the instantiated object or use the `__toString` method to show the CSV full content.

~~~php
<?php

echo $reader;
// or
echo $reader->__toString();
~~~

### output($filename = null)

If you only wish to make your CSV downloadable by forcing a file download just use the `output` method to force the use of the output buffer on the CSV content.

<p class="message-notice"> Since <code>version 7.0</code>, the method returns the number of characters read from the handle and passed through to the output.</p>

~~~php
<?php

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="name-for-your-file.csv"');
$reader->output();
~~~

The output method can take an optional argument `$filename`. When present you
can even remove more headers.

~~~php
<?php

$reader->output("name-for-your-file.csv");
~~~

The output methods **can only be affected by:**

- the [library stream filtering mechanism](/7.0/filtering/)
- the [BOM property](/7.0/bom/)

No other method or property have effect on them.
