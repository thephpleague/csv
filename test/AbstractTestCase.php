<?php

namespace League\Csv\Test;

use PHPUnit_Framework_TestCase;

class AbstractTestCase extends PHPUnit_Framework_TestCase
{
    protected function checkRequirements()
    {
        parent::checkRequirements();
        $annotations = $this->getAnnotations();
        foreach ($annotations as $type => $bag) {
            if (!array_key_exists('skipIfHHVM', $bag)) {
                continue;
            }
            if (defined('HHVM_VERSION')) {
                $this->markTestSkipped('This test does not run on HHVM');
            }
        }
    }
}
