---
layout: default
title: Examples
---

# Examples

## Parsing a document

A simple example to show you how to parse a CSV document.

```php
use League\Csv\Reader;

$csv = Reader::createFromPath('/path/to/your/csv/file.csv', 'r');

//get the first row, usually the CSV header
$headers = $csv->fetchOne();

//get 25 rows starting from the 11th row
$res = $csv->setOffset(10)->setLimit(25)->fetchAll();
```

## Exporting a database table as a CSV document

A simple example to show you how to create and download a CSV from a `PDOStatement` object

```php
use League\Csv\Writer;

//we fetch the info from a DB using a PDO object
$sth = $dbh->prepare(
    "SELECT firstname, lastname, email FROM users LIMIT 200"
);
//because we don't want to duplicate the data for each row
// PDO::FETCH_NUM could also have been used
$sth->setFetchMode(PDO::FETCH_ASSOC);
$sth->execute();

//we create the CSV into memory
$csv = Writer::createFromFileObject(new SplTempFileObject());

//we insert the CSV header
$csv->insertOne(['firstname', 'lastname', 'email']);

// The PDOStatement Object implements the Traversable Interface
// that's why Writer::insertAll can directly insert
// the data into the CSV
$csv->insertAll($sth);

// Because you are providing the filename you don't have to
// set the HTTP headers Writer::output can
// directly set them for you
// The file is downloadable
$csv->output('users.csv');
die;
```

## Importing a CSV into a database table

A simple example to show you how to import some CSV data into a database using a `PDOStatement` object

```php
use League\Csv\Reader;

//We are going to insert some data into the users table
$sth = $dbh->prepare(
    "INSERT INTO users (firstname, lastname, email) VALUES (:firstname, :lastname, :email)"
);

$csv = Reader::createFromPath('/path/to/your/csv/file.csv', 'r');
$csv->setOffset(1); //because we don't want to insert the header
$nbInsert = $csv->each(function ($row) use (&$sth) {
    //Do not forget to validate your data before inserting it in your database
    $sth->bindValue(':firstname', $row[0], PDO::PARAM_STR);
    $sth->bindValue(':lastname', $row[1], PDO::PARAM_STR);
    $sth->bindValue(':email', $row[2], PDO::PARAM_STR);

    return $sth->execute(); //if the function return false then the iteration will stop
});
```

## More Examples

- [Selecting specific rows in the CSV](https://github.com/thephpleague/csv/tree/7.2.0/examples/extract.php)
- [Querying a CSV](https://github.com/thephpleague/csv/tree/7.2.0/examples/filtering.php)
- [Creating a CSV](https://github.com/thephpleague/csv/tree/7.2.0/examples/writing.php)
- [Merging 2 CSV documents](https://github.com/thephpleague/csv/tree/7.2.0/examples/merge.php)
- [Switching between modes from Writer to Reader mode](https://github.com/thephpleague/csv/tree/7.2.0/examples/switchmode.php)
- [Downloading the CSV](https://github.com/thephpleague/csv/tree/7.2.0/examples/download.php)
- [Converting the CSV into a Json String](https://github.com/thephpleague/csv/tree/7.2.0/examples/json.php)
- [Converting the CSV into a XML file](https://github.com/thephpleague/csv/tree/7.2.0/examples/xml.php)
- [Converting the CSV into a HTML Table](https://github.com/thephpleague/csv/tree/7.2.0/examples/table.php)
- [Using stream Filter on the CSV](https://github.com/thephpleague/csv/tree/7.2.0/examples/stream.php)

> The CSV data use for the examples are taken from [Paris Opendata](http://opendata.paris.fr/opendata/jsp/site/Portal.jsp?document_id=60&portlet_id=121)
