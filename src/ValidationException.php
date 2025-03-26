<?php

namespace IvanoMatteo\CIRest;

defined('BASEPATH') or exit('No direct script access allowed');

use \CI_Form_validation;

class ValidationException extends HttpException
{
	private $errors = [];

	public static function fromForm(CI_Form_validation $form, ?string $message = null)
	{
		return new self($form->error_array(), $message);
	}

	function __construct(array $errors, ?string $message = null)
	{
		$this->errors = $errors;

		$firstErrKey = array_keys($this->errors)[0] ?? 0;
		$firstErrVal = $this->errors[$firstErrKey] ?? 'errore';

		parent::__construct(HttpConst::UNPROCESSABLE_ENTITY, $message ?? "{$firstErrKey}: {$firstErrVal}");
	}

	function getErrors()
	{
		return $this->errors;
	}
}
