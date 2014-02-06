<?php

error_reporting(-1);
ini_set('display_errors', 'On');
date_default_timezone_set('Europe/Brussels');

use Bakame\Csv\Reader;
use Bakame\Csv\Writer;

require '../vendor/autoload.php';

$inputCsv = new Reader('data/prenoms.csv');
$inputCsv->setDelimiter(';');
$inputCsv->setEncoding("iso-8859-15");

//we filter only the least name given in 2010 and we don't take into account the header
$filter = function ($row, $index) {
    return $index > 0 && isset($row[1], $row[2], $row[3])
    && 10 > $row[1]
    && 2010 == $row[3]
    && 'F' == $row[2];
};

//we order the result according to the number of firstname given
$sortBy = function ($row1, $row2) {
    return strcmp($row1[1], $row2[1]);
};

$res = $inputCsv
    ->setFilter($filter)
    ->setSortBy($sortBy)
    ->setLimit(20) //we just want the first 20 results
    ->fetchAll();

$headers = $inputCsv->fetchOne(0);

$writer = new Writer(new SplTempFileObject); //because we don't want to create the file
$writer->insertOne($headers);
$writer->insertAll($res);

//we create a Reader object from the Writer object to filter the resulting CSV
$reader = $writer->getReader();
$names = $reader
    ->setFilter(function ($row, $index) {
        return $index > 0; //we don't want to select the header
    })
    ->setSortBy(function ($row1, $row2) {
        return strcmp($row1[0], $row2[0]); //we are sorting the name
    })
    ->fetchCol(0); //we only return the name column
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="<?=$inputCsv->getEncoding()?>">
    <title>Example 2</title>
</head>
<body>
<h1>Example 4: Using Writer object</h1>
<h3>The table representation of the csv to be save</h3>
<?=$writer->toHTML('writer-csv-data');?>
<h3>The Raw CSV as it will be saved</h3>
<p><em>Notice that the delimiter have changed from <code>;</code> to <code>,</code></em></p>
<pre>
<?=$writer?>
</pre>
<h3>Here's the firstname ordered list</h3>
<ol>
<?php foreach ($names as $firstname) : ?>
    <li><?=$firstname?>
    <?php
endforeach;
?>
</ol>
</body>
</html>
