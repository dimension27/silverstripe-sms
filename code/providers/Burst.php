<?php

/**
 * Communicate with the BurstSMS API.
 * 
 * <code>
 * $methodResponse = $burstAPI->SMS("61406xxxxx", "Hi John", "Test");
 * </code>
 */
class Burst {

	/**
	 * An instance of burstAPI.
	 * @var burstAPI
	 */
	var $api;

	/**
	 * @var array
	 */
	var $lastResponse;
	
	/**
	 * @var string
	 */
	var $lastResponseXml;

	/**
	 * Class constructor.
	 * 
	 * @param array $options An array that contains the keys apiKey and apiSecret.
	 */
	public function __construct($options) {
		$this->api = new transmitsmsAPI($options['apiKey'], $options['apiSecret']);
	}
	
	/**
	 * Calls a method of burstAPI or a method of this classes and returns the response.
	 * 
	 * In the case of burstAPI the result is parsed through self::parseResponse().
	 *
	 * @param $method
	 * @param $args
	 * @return mixed
	 *
	 * @author Alex Hayes <alex.hayes@dimension27.com>
	 */
	public function __call($method, $args = array()) {
		if(method_exists($this->api, $method)) {
			return $this->parseResponse(call_user_func_array(array($this->api, $method), $args));
		}
		if(method_exists($this, $method)) {
			return call_user_func_array(array($this, $method), $args);
		}
		trigger_error('Call to undefined function: '.__CLASS__.'::'.$method, E_USER_ERROR);
	}
	
	/**
	 * Parse XML response from BurstSMS and place them into an array
	 *
	 * Note our XML response will be similar to the following;
	 * 
	 * <code>
	 * <?xml version='1.0'?>
	 * <xml>
	 * 	 <method>messages.single</method>
	 * 	 <total>1</total>
	 * 	 <time>2010-01-27 03:41:21 GMT</time>
	 * 	 <timestamp>1264563681 GMT</timestamp>
	 * 	 <data>
	 *     <result>queued</result>
	 *     <contact_list_addition>no list provided</contact_list_addition>
	 *   </data>
	 * </xml>
	 * </code>
	 * 
	 * or...
	 * 
	 * <code>
	 * <?xml version='1.0'?>
	 * <xml>
	 *   <method>contact-lists.get</method>
	 *   <total>1</total>
	 *   <time>2010-01-27 03:53:09 GMT</time>
	 *   <timestamp>1264564389 GMT</timestamp>
	 *   <dataset>
	 *     <data>
	 *       <id>1366</id>
	 *       <name>My Test List</name>
	 *       <recipient_count>1</recipient_count>
	 *     </data>
	 *   </dataset>
	 * </xml>
	 * </code>
	 * 
	 * also
	 * 
	 * <code>
	 * <?xml version='1.0'?>
	 * <xml>
	 *   <method>messages.single</method>
	 *   <total>1</total>
	 *   <time>2011-08-23 23:15:03 GMT</time>
	 *   <timestamp>1314141303 GMT</timestamp>
	 *   <response>failed - INSUFFICIENT_CREDIT</response>
	 *</xml>
	 * </code>
	 *
	 * @param string $xmlResponse
	 * @return array
	 *
	 * @author Alex Hayes <alex.hayes@dimension27.com>
	 */
	public function parseResponse($xmlResponse) {

		$response = new SMSResponse();

		$doc = new DOMDocument();
		$doc->preserveWhiteSpace = false;
		$doc->loadXML($xmlResponse);
		$xpath = new DOMXPath($doc);
		
		$response->method    = $xpath->query('//xml/method')->item(0)->nodeValue;
		$response->total     = $xpath->query('//xml/total')->item(0)->nodeValue;
		$response->time      = $xpath->query('//xml/time')->item(0)->nodeValue;
		$response->timestamp = $xpath->query('//xml/timestamp')->item(0)->nodeValue;
		$responseNodeList = $xpath->query('//xml/data/result');
		if ($responseNodeList->length > 0) {
			$response->result = $responseNodeList->item(0)->nodeValue;
		}
		else {
			$responseNodeList = $xpath->query('//xml/response');
			if ($responseNodeList->length > 0) {
				$response->result  = $responseNodeList->item(0)->nodeValue;
			}
			else {
				throw new SMSException('Invalid response: '.$xmlResponse);
			}
		}
		$response->success = ($response->result == 'queued');
		if( stripos($response->result, 'failed') !== false ) {
			$response->code = substr($response->result, 9); // eg failed - BAD_MOBILE 
		}
		$datasetNode = $xpath->query('//xml/dataset/data');
		
		foreach($datasetNode as $item) {
			$loop = array();
			foreach($item->childNodes as $_item) {
				if($_item->nodeType == XML_ELEMENT_NODE) {
					$loop[$_item->nodeName] = $_item->nodeValue;
				}
			}
			$response->dataset[] = $loop;
		}
		
		$dataNode = $xpath->query('//xml/data');
		
		foreach($dataNode as $item) {
			$loop = array();
			foreach($item->childNodes as $_item) {
				if($_item->nodeType == XML_ELEMENT_NODE) {
					$loop[$_item->nodeName] = $_item->nodeValue;
				}
			}
			$response->data = $loop;
			break;
		}

		$this->lastResponse = $response;
		$this->lastResponseXml = $xmlResponse;
		
		return $response;

	}

	/**
	 * Add a single SMS to the system.
	 * 
	 * @param integer $mobile (international format - no spaces or +)
	 * @param string $message
	 * @param string $caller_id
	 * @param integer $sendTime (schedule - always in unix timestamp set at GMT)
	 * @param integer $autoAddContactListID (send contact list to auto add a user)
	 * @return SMSResponse
	 */
	public function send( $mobile, $message, $caller_id, $sendTime = null, $autoAddContactListID = null ) {
		return $this->SMS($mobile, $message, $caller_id, $sendTime, $autoAddContactListID);
	}

}