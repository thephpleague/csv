<?php

namespace League\Csv\test\Iterator;

use ArrayIterator;
use ReflectionClass;
use PHPUnit_Framework_TestCase;
use League\Csv\Iterator\IteratorQuery;

/**
 * @group iterator
 */
class IteratorQueryTest extends PHPUnit_Framework_TestCase
{
    private $traitQuery;
    private $iterator;
    private $data = ['john', 'jane', 'foo', 'bar'];

    public function invokeMethod(&$object, $methodName, array $parameters = [])
    {
        $reflection = new ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }

    private function createTraitObject()
    {
        return $this->getObjectForTrait('\League\Csv\Iterator\IteratorQuery');
    }

    public function setUp()
    {
        $this->traitQuery = $this->createTraitObject();
        $this->iterator = new ArrayIterator($this->data);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSetLimit()
    {
        $this->traitQuery->setLimit(1);
        $iterator = $this->invokeMethod($this->traitQuery, 'execute', [$this->iterator]);
        $res = iterator_to_array($iterator);
        $this->assertCount(1, $res);

        $this->traitQuery->setLimit(-4);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSetOffset()
    {
        $this->traitQuery->setOffset(1);
        $iterator = $this->invokeMethod($this->traitQuery, 'execute', [$this->iterator]);
        $res = iterator_to_array($iterator);
        $this->assertCount(3, $res);

        $this->traitQuery->setOffset('toto');
    }

    public function testIntervalLimitTooLong()
    {
        $this->traitQuery->setOffset(3);
        $this->traitQuery->setLimit(10);
        $iterator = $this->invokeMethod($this->traitQuery, 'execute', [$this->iterator]);
        $res = iterator_to_array($iterator);
        $this->assertSame([3 => 'bar'], $res);
        $this->assertCount(1, $res);
    }

    public function testInterval()
    {
        $this->traitQuery->setOffset(1);
        $this->traitQuery->setLimit(1);
        $iterator = $this->invokeMethod($this->traitQuery, 'execute', [$this->iterator]);
        $res = iterator_to_array($iterator);
        $this->assertCount(1, $res);
    }

    public function testFilter()
    {
        $func = function ($row) {
            return false !== strpos($row, 'o');
        };
        $this->traitQuery->setFilter($func);

        $iterator = $this->invokeMethod($this->traitQuery, 'execute', [$this->iterator]);
        $this->assertCount(2, iterator_to_array($iterator, false));

        $func2 = function ($row) {
            return false !== strpos($row, 'j');
        };
        $this->traitQuery->addFilter($func2);
        $this->traitQuery->addFilter($func);

        $iterator = $this->invokeMethod($this->traitQuery, 'execute', [$this->iterator]);
        $this->assertCount(1, iterator_to_array($iterator, false));

        $this->traitQuery->addFilter($func2);
        $this->traitQuery->addFilter($func);
        $this->assertTrue($this->traitQuery->hasFilter($func2));
        $this->traitQuery->removeFilter($func2);
        $this->assertFalse($this->traitQuery->hasFilter($func2));

        $iterator = $this->invokeMethod($this->traitQuery, 'execute', [$this->iterator]);
        $this->assertCount(2, iterator_to_array($iterator, false));
    }

    public function testSortBy()
    {
        $this->traitQuery->setSortBy('strcmp');
        $iterator = $this->invokeMethod($this->traitQuery, 'execute', [$this->iterator]);
        $res = iterator_to_array($iterator, false);
        $this->assertSame(['bar', 'foo', 'jane', 'john'], $res);

        $this->traitQuery->addSortBy('strcmp');
        $this->traitQuery->addSortBy('strcmp');
        $this->traitQuery->removeSortBy('strcmp');
        $this->assertTrue($this->traitQuery->hasSortBy('strcmp'));
        $iterator = $this->invokeMethod($this->traitQuery, 'execute', [$this->iterator]);
        $res = iterator_to_array($iterator, false);
        $this->assertSame(['bar', 'foo', 'jane', 'john'], $res);
    }

    public function testExecuteWithCallback()
    {
        $iterator = $this->invokeMethod($this->traitQuery, 'execute', [$this->iterator, function ($value) {
            return strtoupper($value);
        }]);
        $this->assertSame(array_map('strtoupper', $this->data), iterator_to_array($iterator));
    }
}
