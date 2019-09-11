<?php

/**
 * League.Csv (https://csv.thephpleague.com)
 *
 * (c) Ignace Nyamagana Butera <nyamsprod@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace League\Csv;

use Traversable;

/**
 * Converts tabular data into a SQL string.
 */
class MySQLConverter
{
    /**
     * @var string
     */
    protected $table = 'csv_data';

    /**
     * @var string
     */
    protected $database = 'csv_data';

    /**
     * MySQLConverter constructor.
     * @param string $database
     * @param string $table
     */
    public function __construct(string $database = '', string $table = '')
    {
        if (! empty($table)) {
            $this->table = $table;
        }
        if (! empty($database)) {
            $this->database = $database;
        }
    }

    /**
     * @param Traversable $records
     * @return string
     */
    public function convert(Traversable $records): string
    {
        $sql = $this->generateUseStatement();
        $sql .= $this->generateDropStatement();
        $sql .= $this->generateCreateStatement($records);
        $sql .= $this->generateInsertStatement($records);

        return $sql;
    }

    /**
     * @return string
     */
    protected function generateUseStatement(): string
    {
        return 'USE `'.$this->database.'`;'.PHP_EOL;
    }

    /**
     * @return string
     */
    protected function generateDropStatement(): string
    {
        return 'DROP TABLE IF EXISTS '.$this->getTableStr().';'.PHP_EOL;
    }

    /**
     * @param Traversable $records
     * @return string
     */
    protected function generateCreateStatement(Traversable $records): string
    {
        $fields = $this->prepareColumns($records);
        return 'CREATE TABLE '.$this->getTableStr().' ('.PHP_EOL.$fields.PHP_EOL.') ENGINE=innodb;'.PHP_EOL;
    }

    /**
     * @param Traversable $records
     * @return string
     */
    protected function generateInsertStatement(Traversable $records): string
    {
        $records = $this->prepareRecords($records);
        return $records;
    }

    /**
     * @return string
     */
    protected function getTableStr(): string
    {
        return '`'.$this->database.'`.`'.$this->table.'`';
    }

    /**
     * @param Traversable $records
     * @return string
     */
    protected function prepareRecords(Traversable $records): string
    {
        $values = [];
        foreach ($records as $row) {
            $fields = [];
            foreach ($row as $data) {
                $data = trim($data);
                if (empty($data)) {
                    $fields[] = 'NULL';
                } else {
                    $fields[] = "'".trim($data)."'";
                }
            }
            $fields = 'INSERT INTO '.$this->getTableStr().' VALUES ('.implode(',', $fields).');';
            $values[] = $fields;
        }
        $values = implode(PHP_EOL, $values);
        return $values;
    }


    /**
     * @param Traversable $records
     * @return string
     */
    protected function prepareColumns(Traversable $records): string
    {
        $records->rewind();
        $sample_data = $records->current();
        $columns = array_keys($sample_data);
        $fields = [];
        foreach ($columns as $column) {
            $column_name = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $column));
            $column_name = preg_replace('/[ ]+/', ' ', $column_name);
            $column_name = str_replace(' ', '_', $column_name);
            $data = trim($sample_data[$column]);

            $is_int = filter_var($data, FILTER_VALIDATE_INT);
            if ($is_int !== false) {
                if ($is_int < 2147483647) {
                    $fields[] = '`'.$column_name.'` INT NULL';
                } else {
                    $fields[] = '`'.$column_name.'` BIGINT NULL';
                }

                continue;
            }

            $is_float = filter_var($data, FILTER_VALIDATE_FLOAT);
            if ($is_float !== false) {
                if ($is_float < 2147483647) {
                    $fields[] = '`'.$column_name.'` FLOAT(10,24) NULL';
                } else {
                    $fields[] = '`'.$column_name.'` DOUBLE(16,53) NULL';
                }

                continue;
            }

            if (strlen($data) < 255) {
                $fields[] = '`'.$column_name.'` VARCHAR(255) NULL';
            } else {
                $fields[] = '`'.$column_name.'` LONGTEXT NULL';
            }
        }

        $fields = implode(','.PHP_EOL, $fields);
        return $fields;
    }
}
