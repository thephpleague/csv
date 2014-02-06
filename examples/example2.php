<?php

error_reporting(-1);
ini_set('display_errors', 'On');
date_default_timezone_set('Europe/Brussels');

use Bakame\Csv\Reader;

require '../vendor/autoload.php';

 //you can instantiate the Reader class with a SplFileObject object
$inputCsv = new Reader(new SplFileObject('data/prenoms.csv'));
$inputCsv->setDelimiter(';');
$inputCsv->setEncoding("iso-8859-15");

//we filter only the least name given in 2010 and we don't take into account the header
$filter = function ($row, $index) {
    return $index > 0 && isset($row[1], $row[2], $row[3])
    && 10 > $row[1]
    && 2010 == $row[3]
    && 'F' == $row[2];
};

//we order the result according to the number of firstname given
$sortBy = function ($row1, $row2) {
    return strcmp($row1[1], $row2[1]);
};

$res = $inputCsv
    ->setFilter($filter)
    ->setSortBy($sortBy)
    ->setLimit(20) //we just want the first 20 results
    ->fetchAll();

//get the headers
$headers = $inputCsv->fetchOne(0);
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="<?=$inputCsv->getEncoding()?>">
<title>Example 3</title>
</head>
<body>
<h1>Example 3: Using the Reader class filtering capabilities</h1>
<table>
<caption>Statistics for the 20 least used female name in the year 2010</caption>
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
