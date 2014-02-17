<?php

use Bakame\Csv\Reader;

require '../vendor/autoload.php';

$inputCsv = new Reader('data/prenoms.csv');
$inputCsv->setEncoding('ISO-8859-15');
$inputCsv->output('firstname.csv'); //specifying a filename triggers header sending
