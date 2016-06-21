<?php

namespace atoum\builder\tests\units\resque\jobs;

use atoum\builder;
use InfluxDB\Database;
use InfluxDB\Point;
use mageekguy\atoum;

class build extends atoum\test
{
	public function test__construct()
	{
		$this
			->given($this->newTestedInstance)
			->then
				->array($this->testedInstance->args)->isEmpty
		;
	}

	public function testPerform()
	{

	}
}
