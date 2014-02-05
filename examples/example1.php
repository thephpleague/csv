<?php

error_reporting(-1);
ini_set('display_errors', 'On');
date_default_timezone_set('Europe/Brussels');

use Bakame\Csv\Reader;

require '../vendor/autoload.php';

$inputCsv = new Reader('data/prenoms.csv');
$inputCsv->setDelimiter(';');
$encoding = "iso-8859-15";
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="<?=$encoding?>">
    <title>Example 2</title>
</head>
<body>
<h1>Example 2: Using the toHTML methods to output the csv</h1>
<p>This is the same result as example1 but with the <code>toHTML</code> method</p>
<?=$inputCsv->toHTML('table-csv-data', $encoding);?>
</body>
</html>
