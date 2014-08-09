<?php

error_reporting(-1);
ini_set('display_errors', 1);

use League\Csv\Writer;

require '../vendor/autoload.php';

$writer = Writer::createFromFileObject(new SplTempFileObject); //the CSV file will be created into a temporary File
$writer->setDelimiter("\t"); //the delimiter will be the tab character
$writer->setEncodingFrom("utf-8");

$headers = ["position" , "team", "played", "goals difference", "points"];
$writer->insertOne($headers);

$teams = [
    [1, "Chelsea", 26, 27, 57],
    [2, "Arsenal", 26, 22, 56],
    [3, "Manchester City", 25, 41, 54],
    [4, "Liverpool", 26, 34, 53],
    [5, "Tottenham", 26, 4, 50],
    [6, "Everton", 25, 11, 45],
    [7, "Manchester United", 26, 10, 42],
];

$writer->insertAll($teams);
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Using the \League\Writer object</title>
    <link rel="stylesheet" href="example.css">
</head>
<body>
<h1>Example 4: Using Writer object</h1>
<h3>The table representation of the csv</h3>
<?=$writer->toHTML('table-csv-data with-header');?>
<h3>The Raw CSV to be saved</h3>
<pre>
<?=$writer?>
</pre>
</body>
</html>
