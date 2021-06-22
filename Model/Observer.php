<?php

namespace BurdDelivery\BurdDeliveryShippingMethod\Model;

use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\ScopeInterface;
use BurdDelivery\BurdDeliveryShippingMethod\ApiClient\APIClient;
use BurdDelivery\BurdDeliveryShippingMethod\ApiClient\APISettings;
// use Psr\Log\LoggerInterface;

/**
 * Handles when a order is been payed.
 * Class Observer
 * @package BurdDelivery\BurdDeliveryShippingMethod\Model
 */
class Observer implements ObserverInterface
{

	/**
	 * @var
	 */
	private $burd_order_number;

	/**
	 * @var LoggerInterface
	 */
	protected $logger;

	/**
	 * @var
	 */
	private $order;

	/**
	 * @var mixed
	 */
	private $store;

	/**
	 * @var
	 */
	private $storeInfo;

	/**
	 * @var \Magento\Framework\Session\SessionManagerInterface
	 */
	private $_session;

	/**
	 * @var
	 */
	protected $_scopeConfig;

	/**
	 * Observer constructor.
	 * See: https://magento.stackexchange.com/questions/125354/how-to-get-store-phone-number-in-magento-2/125357
	 * @param LoggerInterface $logger
	 */
	public function __construct(
        \Psr\Log\LoggerInterface $logger,
		\Magento\Framework\Session\SessionManagerInterface $session,
		\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig) {

        $this->logger = $logger;
		$objectManager = \Magento\Framework\App\ObjectManager::getInstance();

		$storeInformation = $objectManager->create('Magento\Store\Model\Information');

		$store = $objectManager->create('Magento\Store\Model\Store');

		$this->storeInfo = $storeInformation->getStoreInformationObject($store);
		$this->_session = $session;
		$this->_scopeConfig = $scopeConfig;
	}

    private function sendToOrderForecast()
    {
        $this->logger->info("Burd Delivery: Forecast API all.");
        $shippingAddress = $this->order->getShippingAddress();
        
		// send data
		$apiClient = new APIClient(
			new APISettings($this->_scopeConfig->getValue('carriers/burdcarrier/apiBurdUsername', ScopeInterface::SCOPE_STORE),
                $this->_scopeConfig->getValue('carriers/burdcarrier/apiBurdPassword', ScopeInterface::SCOPE_STORE) ) );
        $apiClient->setBaseUrl("https://productionburdapi.azurewebsites.net");
		$apiClient->setEndPoint( "/v1/OrderForecasts?m=1" );
		$apiClient->setRequestType( "POST" );
		$apiClient->setData( json_encode(

			array(
				'address' => $shippingAddress->getStreet()[0],
				'zipCode' => $shippingAddress->getPostcode(),
				'city'   => $shippingAddress->getCity(),
                'expectedDelivery' => $this->_session->getBurdDeliveryDate(),
                'orderNumber' => $this->order->getIncrementId()
			)

		) );

		try {
			$response = $apiClient->execute();
		} catch ( \Exception $exception ) {
			$this->logger->info("Burd Forecast Error: " . $exception->getMessage());
		}

    }
    
    private function validateBurdOrder()
    {
		try 
		{
			if (strpos($this->order->getShippingMethod(), 'burddelivery_burddelivery') === false)
			{
				$this->logger->info("Burd Delivery: Shipping is not Burd Delivery");
				return false;
			}

			// it is a burd order, send to forecast.
			$this->sendToOrderForecast();		
		}
		catch (\Throwable $exception)
		{
			// exception thrown, log and complete order
			$this->logger->info('Burd Delivery: Forecast failed.', ['exception' => $exception]);			
		}
		catch (Exception $exception)
		{
			// exception thrown, log and complete order
			$this->logger->info('Burd Delivery: Forecast failed.', ['exception' => $exception]);
		}        

		return true;
	}

	/**
	 * @param \Magento\Framework\Event\Observer $observer
	 */
	public function execute(\Magento\Framework\Event\Observer $observer)
	{
        $this->logger->info("Burd Delivery: Order placement called.");
        $this->order = $observer->getEvent()->getOrder();
        $this->validateBurdOrder();
	}

}