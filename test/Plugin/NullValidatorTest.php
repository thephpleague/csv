<?php

namespace League\Csv\Test\Plugin;

use League\Csv\Exception\InvalidRowException;
use League\Csv\Plugin\ForbiddenNullValuesValidator;
use League\Csv\Test\AbstractTestCase;
use League\Csv\Writer;
use SplFileObject;
use SplTempFileObject;

/**
 * @group validators
 */
class NullValidatorTest extends AbstractTestCase
{
    private $csv;

    public function setUp()
    {
        $this->csv = Writer::createFromFileObject(new SplTempFileObject());
    }

    public function tearDown()
    {
        $csv = new SplFileObject(dirname(__DIR__).'/data/foo.csv', 'w');
        $csv->setCsvControl();
        $csv->fputcsv(['john', 'doe', 'john.doe@example.com'], ',', '"');
        $this->csv = null;
    }

    public function testInsertNullThrowsException()
    {
        $validator = new ForbiddenNullValuesValidator();
        $validator_name = 'null_as_exception';
        $expected = ['john', null, 'john.doe@example.com'];
        $this->csv->addValidator($validator, $validator_name);
        try {
            $this->csv->insertOne($expected);
        } catch (InvalidRowException $e) {
            $this->assertSame($validator_name, $e->getName());
            $this->assertSame($expected, $e->getData());
            $this->assertSame('row validation failed', $e->getMessage());
        }
    }
}
