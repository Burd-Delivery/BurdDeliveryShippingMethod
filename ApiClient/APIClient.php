<?php
namespace BurdDelivery\BurdDeliveryShippingMethod\ApiClient;

use Exception;

class APIClient {

	/**
	 * @var
	 */
	private $api_settings;

	/**
	 * APIClient constructor.
	 *
	 * @param APISettings $api_settings
	 */
	public function __construct(APISettings $api_settings)
	{
		$this->api_settings = $api_settings;
	}

	/**
	 * @var string
	 */
	private $base_url;

	/**
	 * Request parameters.
	 * @var
	 */
	private $params;

	/****
	 *
	 * Curl configurations.
	 *
	 */

	/**
	 * @var string
	 */
	private $endpoint;

	/**
	 * @var string
	 */
	private $requestType = "GET";

	/**
	 * @var mixed
	 */
	private $data;

	/**
	 * @param string $endpoint
	 */
	public function setEndPoint($endpoint) {
		$this->endpoint = $endpoint;
	}

	/**
	 * @param string $base_url
	 */
	public function setBaseUrl($base_url) {
		$this->base_url = $base_url;
	}

	/**
	 * @param string $requestType
	 */
	public function setRequestType($requestType) {
		$this->requestType = $requestType;
	}

	/**
	 * Sets the params for the curl.
	 * @param array $param
	 */
	public function setRequestParams(array $param) {
		$this->params = $param;
	}

	/**
	 * Sets the data we´re going,
	 * to send to the external API.
	 * @param $data
	 */
	public function setData($data) {
		$this->data = $data;
	}

	/**
	 * Executes the CURL.
	 * @return mixed
	 * @throws \Exception
	 */
	public function execute() {

		// append versions to the REQUEST.
		$this->endpoint = $this->base_url.$this->endpoint . "&magento_version=2&plugin_version=1.1&platform=magento";
		$curl = curl_init();
		curl_setopt_array($curl, array(
			CURLOPT_URL => $this->endpoint,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => "",
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 20,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => $this->requestType,
			CURLOPT_POSTFIELDS => $this->data,
			CURLOPT_HTTPHEADER => array( "authorization: Basic " . $this->api_settings->base64EncodedAuthentication(),
				"content-type: application/json"),
		));

		// contains the response.
		$response = curl_exec($curl);
		// response status code
		$httpcode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);

		if(curl_error($curl)) {
			throw new Exception("Curl error...");
		}

		// check if the httpcode is in the array, if not exception.
		if(!in_array($httpcode, [204, 201, 200])) {
			throw new Exception($this->api_settings->base64EncodedAuthentication() . " Error calling the endpoint: " . $this->endpoint . "
			 response code: " . $httpcode);
		}

		// free up memory.
		curl_close($curl);
		return $response;

	}

}