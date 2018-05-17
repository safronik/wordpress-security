<?php
require_once 'lib/SpbcHelper.php';

class SpbcHelperTest extends \PHPUnit\Framework\TestCase {

    public function test_ip__validate()
    {
        $helper = new SpbcHelper();
		$this->assertEquals('v4',$helper::ip__validate("127.0.0.1"));
 	}
}