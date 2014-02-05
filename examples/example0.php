<?php

error_reporting(-1);
ini_set('display_errors', 'On');
date_default_timezone_set('Europe/Brussels');

use Bakame\Csv\Reader;

require '../vendor/autoload.php';

$inputCsv = new Reader('data/prenoms.csv');
$inputCsv->setDelimiter(';');
$encoding = "iso-8859-15";

//get the header
$headers = $inputCsv->fetchOne(0);
//get all the data without the header
$res = $inputCsv
    ->setOffset(1)
    ->fetchAll();

?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="<?=$encoding?>">
    <title>Example 1</title>
</head>
<body>
<h1>Example 1: Simple Reader class usage</h1>
<table>
<caption>Full Statistics</caption>
<thead>
    <tr>
<?php foreach ($headers as $title): ?>
    <th><?=$title?></th>
    <?php
endforeach;
?>
    </tr>
</thead>
<tbody>
<?php foreach ($res as $row) : ?>
    <tr>
    <td><?=implode('</td>'.PHP_EOL.'<td>', $row), '</td>', PHP_EOL; ?>
    </tr>
    <?php
endforeach;
?>
</tbody>
</table>
</body>
</html>
