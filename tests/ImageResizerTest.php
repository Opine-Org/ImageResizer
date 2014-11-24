<?php
namespace Opine;

use PHPUnit_Framework_TestCase;
use Opine\Config\Service;
use Opine\Container\Service;

class ImageResizerTest extends PHPUnit_Framework_TestCase {

    public function setup () {
        $root = __DIR__ . '/../public';
        $config = new Config($root);
        $container = Container::instance($root, $config, $root . '/../container.yml');
    }

    public function testSample () {
        $this->assertTrue(true);
    }
}