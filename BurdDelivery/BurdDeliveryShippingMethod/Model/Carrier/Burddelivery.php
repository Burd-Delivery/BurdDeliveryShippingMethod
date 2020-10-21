<?php

namespace BurdDelivery\BurdDeliveryShippingMethod\Model\Carrier;

use DateTime;
use Exception;
use BurdDelivery\BurdDeliveryShippingMethod\Helper\CutOffTimeHelper;
use BurdDelivery\BurdDeliveryShippingMethod\Helper\MonthHelper;
use BurdDelivery\BurdDeliveryShippingMethod\APIClient\APIClient;
use BurdDelivery\BurdDeliveryShippingMethod\APIClient\APISettings;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Shipping\Model\Carrier\AbstractCarrier;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\InventorySalesAdminUi\Model\GetSalableQuantityDataBySku;
use \Magento\Quote\Model\Quote\Address\RateResult\Method;

/**
 * Burd Delivery model
 */
class Burddelivery extends AbstractCarrier implements CarrierInterface
{
    /**
     * @var string
     */
    protected $_code = 'burddelivery';

    /**
     * @var bool
     */
	protected $_isFixed = true;
	
	/**
	 * @var \Magento\Framework\Session\SessionManagerInterface
	 */
	protected $_session;

    /**
     * @var \Magento\Shipping\Model\Rate\ResultFactory
     */
    private $rateResultFactory;

    /**
     * @var \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory
     */
	private $rateMethodFactory;

	/**
	 * @var \Magento\InventorySalesAdminUi\Model\GetSalableQuantityDataBySku
	 */
	private $getSalableQuantityDataBySku;

    /**
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Shipping\Model\Rate\ResultFactory $rateResultFactory
     * @param \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory
	 * @param \Magento\InventorySalesAdminUi\Model\GetSalableQuantityDataBySku $getSalableQuantityDataBySku
	 * @param \Magento\Framework\Session\SessionManagerInterface $session
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Shipping\Model\Rate\ResultFactory $rateResultFactory,
		\Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory,
		\Magento\InventorySalesAdminUi\Model\GetSalableQuantityDataBySku $getSalableQuantityDataBySku,
		\Magento\Framework\Session\SessionManagerInterface $session,
        array $data = []
    ) {
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);

        $this->rateResultFactory = $rateResultFactory;
		$this->rateMethodFactory = $rateMethodFactory;
		$this->getSalableQuantityDataBySku = $getSalableQuantityDataBySku;
		$this->_session = $session;
    }

    /**
     * Burd Delivery Rates Collector
     *
     * @param RateRequest $request
     * @return \Magento\Shipping\Model\Rate\Result|bool
     */
    public function collectRates(RateRequest $request)
    {		
		$this->_logger->info("Burd Delivery: CollectRates");

		try
		{
			$method = $this->rateMethodFactory->create();
			$isShippingAvailable = $this->isShippingAvailable($request, $method);

			if(!$isShippingAvailable)
			{
				return false;
			}

		} catch (Exception $exception)
		{
			// exception thrown, maybe no access to the api or another error, hide the shipping.
			$this->_logger->info('Error message', ['exception' => $exception]);
			return false;
		}
	
        /** @var \Magento\Shipping\Model\Rate\Result $result */
        $result = $this->rateResultFactory->create();

        $method->setCarrier($this->_code);
        $method->setCarrierTitle($this->getConfigData('title'));
		
		// get the price set from admin
		$amount = $this->calculateShippingPrice();

		// pricing setter
		$method->setPrice($amount);
		$method->setCost($amount);
        $result->append($method);

        return $result;
	}
	
	/**
	 * Generates list of allowed carrier`s shipping methods
	 * Displays on cart price rules page
	 *
	 * @return array
	 * @api
	 */
	public function getAllowedMethods()
	{
		return array('burdcarrier' => $this->getConfigData('name'));
	}

	/**
	 * @return int|mixed
	 */
	public function deliveryDates()
	{
		$apiClient = new APIClient(new APISettings($this->getConfigData('apiBurdUsername'), $this->getConfigData('apiBurdPassword')));
		$apiClient->setBaseUrl("https://productionburdapi.azurewebsites.net");
		$apiClient->setEndPoint("/v1/DeliveryDates?take=5");
		$apiClient->setRequestType("GET");

		try {
			$this->_logger->info("Burd Delivery: Delivery dates success.");
			return json_decode($apiClient->execute());
		} catch(Exception $exception) {
			$this->_logger->info("Burd Delivery: " . $exception->getMessage());
			return [];
		}
	}


    /**
	 * Checks if the shipping method should be visible,
	 * by checking several of conditions.
	 * @param RateRequest $request
	 * @return bool
	 */
	public function isShippingAvailable(RateRequest $request, Method $method)
	{
		if($request->getPackageWeight() > $this->getConfigData('maxtotalweight')){
			return false;
		}

		$this->_logger->info("Burd Delivery: Shipping method check.");
		// the shipping method is disabled.
		if(!$this->getConfigData('active'))
		{
			$this->_logger->info("Burd Delivery: Not active.");
			return false;
		}

		$deliveryDates = $this->deliveryDates();
		if(count($deliveryDates) == 0) {
			$this->_logger->info("Burd Delivery: No delivery dates found.");
			return false;
		}

		// set carrier title...
		$method->setMethod($this->_code);
				
		$itemsInStock = 0;
		$itemsNotInStock = 0;

		// iterate through all items from the basket
			
		foreach ($request->getAllItems() as $item)
		{
			$sku = $item->getProduct()->getSku();

			if($item->getWeight() > $this->getConfigData('maxperitemweight'))
			{
				return false;
			}

			$skuQtyData = $this->getSalableQuantityDataBySku->execute($sku);

			// product is not in stock, qty is less or equal to 0.
			if($skuQtyData[0]['qty'] <= 0)
			{
				$itemsNotInStock += 1;
			}
			else
			{
				$itemsInStock += 1;
			}
		}

		if($itemsNotInStock > 0 && $itemsInStock > 0)
		{
			$method->setMethodTitle($this->getConfigData('namepartial'));
		}
		elseif($itemsNotInStock > 0)
		{			
			$method->setMethodTitle($this->getConfigData('namebackorder'));
		}
		else
		{
			$method->setMethodTitle($this->getConfigData('name'));
		}

		if(!$this->getConfigData('allowbackorderdelivery') && $itemsNotInStock > 0)
		{
			$this->_logger->info("Burd Delivery: Backorder disabled, no order placed.");
			return false;
		}

		$this->_logger->info("Burd Delivery: Stock - " . $itemsInStock . " Not in Stock - " . $itemsNotInStock);

		/**
		 * Check if the zip-code is covered for shipment.
		 * If not hide the shipping method.
		 */
		$apiClient = new APIClient(new APISettings($this->getConfigData('apiBurdUsername'), $this->getConfigData('apiBurdPassword')));
		$apiClient->setBaseUrl("https://burdecommerceapiprod.azurewebsites.net");	
		$apiClient->setEndPoint("/v1/postcode/" . $request->getDestPostcode() . '?api=1');
		$apiClient->setRequestType("GET");

		try
		{
			$apiResponse = $apiClient->execute();
		}
		catch (Exception $exception)
		{
			// exception thrown, maybe no access to the api or another error, hide the shipping.
			$this->_logger->info('Burd Delivery: Failed to get postcodes.', ['exception' => $exception]);
			return false;
		}

		// decode the json to array.
		$apiResponse = json_decode($apiResponse);

		if($apiResponse == null)
		{
			$this->_logger->info('Burd Delivery: Invalid postcode.');
			return false;
		}

		// get area from API response
		$this->_logger->info($apiResponse->{'area'});

		// get area from API response
		$cutOffConfig = $this->getConfigData('cut_off_time' . '_' . $apiResponse->{'area'});
		$cutOffConfigDelay = (int)$this->getConfigData('cut_off_time' . '_' . $apiResponse->{'area'} . '_delay');

		if(empty($cutOffConfig))
		{
			return false;
		}

		$this->_logger->info('Burd Delivery: Cut off setting - ' . $cutOffConfig . ' Delay - ' . $cutOffConfigDelay);

		$cutOffTimeHelper = new CutOffTimeHelper();
		$formatted = null;
		// iterate..
		for($i = 0; count($deliveryDates) > 0; $i++ ) {
			$datetimeObj = new \DateTime($deliveryDates[$i]);
			$formatted = $datetimeObj->format("Y-m-d");
			// false, if cut off time is exceeded.
			if (! $cutOffTimeHelper->is_cut_off_time_exceeded( $formatted, $cutOffConfig))
			{
				// apply delay if any
				if($cutOffConfigDelay > 0)
				{
					$datetimeObj = new \DateTime($deliveryDates[$i+$cutOffConfigDelay]);
					$formatted = $datetimeObj->format("Y-m-d");
				}	

				// not same day.
				if($formatted != date("Y-m-d"))
				{
					$monthHelperObject = new MonthHelper();

					if($itemsNotInStock > 0 && $itemsInStock > 0)
					{
						$method->setMethodTitle(str_replace("%deliverydate%", $datetimeObj->format("j") . ". " . $monthHelperObject->getMonthName($datetimeObj->format("n")), $this->getConfigData('namedelaypartial')));
					}
					elseif($itemsNotInStock > 0)
					{						
						$method->setMethodTitle($this->getConfigData('namebackorder'));

						// Magento 2 does not support restock date, setting forecast date in the future
						$datetimeObj = new DateTime('9999-01-01');
						$formatted = $datetimeObj->format("Y-m-d");
					}
					else
					{
						$method->setMethodTitle(str_replace("%deliverydate%", $datetimeObj->format("j") . ". " . $monthHelperObject->getMonthName($datetimeObj->format("n")), $this->getConfigData('namedelay')));
					}					
				}
				break;
			}
		}

		$utcDate = new DateTime($formatted, new \DateTimeZone("UTC"));
		$this->_session->setBurdDeliveryDate($utcDate->format(DateTime::ATOM));
		$this->_logger->info("Burd Delivery: Delivery supported.");
		return true;
	}

	/**
	 * @return false|int|string
	 */
	private function calculateShippingPrice()
	{
		if($this->getConfigData("free_shipping_amount") > 0) {

			$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
			$cart = $objectManager->get('\Magento\Checkout\Model\Cart');

			if($this->getConfigData("free_shipping_amount") <= $cart->getQuote()->getGrandTotal()) {
				return 0;
			}
		}

		return (float)$this->getConfigData('shipping_cost');
	}

}
