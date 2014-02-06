<?php

namespace Bakame\Csv\Traits;

use ArrayIterator;
use ReflectionClass;
use PHPUnit_Framework_TestCase;

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
        return $this->getObjectForTrait('\Bakame\Csv\Traits\IteratorQuery');
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
            return $row == 'john';
        };
        $this->traitQuery->setFilter($func);

        $iterator = $this->invokeMethod($this->traitQuery, 'execute', [$this->iterator]);
        $res = iterator_to_array($iterator);
        $this->assertCount(1, $res);
    }

    public function testSortBy()
    {
        $this->traitQuery->setSortBy('strcmp');
        $iterator = $this->invokeMethod($this->traitQuery, 'execute', [$this->iterator]);
        $res = iterator_to_array($iterator);

        $this->assertSame(['bar', 'foo', 'jane', 'john'], array_values($res));
    }

    public function testExecuteWithCallback()
    {
        $iterator = $this->invokeMethod($this->traitQuery, 'execute', [$this->iterator, function ($value) {
            return strtoupper($value);
        }]);
        $this->assertSame(array_map('strtoupper', $this->data), iterator_to_array($iterator));
    }
}
