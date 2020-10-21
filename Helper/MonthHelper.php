<?php

namespace BurdDelivery\BurdDeliveryShippingMethod\Helper;

class MonthHelper {
	private $months = array('januar', 'februar', 'marts', 'april', 'maj', 'juni', 'juli', 'august', 'september', 'oktober', 'november', 'december');
	public function getMonthName($index) {
		$index--; // we get the month index as 1,2,3,4
		if(!isset($this->months[$index])) {
			return "";
		}
		return $this->months[$index];
	}
}