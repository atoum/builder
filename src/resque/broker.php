<?php

namespace atoum\builder\resque;

use atoum\builder\resque;

class broker extends resque
{
	public function enqueue($class, array $args = null, $trackStatus = false) : string
	{
		return \Resque::enqueue($this->queue, $class, $args, $trackStatus);
	}

	public function redis()
	{
		return \Resque::redis();
	}
}
