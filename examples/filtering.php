<?php

use League\Csv\Reader;

require '../vendor/autoload.php';

 //you can instantiate the Reader class with a SplFileObject object
$inputCsv = Reader::createFromPath(new SplFileObject('data/prenoms.csv'));
$inputCsv->setDelimiter(';');

$res = $inputCsv
    ->addFilter(function ($row, $index) {
        return $index > 0; //we don't take into account the header
    })
    ->addFilter(function ($row) {
        return isset($row[1], $row[2], $row[3]); //we make sure the data are present
    })
    ->addFilter(function ($row) {
        return 10 > $row[1]; //the name is used less than 10 times
    })
    ->addFilter(function ($row) {
        return 2010 == $row[3]; //we are looking for the year 2010
    })
    ->addFilter(function ($row) {
        return 'F' == $row[2]; //we are only interested in girl firstname
    })
    ->addSortBy(function ($row1, $row2) {
        return strcmp($row1[1], $row2[1]); //we order the result according to the number of firstname given
    })
    ->setLimit(20) //we just want the first 20 results
    ->fetch();

//get the headers
$headers = $inputCsv->fetchOne(0);
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="iso-8859-15">
    <title>League\Csv\Reader filtering method</title>
    <link rel="stylesheet" href="example.css">
</head>
<body>
<h1>Using the League\Csv\Reader class filtering capabilities</h1>
<table class="table-csv-data">
<caption>Statistics for the 20 least used female name in the year 2010</caption>
<thead>
    <tr>
        <th><?=implode('</th>'.PHP_EOL.'<th>', $headers), '</th>', PHP_EOL; ?>
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
