<?php

use League\Csv\Writer;

require '../vendor/autoload.php';

$header = ["position" , "team", "played", "goals difference", "points"];

$contents = [
    [1, "Chelsea", 26, 27, 57],
    [2, "Arsenal", 26, 22, 56],
    [3, "Manchester City", 25, 41, 54,],
    [4, "Liverpool", 26, 34, 53],
    [5, "Tottenham", 26, 4, 50],
    [6, "Everton", 25, 11, 45],
    [7, "Manchester United", 26, 10, 42],
];

$writer = Writer::createFromFileObject(new SplTempFileObject()); //the CSV file will be created using a temporary File
$writer->setDelimiter("\t"); //the delimiter will be the tab character
$writer->setNewline("\r\n"); //use windows line endings for compatibility with some csv libraries
$writer->setOutputBOM(Writer::BOM_UTF8); //adding the BOM sequence on output
$writer->insertOne($header);
$writer->insertAll($contents);
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Using the Writer class</title>
    <link rel="stylesheet" href="example.css">
</head>
<body>
<h1>Example 4: Using the Writer class</h1>
<h3>The table representation of the csv</h3>
<?=$writer->toHTML('table-csv-data with-header');?>
<h3>The Raw CSV to be saved</h3>
<pre>
<?=$writer?>
</pre>
</body>
</html>
