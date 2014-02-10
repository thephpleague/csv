Examples
==========


* [Converting the CSV into a HTML Table](example00.php) with the `toHTML` method
* [Converting the CSV into a Json String](example01.php) string
* [Downloading the CSV](example02.php) using the `output` method
* [Selecting specific rows in the CSV](example03.php)
* [Filtering a CSV](example04.php) using the `Bakame\Csv\Reader` class
* [Creating a CSV](example05.php) using the `Bakame\Csv\Writer` class
* [From writing mode to reader mode](example06.php)

The CSV data use for the examples are taken from [Paris Opendata](http://opendata.paris.fr/opendata/jsp/site/Portal.jsp?document_id=60&portlet_id=121)

Tips
------

* When creating a file using the `Bakame\Csv\Writer` class, first use the `insert*` methods and manipulate your CSV afterwards. If you manipulate your data before you may change the file cursor position and get unexpected results.

* If your are dealing with non-unicode data, please don't forger to specify the encoding parameter using the `setEncoding` method otherwise you json conversion may no work.