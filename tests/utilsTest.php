<?php
namespace ReVival\Test;
require dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR .'autoload.php';

use PHPUnit\Framework\TestCase;
use ReVival\utils;
use \InvalidArgumentException;

class UtilsTest extends TestCase {
	public function testPartition() {
        
        $this->assertEquals(
            [0, 1],
            Utils::partition(0, 1, 1),
            'When 1 part'
        );
        
        $this->assertEquals(
            [0, 1, 3],
            Utils::partition(0, 3, 2),
            'When 2 parts even index'
        );

        $this->assertEquals(
            [0, 2, 4],
            Utils::partition(0, 4, 2),
            'When 2 parts odd index'
        );

        $this->assertEquals(
            [0, 1, 2, 4],
            Utils::partition(0, 4, 3),
            'When 3 parts odd index'
        );
	}
}
?>