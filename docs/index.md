---
layout: homepage
---

~~~php
<?php
use League\Csv\Reader;
use League\Csv\Statement;

//load the CSV document
$csv = Reader::createFromPath('/path/to/your/csv/file.csv')
    ->setHeaderOffset(0)
    ->addStreamFilter('convert.iconv.ISO-8859-1/UTF-8')
;

//build a statement
$stmt = (new Statement())
    ->offset(10)
    ->limit(25)
;

//query your records from the document
$records = $stmt->process($csv)->fetchAll();
~~~