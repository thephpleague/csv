<?php

namespace League\Csv\test\Stream;

use PHPUnit_Framework_TestCase;
use League\Csv\Stream\EncodingFilter;
use League\Csv\Reader;
use SplTempFileObject;

/**
 * @group reader
 */
class EncodingFilterTest extends PHPUnit_Framework_TestCase
{
    private $csv;

    private $expected = [
        ['john', 'doe', 'john.doe@example.com'],
        ['jane','doe','jane.doe@example.com'],
    ];

    public function setUp()
    {
        $csv = new SplTempFileObject;
        foreach ($this->expected as $row) {
            $csv->fputcsv($row);
        }

        $this->csv = new Reader($csv);
    }

    /**
    * @expectedException \InvalidArgumentException
    */
    public function testEncodingTo()
    {
        $expected = 'ISO-8859-15';
        $conv = new EncodingFilter;
        $conv->setEncodingTo('iso-8859-15');
        $this->assertSame($expected, $conv->getEncodingTo());
        $conv->setEncodingTo(' iso-8859-15');
        $this->assertSame($expected, $conv->getEncodingTo());
        $conv->setEncodingTo(' ');
    }

    /**
    * @expectedException \InvalidArgumentException
    */
    public function testEncodingFrom()
    {
        $expected = 'ISO-8859-15';
        $conv = new EncodingFilter;
        $conv->setEncodingFrom('iso-8859-15');
        $this->assertSame($expected, $conv->getEncodingFrom());
        $conv->setEncodingFrom(' iso-8859-15');
        $this->assertSame($expected, $conv->getEncodingFrom());
        $conv->setEncodingFrom('   ');
    }

    public function testStream()
    {
        $stream_filter = new EncodingFilter;
        $stream_filter->setEncodingFrom('iso-8859-15');
        $stream_filter->setEncodingTo('utf-8');
        $reader = new Reader(dirname(__DIR__).'/data/prenoms.csv', 'r', $stream_filter);
        $res = $reader->setOffset(5)->setLimit(5)->fetchAll();
        $this->assertCount(5, $res);
        $writer = $reader->getWriter('a+', $stream_filter);
        $this->assertInstanceof("\\League\Csv\Writer", $writer);
        $newReader = $writer->getReader($stream_filter);
        $this->assertInstanceof("\\League\Csv\Reader", $newReader);
    }

    public function testgetName()
    {
        $this->assertSame('csv.content.converter', EncodingFilter::getName());
    }

    public function testOnCreate()
    {
        $stream_filter = new EncodingFilter;
        $stream_filter->setEncodingFrom('iso-8859-15');
        $stream_filter->setEncodingTo('utf-8');
        $stream_filter->filtername = 'toto';
        $this->assertFalse($stream_filter->onCreate());
        $stream_filter->filtername = 'csv.content.converter.x7z?';
        $this->assertFalse($stream_filter->onCreate());
        $stream_filter->filtername = 'csv.content.converter.UTF-8:ISO-8859-15';
        $this->assertTrue($stream_filter->onCreate());
    }
}
