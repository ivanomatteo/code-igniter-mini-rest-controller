<?php

namespace IvanoMatteo\CIRest;

defined('BASEPATH') or exit('No direct script access allowed');

use Exception;

class HttpException extends Exception
{
	function __construct($code, $message = '', $prev = null)
	{
		parent::__construct($message, $code, $prev);
	}
}
