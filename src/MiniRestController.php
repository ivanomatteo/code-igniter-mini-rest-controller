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

	protected $auto_decode_multipart = false;

	protected $_params = null;
	protected $_headers = null;
	protected $_middlewares = [];
	public static $_middlewares_global = [];


	protected function boot() {}


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
		$status = $http_ex ? $ex->getCode() : HttpConst::INTERNAL_SERVER_ERROR;
		$message = $http_ex ? $ex->getMessage() : get_class($ex);

		if (!$http_ex || $ex->getCode() >= HttpConst::INTERNAL_SERVER_ERROR) {
			log_message('error', $ex->getMessage());
		}

		$payload = [
			'message' => $message
		];

		if ($ex instanceof ValidationException) {
			$payload['errors'] = $ex->getErrors();
		}

		return $this->output
			->set_content_type('application/json')
			->set_status_header($status)
			->set_output(json_encode($payload));
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
					throw new HttpException('unknown http method', HttpConst::METHOD_NOT_ALLOWED);
			}
			if (!$this->_params) {
				$this->_params = [];
			}

			$contentType = $this->input->request_headers()['Content-Type'] ?? '';


			if ($this->auto_decode_multipart && preg_match('/^multipart\/form-data/i', $contentType)) {
				foreach ($this->_params as $pname => $pvalue) {
					$this->_params[$pname] = json_decode($pvalue, true);
				}
			}
		}

		if (isset($key)) {
			return $this->_params[$key] ?? null;
		} else {
			return $this->_params;
		}
	}


	/**
	 * $config:
	 * 
	 * upload_path	            None	None	                The path to the directory where the upload should be placed. The directory must be writable and the path can be absolute or relative.
	 * allowed_types	        None	None	                The mime types corresponding to the types of files you allow to be uploaded. Usually the file extension can be used as the mime type. Can be either an array or a pipe-separated string.
	 * file_name	            None	Desired file name	    If set CodeIgniter will rename the uploaded file to this name. The extension provided in the file name must also be an allowed file type. If no extension is provided in the original file_name will be used.
	 * file_ext_tolower	        FALSE	TRUE/FALSE (boolean)	If set to TRUE, the file extension will be forced to lower case
	 * overwrite	            FALSE	TRUE/FALSE (boolean)	If set to true, if a file with the same name as the one you are uploading exists, it will be overwritten. If set to false, a number will be appended to the filename if another with the same name exists.
	 * max_size                 0	    None	                The maximum size (in kilobytes) that the file can be. Set to zero for no limit. Note: Most PHP installations have their own limit, as specified in the php.ini file. Usually 2 MB (or 2048 KB) by default.
	 * max_width	            0	    None	                The maximum width (in pixels) that the image can be. Set to zero for no limit.
	 * max_height	            0	    None	                The maximum height (in pixels) that the image can be. Set to zero for no limit.
	 * min_width	            0	    None	                The minimum width (in pixels) that the image can be. Set to zero for no limit.
	 * min_height	            0	    None	                The minimum height (in pixels) that the image can be. Set to zero for no limit.
	 * max_filename	            0	    None	                The maximum length that a file name can be. Set to zero for no limit.
	 * max_filename_increment	100	    None	                When overwrite is set to FALSE, use this to set the maximum filename increment for CodeIgniter to append to the filename.
	 * encrypt_name	            FALSE	TRUE/FALSE (boolean)	If set to TRUE the file name will be converted to a random encrypted string. This can be useful if you would like the file saved with a name that can not be discerned by the person uploading it.
	 * remove_spaces	        TRUE	TRUE/FALSE (boolean)	If set to TRUE, any spaces in the file name will be converted to underscores. This is recommended.
	 * detect_mime	            TRUE	TRUE/FALSE (boolean)	If set to TRUE, a server side detection of the file type will be performed to avoid code injection attacks. DO NOT disable this option unless you have no other option as that would cause a security risk.
	 * mod_mime_fix	            TRUE	TRUE/FALSE (boolean)	If set to TRUE, multiple filename extensions will be suffixed with an underscore in order to avoid triggering Apache mod_mime. DO NOT turn off this option if your upload directory is public, as this is a security risk.
	 * 
	 * return:
	 * 
	 * file_name            mypic.jpg
	 * file_type            image/jpeg
	 * file_path            /path/to/your/upload/
	 * full_path            /path/to/your/upload/jpg.jpg
	 * raw_name             mypic
	 * orig_name            mypic.jpg
	 * client_name          mypic.jpg
	 * file_ext             .jpg
	 * file_size            22.2
	 * is_image             1
	 * image_width          800
	 * image_height         600
	 * image_type           jpeg
	 * image_size_str       width="800" height="200"
	 * 
	 * https://codeigniter.com/userguide3/libraries/file_uploading.html#reference-guide
	 * 
	 */
	function do_upload(string $field, string $folder, string $types = 'jpg|png', ?string $filename = null, int $maxSize = 0, array $config = [])
	{
		$config['upload_path']          = APPPATH . './uploads/' . $folder;
		$config['allowed_types']        = $types;
		$config['max_size']             = $maxSize;

		if ($filename !== true) {
			$this->load->helper('string');
			$config['file_name'] = $filename ?? (uniqid(true) . \random_string('alnum', 16));
		}

		$this->load->library('upload');
		$this->upload->initialize($config);

		if (! $this->upload->do_upload($field)) {

			$errors = $this->upload->error_msg;

			throw new ValidationException([
				$field => implode(", ", $errors)
			]);
		}

		return $this->upload->data();
	}
}
