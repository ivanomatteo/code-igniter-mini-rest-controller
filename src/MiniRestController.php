<?php
namespace IvanoMatteo\CIRest;

defined('BASEPATH') or exit('No direct script access allowed');

use CI_Controller;
use Exception;
use stdClass;


class MiniRestController extends CI_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->boot();
	}


	protected $_params = null;
	protected $_headers = null;
	protected $_middlewares = [];
	protected static $_middlewares_global = [];


	protected function boot()
	{
	}


	public function _remap($class_method, $arg_list)
	{
		$http_method = $this->input->method();
		$target_method = $class_method . '_' . $http_method;
		try {

			$this->processMiddlewares();

			$resp = call_user_func_array([$this, $target_method], $arg_list);
			if (empty($resp)) {
				return;
			}
			return $this->response($resp);
		} catch (Exception $ex) {
			return $this->handleExceptions($ex);
		}
	}


	protected function processMiddlewares()
	{
		$arr = array_merge(self::$_middlewares_global, $this->_middlewares);
		foreach ($arr as  $middleware) {
			if (is_callable($middleware)) {
				$middleware($this);
			} else {
				$middleware->handle($this);
			}
		}
	}


	protected function response($resp, $status = 200, $content_type = null)
	{

		if (!isset($content_type) && !is_object($resp) || $resp instanceof stdClass) {
			$this->output->set_output(
				json_encode($resp)
			)->set_content_type('application/json');
		} else {
			$this->output->set_output(
				$resp
			);
		}
		if (isset($content_type)) {
			$this->output->set_content_type($content_type);
		}
		$this->output->set_status_header($status);
	}

	protected function handleExceptions($ex)
	{
		$http_ex = ($ex instanceof HttpException);
		$status = $http_ex ? $ex->getCode() : 500;
		$message = $http_ex ? $ex->getMessage() : get_class($ex);

		
		log_message('error', $ex->getMessage());

		return $this->output
			->set_content_type('application/json')
			->set_status_header($status)
			->set_output(
				json_encode([
					'message' => $message
				])
			);
	}

	function headers($key = null)
	{
		if (!isset($this->_headers)) {
			$this->_headers = $this->input->request_headers();
		}
		if (isset($key)) {
			return isset($this->_headers[$key]) ? $this->_headers[$key] : null;
		} else {
			return $this->_headers;
		}
	}

	function setHeader($key = null, $val = null)
	{
		$this->_headers[$key] = $val;
	}
	function setHeaders(array $val = null)
	{
		$this->_headers = $val;
	}
	function mergeHeaders(array $val = null)
	{
		$this->_headers = array_merge($this->_params, $val);
	}

	function setParam($key = null, $val = null)
	{
		$this->_params[$key] = $val;
	}
	function setParams(array $val = null)
	{
		$this->_params = $val;
	}
	function mergeParams(array $val = null)
	{
		$this->_params = array_merge($this->_params, $val);
	}

	function params($key = null)
	{
		if ($this->input->is_cli_request()) {
			if (isset($key)) {
				return null;
			} else {
				return [];
			}
		}

		if (!isset($this->_params)) {

			switch ($this->input->method()) {

				case 'get':
					$this->_params = $_GET;
					break;
				case 'post':
					if (!empty($_POST)) {
						$this->_params = $_POST;
					}
				case 'put':
				case 'patch':
				case 'delete':

					if (empty($this->_params)) {
						$rawData = file_get_contents("php://input");
						if (!empty($rawData)) {
							if (!empty($_SERVER['HTTP_CONTENT_TYPE']) && $_SERVER['HTTP_CONTENT_TYPE'] === 'application/x-www-form-urlencoded') {
								parse_str($rawData, $this->_params);
							}

							if (
								empty($this->_params) &&
								(!empty($_SERVER['HTTP_CONTENT_TYPE']) && $_SERVER['HTTP_CONTENT_TYPE'] === 'application/json') ||
								($rawData[0] === '{' || $rawData[0] === '[')
							) {
								$this->_params = json_decode($rawData);
								if (empty($this->_params)) {
									$this->_params = [];
								} else {
									if (is_object($this->_params)) {
										$this->_params = (array) $this->_params;
									} else if (is_array($this->_params)) {
										$this->_params = [
											'json_array' => $this->_params
										];
									} else {
										$this->_params = [
											'json_value' => $this->_params
										];
									}
								}
							}
							if (empty($this->_params)) {
								parse_str($rawData, $this->_params);
							}
						}
					}

					break;

				default:
					throw new HttpException('unknown http method', 405);
			}
			if (!$this->_params) {
				$this->_params = [];
			}
		}

		if (isset($key)) {
			isset($this->_params[$key]) ? $this->_params[$key] : null;
		} else {
			return $this->_params;
		}
	}
}
