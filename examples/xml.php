<?php

use League\Csv\Reader;
use League\Csv\Statement;

require '../vendor/autoload.php';

 //we order the result according to the number of firstname given
$func = function ($row1, $row2) {
    return strcmp($row2[1], $row1[1]);
};

$csv = Reader::createFromPath('data/prenoms.csv');
$csv->setInputEncoding('ISO-8859-15');
$csv->setDelimiter(';');
$stmt = (new Statement())
    ->setOffset(1)
    ->setLimit(10)
    ->addSortBy($func)
;
$doc = $csv->select($stmt)->toXML('csv', 'ligne', 'cellule');
$xml = $doc->saveXML();
header('Content-Type: application/xml; charset="utf-8"');
header('Content-Length: '.strlen($xml));
die($xml);
