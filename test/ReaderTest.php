<?php

namespace League\Csv\Test;

use DOMDocument;
use Iterator;
use League\Csv\Reader;
use League\Csv\RecordSet;
use PHPUnit\Framework\TestCase;
use SplTempFileObject;

/**
 * @group reader
 */
class ReaderTest extends TestCase
{
    private $csv;

    private $expected = [
        ['john', 'doe', 'john.doe@example.com'],
        ['jane', 'doe', 'jane.doe@example.com'],
    ];

    public function setUp()
    {
        $tmp = new SplTempFileObject();
        foreach ($this->expected as $row) {
            $tmp->fputcsv($row);
        }

        $this->csv = Reader::createFromFileObject($tmp);
    }

    public function testSelect()
    {
        $this->assertInstanceof(RecordSet::class, $this->csv->select());
    }

    public function testCall()
    {
        $this->csv->setHeader(0);
        $this->assertCount(1, $this->csv);
        $this->assertInternalType('array', $this->csv->jsonSerialize());
        $this->assertInternalType('array', $this->csv->fetchAll());
        $this->assertInternalType('array', $this->csv->fetchOne());
        $this->assertInstanceof(Iterator::class, $this->csv->fetchColumn());
        $this->assertInstanceof(Iterator::class, $this->csv->fetchPairs());
        $this->assertInstanceOf(DOMDocument::class, $this->csv->toXML());
        $this->assertContains('<table', $this->csv->toHTML());
    }

    public function testCallThrowsBadMethodCallException()
    {
        $this->expectException(\BadMethodCallException::class);
        $this->csv->filtterFieldName('john');
    }
}
