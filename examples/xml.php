<?php

error_reporting(-1);
ini_set('display_errors', '1');

use League\Csv\Reader;

require '../vendor/autoload.php';

$inputCsv = Reader::createFromPath('data/prenoms.csv');
$inputCsv->setEncodingFrom('ISO-8859-15');
$inputCsv->setDelimiter(';');
$doc = $inputCsv->toXML('csv', 'ligne', 'cellule');
$xml = $doc->saveXML();
header('Content-Type: application/xml; charset="utf-8"');
header('Content-Length: '.strlen($xml));
die($xml);
