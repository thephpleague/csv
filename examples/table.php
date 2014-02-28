<?php

use League\Csv\Reader;

require '../vendor/autoload.php';

$inputCsv = new Reader('data/prenoms.csv');
$inputCsv->setDelimiter(';');
$inputCsv->setEncoding("iso-8859-15");
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="<?=$inputCsv->getEncoding()?>">
    <title>Using the toHTML() method</title>
    <link rel="stylesheet" href="example.css">
</head>
<body>
<?=$inputCsv->toHTML('table-csv-data with-header');?>
</body>
</html>
