<?php

use League\Csv\Reader;

require '../vendor/autoload.php';

$inputCsv = Reader::createFromPath('data/prenoms.csv');
$inputCsv->setEncodingFrom('ISO-8859-15');
$inputCsv->output('firstname.csv'); //specifying a filename triggers header sending
