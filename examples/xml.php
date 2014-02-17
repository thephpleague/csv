<?php

error_reporting(-1);
ini_set('display_errors', 1);

use Bakame\Csv\Reader;

require '../vendor/autoload.php';

$inputCsv = new Reader('data/prenoms.csv');
$inputCsv->setEncoding('ISO-8859-15');
$inputCsv->setDelimiter(';');
$doc = $inputCsv->toXML('csv', 'ligne', 'cellule');
$xml = $doc->saveXML();
header('Content-Type: application/xml; charset="utf-8"');
header('Content-Length: '.strlen($xml));
die($xml);
