<?php
namespace IvanoMatteo\CIRest;

defined('BASEPATH') or exit('No direct script access allowed');

use Exception;

class HttpException extends Exception
{
	function __construct($message = 'Error', $code = 406, $prev = null)
	{
		parent::__construct($message, $code, $prev);
	}	
}