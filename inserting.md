---
layout: default
title: Inserting new data into a CSV
permalink: inserting/
---

# Inserting Data

To create or update a CSV use the following `League\Csv\Writer` methods.

<p class="message-warning">When inserting strings don't forget to specify the CSV delimiter and enclosure characters that match those use in the string.</p>

<p class="message-info">When creating a file using the library, first insert all the data that need to be inserted before starting manipulating the CSV. If you manipulate your data before insertion, you may change the file cursor position and get unexpected results.</p>

## Adding data to a CSV

### insertOne($data)

`insertOne` inserts a single row. This method can take an `array`, a `string` or
an `object` implementing the `__toString` method.

~~~php
class ToStringEnabledClass
{
    private $str;

    public function __construct($str)
    {
        $this->str = $str;
    }

    public function __toString()
    {
        return $this->str;
    }
}

$writer->insertOne(['john', 'doe', 'john.doe@example.com']);
$writer->insertOne("'john','doe','john.doe@example.com'"); 
$writer->insertOne(new ToStringEnabledClass("john,doe,john.doe@example.com")) 
~~~

### insertAll($data)

`insertAll` inserts multiple rows. This method can take an `array` or a 
`Traversable` object to add several rows to the CSV data.

~~~php
$arr = [
    [1, 2, 3],
    ['foo', 'bar', 'baz'],
    "'john','doe','john.doe@example.com'",
    new ToStringEnabledClass("john,doe,john.doe@example.com")
];

$writer->insertAll($arr); //using an array 

$object = new ArrayIterator($arr);
$writer->insertAll($object); //using a Traversable object
~~~

### useFormatValidation(bool $status)

<p class="message-notice">added in version 6.4</p>

This method is to be used when all necessary checks have been made to your data before using `insertOne` or `insertAll`. The method enables or disables the library internal cell format validation. It accepts a single boolean parameter. When this parameter equals `false` the internal processs is disabled. You can of course reactivate the process at any given moment by setting the parameter to `true`. By default and for backward compatibility, cell format is always checked.

`Writer::useFormatValidation` can be useful, for instance, when dealing with large dataset to transfer to a CSV file. By disabling the cell format validation you can drastically reduce the CSV creation duration.

~~~php
<?php
use League\Csv\Writer;

//$dbh is a PDO object
//we fetch the info from a DB using a PDO object
//let's assume that the table has more than 100 000 rows
$sth = $dbh->prepare("SELECT firstname, lastname, email FROM users");
$sth->setFetchMode(PDO::FETCH_ASSOC);
$sth->execute();
$csv = Writer::createFromFileObject(new SplTempFileObject);
//data format validation is made on the following line
$csv->insertOne(['firstname', 'lastname', 'email']);
$csv->useFormatValidation(false);
//no data format validation will be made on the data returned by PDO
$csv->insertAll($sth);
$csv->output('users.csv');
die;
~~~

<p class="message-warning">Even though no check is done on each cell format, all other checks described bellow are applied</p>

## Handling null values (since version 5.3)

When importing data containing `null` values you should tell the library how to handle them. 

### setNullHandlingMode($mode)

To set the `Writer` class handling behavior, you will use the `setNullHandlingMode` method. This method takes one of these constants mode:

* `Writer::NULL_AS_EXCEPTION`: Inserting methods throw an `InvalidArgumentException` when a `null` value is found;
* `Writer::NULL_AS_EMPTY`:Inserting methods convert `null` values into empty string;
* `Writer::NULL_AS_SKIP_CELL`: Inserting methods filter out each `null` item found;

<p class="message-warning">By default the Writer mode to handle <code>null</code> value is <code>Writer::NULL_AS_EXCEPTION</code> to keep the code backward compatible.</p>

~~~php
$writer->setNullHandlingMode(Writer::NULL_AS_SKIP_CELL);
$writer->insertOne(["one", "two", null, "four"]); 
~~~

In the above example, the `null` value will be filter out and the corresponding CSV row will contain only 3 items.

### getNullHandlingMode()

At any given time you are able to know the class mode using the `getNullHandlingMode` method.

~~~php
if (Writer::NULL_AS_EXCEPTION == $writer->getNullHandlingMode()) {
    $writer->setNullHandlingMode(Writer::NULL_AS_EMPTY);
}
$writer->insertOne(["one", "two", null, "four"]); 
~~~
In the above example, the `null` value will be converted into an empty string, only if the current mode handles the value by throwing exception.

## Handling CSV columns count consistency (since version 5.4)

You can optionally asks the `Writer` class to check the columns count consistency of the newly added rows in you CSV.

### getColumnsCount()

At any given time you can access the `$columns_count` property using the `getColumnsCount` method. 

<p class="message-warning">By default and for backward compatibility, the <code>$columns_count</code>property equals <code>-1</code> which means that column count consistency is not checked.</p>

### setColumnsCount($value)

One way to do enable columns consistency is to use the `setColumnsCount` method to set the required number of columns to an integer greater than `-1`.

~~~php
$writer->setColumnsCount(2);
$nb_column_count = $writer->getColumnsCount(); // equals to 2;
$writer->insertAll([
    ["one", "two"],
    ["one", "two", "four"],  //this will throw an InvalidArgumentException
]); 
~~~

### autodetectColumnsCount()

Another way is to use the `autodetectColumnsCount` method which will set the required number of columns according to the next inserted row.

~~~php
use League\Csv\Writer;

$writer = Writer::createFromPath('path/to/csv', 'w');
$writer->autodetectColumnsCount();
$nb_column_count = $writer->getColumnsCount(); // equals to -1 = default value;
$writer->insertOne(["one", "two", "four"]); 
$nb_column_count = $writer->getColumnsCount(); // equals to 3;
$writer->insertOne(["one", "two"]); //this will throw an InvalidArgumentException
~~~

Keep in mind that:

* the effect of the `autodetectColumnsCount` method will only take place after the next call to `insertOne`.
* `setColumnsCount` and `autodetectColumnsCount` override each other effect when called before `insertOne`;

## Handling CSV newline character

<p class="message-notice">added in version 6.2</p>

Because the php `fputcsv` implementation has a hardcoded `"\n"`, we need to be able to replace the last LF code with one supplied by the developper for more interoperability between CSV packages on different platform.

### getNewline()

At any given time you can access the `$newline` property using the `getNewline` method. 

<p class="message-warning">By default and for backward compatibility, the <code>$newline</code>property equals <code>"\n"</code> which is the default behavior of php <code>fputcsv</code> function.</p>

### setNewline($newline)

At any given time you can modify the `$newline` property using the `setNewline` method.

~~~php
$writer = Writer::createFromFileObject(new SplFileObject());
$newline = $writer->getNewline(); // equals "\n";
$writer->setNewline("\r\n");
$newline = $writer->getNewline(); // equals "\r\n";
$writer->insertOne(["one", "two"]); 
echo $writer; // displays "one,two\r\n";
~~~