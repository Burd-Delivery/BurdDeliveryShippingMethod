<?php

namespace BurdDelivery\BurdDeliveryShippingMethod\APIClient;

/**
 * Class APISettings
 * @package Burd\ModuleAPI
 */
class APISettings {

	/**
	 * @var
	 */
	private $burd_api_username;

	/**
	 * @var
	 */
	private $burd_api_password;

	/**
	 * APISettings constructor.
	 *
	 * @param $api_username
	 * @param $api_password
	 */
	public function __construct($api_username, $api_password)
	{
		$this->burd_api_username = trim($api_username);
		$this->burd_api_password = trim($api_password);
	}
	
	/**
	 * Returns the basic authentication encoded as base64.
	 * @return string
	 */
	public function base64EncodedAuthentication()
	{
		return base64_encode($this->burd_api_username.":".$this->burd_api_password);
	}

}