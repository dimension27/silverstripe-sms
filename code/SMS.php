<?php

class SMS {

	public static $send_all_messages_to = null;
	public static $log_all_messages = false;
	public static $provider;
	public static $default_country_code = 61;
	
	/**
	 * Create a new SMS object for the specified type.
	 * 
	 * Currently supported types are 'Burst' only.
	 * 
	 * @param string $type
	 * @param array $options
	 *
	 * @author Alex Hayes <alex.hayes@dimension27.com>
	 */
	static public function configure($type, $options) {
		switch($type) {
			case 'Burst':
				self::$provider = new Burst($options);
				break;
			default:
				throw new Exception('Unknown SMS provider type: ' . $type);
		}
	}

	public static function send_all_messages_to( $number ) {
		self::$send_all_messages_to = $number;
	}

	/**
	 * Disables sending of messages and logs them to error_log()
	 * @param boolean $bool
	 */
	public static function log_all_messages( $bool = true ) {
		self::$log_all_messages = $bool;
	}

	/**
	 * Returns the singleton instance of the provider class.
	 * 
	 * @return Burst
	 */
	static public function getProvider() {
		return self::$provider;
	} 
	
	/**
	 * Add a single SMS to the system, verify successful addition with response in result "queued"
	 * 
	 * Requires credit in account to process
	 *
	 * @param integer $mobile (international format - no spaces or +)
	 * @param string $message
	 * @param string $caller_id
	 * @param integer $sendTime (schedule - always in unix timestamp set at GMT)
	 * @param integer $autoAddContactListID (send contact list to auto add a user)
	 * @return xml string
	 */
	static public function send( $mobile, $message, $caller_id, $sendTime = null, $autoAddContactListID = null ) {
		if( self::$send_all_messages_to ) {
			$message = "(To: $mobile) $message";
			$mobile = self::$send_all_messages_to;
		}
		if( $mobile = self::validate_number($mobile) ) {
			if( self::$log_all_messages ) {
				error_log("Not sending SMS to '$mobile': $message");
				return true;
			}
			return self::$provider->send($mobile, $message, $caller_id, $sendTime, $autoAddContactListID);
		}
		else {
			trigger_error("Invalid mobile number '$mobile' in call to SMS::send()", E_USER_WARNING);
		}
	}

	public static function validate_number( $input, $defaultCountryCode = null ) {
		$rv = null;
		// remove anything not + or 0-9
		$number = preg_replace('/[^\+0-9]/', '', $input);
		// check that it's well formed
		if( preg_match('/^\+?(\d+)$/', $number, $matches) ) {
			// handle the default country code
			if( !$defaultCountryCode ) {
				$defaultCountryCode = self::$default_country_code;
			}
			$number = preg_replace('/^0/', $defaultCountryCode, $matches[1]);
			// check that it's 10 digits
			if( strlen($number) == 11 ) {
				$rv = $number;
			}
		}
		//* debug */ echo "$input: $rv".NL;
		return $rv;
	}

}