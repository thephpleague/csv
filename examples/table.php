<?php

use League\Csv\Reader;
use League\Csv\Statement;

require '../vendor/autoload.php';

$csv = Reader::createFromPath('data/prenoms.csv');
$csv->setDelimiter(';');
$csv->setInputEncoding("iso-8859-15");
$csv->setHeader(0);
$stmt = (new Statement())->setLimit(30);
$records = $csv->select($stmt); //we are limiting the convertion to the first 31 rows
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Using the toHTML() method</title>
    <link rel="stylesheet" href="example.css">
</head>
<body>
<?=$records->toHTML('table-csv-data with-header');?>
</body>
</html>
