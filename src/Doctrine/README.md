## Sub-split of CSV for Doctrine integration.

> [!CAUTION]  
> Sub-split of League\Csv.
> ⚠️ this is a sub-split, for pull requests and issues, visit: https://github.com/thephpleague/csv

```bash
composer require league/csv-doctrine
```

View the [documentation](https://csv.thephpleague.com/9.0/extensions/doctrine/).

> [!WARNING]  
> With the release of league\csv `9.16.0` this package is officially marked as
> deprecated no development aside security fixes will be applied to it.
> The package is also marked as abandoned if you use composer.
> 
> For replacement please visit: https://csv.thephpleague.com/9.0/reader/statement/

In a nutshell, the features provided by this package have been implemented in a
better integrated manner directly into the main package, without the need for a
third party package.

The following example

```php
<?php

use Doctrine\Common\Collections\Criteria;
use League\Csv\Doctrine as CsvDoctrine;
use League\Csv\Reader;

$csv = Reader::createFromPath('/path/to/my/file.csv');
$csv->setHeaderOffset(0);
$csv->setDelimiter(';');

$criteria = Criteria::create()
    ->andWhere(Criteria::expr()->eq('prenom', 'Adam'))
    ->orderBy( [ 'annee' => 'ASC', 'foo' => 'desc', ] )
    ->setFirstResult(3)
    ->setMaxResults(10)
;

$resultset = CsvDoctrine\CriteriaConverter::convert($criteria)->process($csv);
```

can be written using only the `Statement` class since version `9.16.0`:

```php
<?php

use League\Csv\Reader;
use League\Csv\Statement;

$csv = Reader::createFromPath('/path/to/my/file.csv');
$csv->setHeaderOffset(0);
$csv->setDelimiter(';');

$criteria = Statement::create()
    ->andWhere('prenom', '=', 'Adam')
    ->orderByAsc('annee')
    ->orderByDesc('foo')
    ->offset(3)
    ->limit(10);
    
$resultset = $criteria->process($csv);
```
