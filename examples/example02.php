<?php

error_reporting(-1);
ini_set('display_errors', 'On');

use Bakame\Csv\Reader;

require '../vendor/autoload.php';

$inputCsv = new Reader('data/prenoms.csv');
$inputCsv->setEncoding('ISO-8859-15');
header('Content-Type: text/csv; charset="'.$inputCsv->getEncoding().'"');
header('Content-Disposition: attachment; filename="firstname.csv"');
$inputCsv->output();
