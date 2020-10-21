<?php
namespace BurdDelivery\BurdDeliveryShippingMethod\Helper;

class CutOffTimeHelper {


	/**
	 * @param $cut_off
	 * @return bool
	 */
	public function is_cut_off_time_exceeded( $date, $cut_off ) {
		if ( ! empty( $cut_off ) ) {
			$cut_off_helper = $this->cut_off_helper( $date, $cut_off );
			if ( ! empty( $cut_off_helper['cut_off_timestamp'] ) ) {
				if ( $cut_off_helper['cut_off_timestamp'] < $cut_off_helper['now'] ) {
					return true;
				}
			}
		}
		return false;
	}
	/**
	 * @param $cut_off
	 * @param date
	 * @return array
	 */
	private function cut_off_helper($date, $cut_off ) {

		$date_utc = new \DateTime("now", new \DateTimeZone("UTC"));

		$cut_off = date($date . " " . $cut_off);

		// returning array.
		return array( 'now' => $date_utc->getTimestamp(), 'cut_off_timestamp' => (strtotime($cut_off) - 7200) );
	}

}