<?php
namespace Opine;
use PHPUnit_Framework_TestCase;

class ImageResizerTest extends PHPUnit_Framework_TestCase {
    public function setup () {
        $root = __DIR__;
        $container = new Container($root, $root . '/../container.yml');
    }

    public function testSample () {
        $this->assertTrue(true);
    }
}