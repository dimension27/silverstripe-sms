<?php

/**
 * This simple class provides an example PHP interface into the Transmit SMS platform.
 * There should be little to no reason to update the transmitsmsAPIbase class unless its
 * to correct bugs.
 * 
 * Please submit any bug fixes or improvements to support@transmitsms.com with the subject
 * line of API bug fixes.
 * 
 * If you are using this file for a basis to write a connector class for another 
 * language other than php and feel like submitting it for others to use please email
 * your file to support@transmitsms.com with a subject line of API {language} example.
 * 
 * 
 * @author Nathan Bryant (nathan@beamme.info)
 * @since November 2007
 * @version 0.4
 * @package Transmit SMS API
 *
 */
class transmitsmsAPIbase {
	
	/**
	 * Set version number for checking latest version against server version
	 *
	 * @var float
	 */
	private $version = 0.4;
	
	/**
	 * Set request URL
	 *
	 * @var string
	 */
	public $requestURL = "transmitsms.com/api";
	
	/**
	 * Set request protocol
	 *
	 * @var string
	 */
	protected $requestProtocol = "http";
	
	/**
	 * Hold the authentication key
	 *
	 * @var string
	 */
	private $apiKey = null;
	
	/**
	 * Hold the authentication secret
	 *
	 * @var string
	 */
	private $apiSecret = null;
	
	/**
	 * Provide XML translation table
	 *
	 * @var array
	 */
	public $xmlTranslationTable = array(
		"&" => "&amp;", 
		"<" => "&lt;", 
		">" => "&gt;", 
		"'" => "&#39;", 
		'"' => "&quot;"
	);

	/**
	 * Limits to set
	 *
	 * @var array
	 */
	private $limits = array("max_execution_time");
	
	/**
	 * Set the maximum number of recipients to parse at once
	 *
	 * @var integer
	 */
	protected $maxRecipientCount = 1000;
	
	/**
	 * Set authentication values
	 *
	 * @param string $apiKey
	 * @param string $apiSecret
	 */
	public function __construct($apiKey, $apiSecret = "")
	{
		foreach ($this->limits as $limit) {
			ini_set($limit, 0);
		}
				
		$apiKey = trim($apiKey);
		$validKey = preg_match("/^[a-f0-9]{32}$/i", $apiKey);
		if ($validKey) {
			$this->apiKey = $apiKey;
			$this->apiSecret = $apiSecret;
		}
		else {
			trigger_error("The API key passed is not in a valid format - passed: " . $apiKey, E_USER_ERROR);
			exit;
		}
	}
	
	/**
	 * Reset any changed limits
	 *
	 */
	public function __destruct()
	{
		foreach ($this->limits as $limit) {
			ini_restore($limit);
		}
	}
		
	/**
	 * Call server methods
	 *
	 * @param string $method
	 * @param array $params
	 */
	protected function call_method($method, $params = array())	
	{
		$xml = $this->createXML($method, $params);
		// remove whitespace - compress or leave readable?
		//$xml = preg_replace("/\r\n|\n|\t/", "", $xml);
		
		$urlInfo = parse_url($this->requestProtocol . "://" . $this->requestURL);
		$port = (preg_match("/https|ssl/i", $urlInfo["scheme"])) ? 443 : 80;	
						
		// curl
		if (function_exists("curl_init")) {
			
			$ch = curl_init($this->requestURL);
			if (! $ch) {
				exit("CURL: Error connecting to the server: " . curl_errno($ch) . " : " . curl_error($ch) . "<br />");
			}			
			curl_setopt($ch, CURLOPT_AUTOREFERER, true);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, array("request" => $xml));
			curl_setopt($ch, CURLOPT_USERAGENT, "transmitSMS API: CURL PHP " . phpversion());
			curl_setopt($ch, CURLOPT_PORT, $port);
			curl_setopt($ch, CURLOPT_SSLVERSION, 3);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
			curl_setopt($ch, CURLOPT_VERBOSE, true);
			$result = curl_exec($ch);
			if (! $result) {
				exit("CURL: Problem executing request <strong>{$method}</strong> on <strong>{$_SERVER['HTTP_HOST']}</strong>, try changing above set options and re-requesting: " . curl_errno($ch) . " : " . curl_error($ch) . "<br />");		
			}
			curl_close($ch);		
			return $result;
		}
		// use file wrapper?
		elseif (ini_get("allow_url_fopen")) {
														
			$errno = null;
			$errstr = null;					
							
			$prefix = ($port == 443) ? "ssl://" : "";
			$sock = @fsockopen($prefix . $urlInfo["host"], $port, $errno, $errstr, 30);			
			
			if ($sock) {
				
				$requestStr = "request={$xml}";				
				$headers = array(
					"POST " . $urlInfo["path"] . " HTTP/1.0",
					"Host: " . $urlInfo["host"],
					"User-Agent: Transmit SMS API: PHP Socket Function " . phpversion(),
					"Content-type: application/x-www-form-urlencoded",
					"Content-length: " . strlen($requestStr),
					"Connection: close",
					"",
					$requestStr,
				);
				//exit(join("<br />", $headers) . "<hr />");
				
				$result = "";
				if (fwrite($sock, join("\r\n", $headers))) {
				 	while (!feof($sock)) {
				    	$result .= fgets($sock, 128);
				   	}
				}
			   	fclose($sock);
			   	
			   	$matches = array();
			   	preg_match("/^(.+)(\r\n|\n){2}(.+)$/ims", $result, $matches);		   			   	
			   	list($null, $responseHeaders, $null2, $response) = $matches;
			   				   	
			   	return $response;
			}			
			else {
				exit("The connection to the Transmit SMS API could not be made: $errno : $errstr");
			}
		}
		else {
			exit("The Transmit SMS API object requires the use of cURL extension, or alternatively you can use PHP's Socket Functions however you need allow_url_fopen to be set to on in php.ini");
		}
	}
	
	/**
	 * Create XML for sending to API
	 *
	 * @param string $method
	 * @param array $params
	 * @return string
	 */
	private function createXML($method, $params = array())
	{
		$xml = "<?xml version='1.0'?>\n";
		$xml .= "<request>\n";
		$xml .= "\t<interface>PHP</interface>\n";
		$xml .= "\t<version>" . $this->xmlEncode($this->version) . "</version>\n";
		$xml .= "\t<key>" . $this->xmlEncode($this->apiKey) . "</key>\n";
		$xml .= "\t<secret>" . $this->xmlEncode($this->apiSecret) . "</secret>\n";
		$xml .= "\t<method>" . $this->xmlEncode($method) . "</method>\n";		
		
		switch ($method) {
			// multiple recipients require child and parent key names
			case 'contact-lists.add-multiple-recipients' : 
				$parentKey = 'recipients'; 
				$childKey = 'recipient';
			break;
		}
		
		if (count($params)) {
			$xml .= "\t<params>\n";
			foreach ($params as $key => $value) {
				// this is a multiple element data set
				if (is_array($value)) {			
					$xml .= "\t\t<" . $parentKey . ">\n";
					foreach ($value as $childArray) {
						$xml .= "\t\t\t<{$childKey}>\n";
						foreach ($childArray as $cKey => $cValue) {
							$xml .= $this->xmlChildNode($cKey, $cValue, 4);
						}
						$xml .= "\t\t\t</{$childKey}>\n";
					}	
					$xml .= "\t\t</" . $parentKey . ">\n";				
				}
				// basic data set
				else {
					$xml .= $this->xmlChildNode($key, $value);
				}
			}		
			$xml .= "\t</params>\n";
		}
		$xml .= "</request>";			
		return $xml;
	}
	
	/**
	 * Create child node array
	 *
	 * @param string $key
	 * @param string $value
	 * @param integer $steps
	 */
	private function xmlChildNode($key, $value, $steps = 2)
	{
		if (strlen($value)) {
			$xml = str_repeat("\t", $steps) . "<{$key}>" . $this->xmlEncode($value) . "</{$key}>\n";
		}
		else {
			$xml = str_repeat("\t", $steps) . "<{$key} />\n";
		}
		return $xml;
	}
	
	/**
	 * Encode XML characters if needed
	 *
	 * @param string $str
	 * @return string
	 */
	private function xmlEncode($str)
	{
		return strtr($str, $this->xmlTranslationTable);
	}
	
	/**
	 * Decode XML characters if needed
	 *
	 * @param string $str
	 * @return string
	 */
	private function xmlDecode($str)
	{
		return strtr($str, array_flip($this->xmlTranslationTable));
	}
}








/**
 * Transmit SMS API Client
 * 
 * @author Nathan Bryant (nathan@beamme.info)
 * @copyright transmitSMS.com 2007
 *
 */
class transmitsmsAPI extends transmitsmsAPIbase {
	
	/**
	 * Set protocol to request
	 *
	 * @var string
	 */
	protected $requestProtocol = "http";
	
	/**
	 * Check the version of the API in use
	 *
	 * @return string
	 */
	public function checkVersion()
	{
		return $this->call_method("api.version");
	}
	
	/**
	 * Get users contact lists 
	 *
	 * @param integer $offset
	 * @param integer $limit
	 * @return xml string
	 */
	public function getContactLists($offset = 0, $limit = 10)
	{
		return $this->call_method("contact-lists.get", array("offset" => $offset, "limit" => $limit));
	}
	
	/**
	 * Add contact list to users account
	 *
	 * @param string $name
	 * @return xml string string
	 */
	public function addContactList($name)
	{
		return $this->call_method("contact-lists.add", array("name" => $name));
	}
	
	/**
	 * Delete contact list
	 *
	 * @param integer $id
	 * @return xml string
	 */
	public function deleteContactList($id)
	{
		return $this->call_method("contact-lists.delete", array("id" => $id));
	}
	
	/**
	 * Set / Update custom fields 
	 * 
	 * Beware this will update variable names used in messages
	 * and could potentially cause some variables not be assigned
	 * or incorrectly assigned after an update. Please see the API documentation
	 * on this item for more information.
	 *
	 * @param integer $id
	 * @param string $custom1
	 * @param string $custom2 - (optional) 
	 * @param string $custom3 - (optional)
	 * @param string $custom4 - (optional)
	 * @param string $custom5 - (optional)
	 * @param string $custom6 - (optional)
	 * @param string $custom7 - (optional)
	 * @param string $custom8 - (optional)
	 * @param string $custom9 - (optional)
	 * @param string $custom10 - (optional)
	 */
	public function setContactListCustomFields($list_id)
	{
		$params = array("id" => $list_id);
		
		$current = 1;
		$baseArgCount = 1;
		$arguments = func_get_args();
		$totalArguments = count($arguments);
		
		if ($totalArguments > $baseArgCount) {
			for ($x = $baseArgCount; $x < $totalArguments; $x ++) {
				$params["custom_{$current}"] = $arguments[$x];
				$current ++;
			}
		}
		return $this->call_method("contact-lists.update-custom-fields", $params);
	}
	
	/**
	 * Get users contact lists recipients
	 *
	 * @param integer $offset
	 * @param integer $limit
	 * @return xml string
	 */
	public function getContactListRecipients($id, $offset = 0, $limit = 10)
	{
		return $this->call_method("contact-lists.get-recipients", array("id" => $id, "offset" => $offset, "limit" => $limit));
	}
	
	/**
	 * Get users contact lists recipients who have unsubscribed
	 *
	 * @param integer $offset
	 * @param integer $limit
	 * @return xml string
	 */
	public function getContactListRecipientsUnsubscribed($id, $offset = 0, $limit = 10)
	{
		return $this->call_method("contact-lists.get-unsubscribed", array("id" => $id, "offset" => $offset, "limit" => $limit));
	}
	
	/**
	 * Add a contact 
	 *
	 * @param integer $list_id
	 * @param string $mobile (if mobile is not in international format you will need to pass the $mobileCountry to correctly format and add number).
	 * 				 - please note: the number must be passed as a string to avoid losing any preceeding zeros
	 * @param string $mobileCountry (two letter ISO code e.g. AU).
	 * @param string $firstname
	 * @param string $lastname
	 * @param string $custom1 - if you have defined custom fields you can add more parameters to ensure custom data gets added
	 * @param string $custom2 
	 * @param string $custom3 
	 * @param string $custom4 
	 * @param string $custom5 
	 * @param string $custom6 
	 * @param string $custom7 
	 * @param string $custom8 
	 * @param string $custom9 
	 * @param string $custom10
	 * @return xml string
	 */
	public function addContactListRecipient($list_id, $mobile, $mobileCountry = "", $firstname = "", $lastname = "")
	{
		$params = array(
			"list_id" => $list_id, 
			"mobile" => $mobile, 
			"firstname" => $firstname, 
			"lastname" => $lastname, 
			"mobile_dest_country" => $mobileCountry
		);
		
		$current = 1;
		$baseArgCount = 5;
		$arguments = func_get_args();
		$totalArguments = count($arguments);
		
		if ($totalArguments > $baseArgCount) {
			for ($x = $baseArgCount; $x < $totalArguments; $x ++) {
				$params["custom_{$current}_value"] = $arguments[$x];
				$current ++;
			}
		}
		
		return $this->call_method("contact-lists.add-recipient", $params);
	}
	
	/**
	 * Add multiple contacts to a recipient list
	 *
	 * @param integer $list_id
	 * @param array $recipientArray
	 * 
	 * Please note: recipient array is an array holding an associative array for a single recipient using the following format (custom fields only need to included if required) 
	 * array(
	 * 		'mobile' => '04xxxxxxx854', 
	 *  	'mobile_dest_country' => 'AU', 
	 * 		'firstname' => 'Bob', 
	 * 		'lastname' => 'builder'
	 * 	    'custom1' => 'custom variable',
	 * 		...
	 * 		'custom10' => 'custom variable' 
	 * );
	 * 
	 * @return xml string
	 */
	public function addContactListRecipientsMulti($list_id, $recipientArray = array())
	{
		if (! is_array($recipientArray) || empty($recipientArray)) {
			trigger_error("Please make sure the array passed is in the correct format", E_USER_ERROR);
			exit;
		}
						
		$params = array("list_id" => $list_id, "recipients" => $recipientArray);
		$totalRecipients = count($recipientArray);
				
		if ($totalRecipients > $this->maxRecipientCount) {
			trigger_error("You are adding more than the allowed amount of recipients, please break your recipient additions into blocks of {$this->maxRecipientCount}", E_USER_ERROR);
			exit;
		}					
		return $this->call_method("contact-lists.add-multiple-recipients", $params);
	}
	
	/**
	 * Delete a contact 
	 *
	 * @param integer $list_id
	 * @param string $mobile (mobile must be in international format - whats displayed when returning list recipients).
	 * @return xml string
	 */
	public function deleteContactListRecipient($list_id, $mobileIntFormat)
	{		
		return $this->call_method("contact-lists.delete-recipient", array("list_id" => $list_id, "mobile" => $mobileIntFormat));
	}
	
	/**
	 * Delete a contact from all contact lists
	 *
	 * @param string $mobile (mobile must be in international format - whats displayed when returning list recipients).
	 * @return xml string
	 */
	public function deleteRecipientGlobal($mobileIntFormat)
	{		
		return $this->call_method("recipient.delete-global", array("mobile" => $mobileIntFormat));
	}
	
	/**
	 * Retrieve user messages
	 *
	 * @param integer $offset
	 * @param integer $limit
	 * @return xml string
	 */
	public function getMessages($offset = 0, $limit = 10)
	{
		return $this->call_method("messages.get", array("offset" => $offset, "limit" => $limit));
	}
	
	/**
	 * Retrieve user message responses
	 *
	 * @param integer $offset
	 * @param integer $limit
	 * @return xml string
	 */
	public function getMessageResponses($message_id, $offset = 0, $limit = 10)
	{
		return $this->call_method("messages.responses", array("message_id" => $message_id, "offset" => $offset, "limit" => $limit));
	}
	
	/**
	 * Add a message to the system, verify successful addition with 
	 * response in result "queued", "added but not queued" means the message has been added
	 * but will require payment for send
	 *
	 * @param integer $list_id
	 * @param string $message
	 * @param string $caller_id
	 * @param boolean $optout
	 * @param integer $sendTime (schedule - always in unix timestamp set at GMT)
	 * @return xml string
	 */
	public function addMessage($list_id, $message, $caller_id, $optout = false, $sendTime = null)
	{
		return $this->call_method("messages.add", array("list_id" => $list_id, "message" => $message, "caller_id" => $caller_id, "optout" => false, "sendtime" => $sendTime));
	}	
	
	/**
	 * Add a single SMS to the system, verify successful addition with response in result "queued"
	 * Requires credit in account to process
	 *
	 * @param integer $mobile (international format - no spaces or +)
	 * @param string $message
	 * @param string $caller_id
	 * @param integer $sendTime (schedule - always in unix timestamp set at GMT)
	 * @param integer $autoAddContactListID (send contact list to auto add a user)
	 * @return xml string
	 */
	public function SMS($mobile, $message, $caller_id, $sendTime = null, $autoAddContactListID = null)
	{
		return $this->call_method("messages.single", array("mobile" => $mobile, "message" => $message, "caller_id" => $caller_id, "sendtime" => $sendTime, "contact_list" => $autoAddContactListID));
	}
		
	/**
	 * Add multiple SMS to the system, verify successful addition with response in result "queued"
	 * Requires credit in account to process
	 *
	 * @param integer $mobile (international format - no spaces or +, separate mobile numbers with a ,)
	 * @param string $message
	 * @param string $caller_id
	 * @param integer $sendTime (schedule - always in unix timestamp set at GMT)
	 * @param integer $autoAddContactListID (send contact list to auto add a user)
	 * @return xml string
	 */
	public function SMSMulti($mobile, $message, $caller_id, $sendTime = null, $autoAddContactListID = null)
	{
		return $this->call_method("messages.multiple", array("mobile" => $mobile, "message" => $message, "caller_id" => $caller_id, "sendtime" => $sendTime, "contact_list" => $autoAddContactListID));
	}
			
	/**
	 * Get a resellers clients (must be a reseller to do so)
	 *
	 * @param integer $offset
	 * @param integer $limit
	 * @return xml string
	 */
	public function getClients($offset = 0, $limit = 10)
	{
		return $this->call_method("clients.get", array("offset" => $offset, "limit" => $limit));
	}		
	
	/**
	 * Get a resellers clients (must be a reseller to do so)
	 *
	 * @param integer $offset
	 * @param integer $limit
	 * @return xml string
	 */
	public function getClientsFull($offset = 0, $limit = 10)
	{
		return $this->call_method("clients.get-full", array("offset" => $offset, "limit" => $limit));
	}
				
	/**
	 * Get a resellers clients (must be a reseller to do so)
	 *
	 * @param integer $id
	 * @return xml string
	 */
	public function getClientByID($id)
	{
		return $this->call_method("clients.get-by-id", array("id" => $id));
	}
			
	/**
	 * Add a client to a reseller account (must be a reseller)
	 *
	 * @param string $name
	 * @param string $email
	 * @param string (6 or more) $password
	 * @param phone number $phone
	 * @param string $reseller_pays (reseller will be billed for all client sends)
	 * @param float $reseller_markup - charge per sms to add to clients cost (e.g. 2.1 = add 2.1 cents to SMS cost)
	 * @return xml string
	 */
	public function addClient($name, $email, $password, $phone, $reseller_pays = 'no', $reseller_markup = 0)
	{
		return $this->call_method("clients.add", array("name" => $name, "email" => $email, "password" => $password, "phone" => $phone, "reseller_pays" => $reseller_pays, "reseller_markup" => $reseller_markup));
	}
	
	/**
	 * Retrieve a users transactions
	 *
	 * @param integer $offset
	 * @param integer $limit
	 * @param date $dateStart (Y-m-d H:i:s)
	 * @param date $dateEnd (Y-m-d H:i:s)
	 * @return xml string
	 */
	public function getTransactions($offset = 0, $limit = 10, $dateStart = null, $dateEnd = null)
	{
		return $this->call_method("transactions.get", array("offset" => $offset, "limit" => $limit, "dateStart" => $dateStart, "dateEnd" => $dateEnd));		
	}
	
	/**
	 * Retrieve a users transactions
	 *
	 * @param integer $user_id
	 * @param integer $offset
	 * @param integer $limit
	 * @param date $dateStart (Y-m-d H:i:s)
	 * @param date $dateEnd (Y-m-d H:i:s)
	 * @return xml string
	 */
	public function getTransactionsForUser($user_id, $offset = 0, $limit = 10, $dateStart = null, $dateEnd = null)
	{
		return $this->call_method("transactions.get-users", array("user_id" => $user_id, "offset" => $offset, "limit" => $limit, "dateStart" => $dateStart, "dateEnd" => $dateEnd));		
	}
	
	/**
	 * Retrieve a users transactions
	 *
	 * @param integer $user_id
	 * @param integer $offset
	 * @param integer $limit
	 * @param date $dateStart (Y-m-d H:i:s)
	 * @param date $dateEnd (Y-m-d H:i:s)
	 * @return xml string
	 */
	public function getTransactionsForClients($offset = 0, $limit = 10, $dateStart = null, $dateEnd = null)
	{
		return $this->call_method("transactions.get-clients", array("offset" => $offset, "limit" => $limit, "dateStart" => $dateStart, "dateEnd" => $dateEnd));		
	}
	
	/**
	 * Retrieve a users transactions
	 *
	 * @param integer $id
	 * @return xml string
	 */
	public function getTransactionDetail($id)
	{
		return $this->call_method("transactions.get-detail", array("id" => $id));		
	}		
}

/**
 * Api Usage Examples

$transmitsmsAPI = new transmitsmsAPI("YOUR API KEY", "YOUR API SECRET");

// examples - all users
$methodResponse = $transmitsmsAPI->getContactLists(0, 10);
$methodResponse = $transmitsmsAPI->getContactListRecipients(175, 0, 10);
$methodResponse = $transmitsmsAPI->getContactListRecipientsUnsubscribed(175, 0, 10);
$methodResponse = $transmitsmsAPI->addContactListRecipient(175, "04163xxxxx", "AU", "John", "Doe");
$methodResponse = $transmitsmsAPI->addContactListRecipient(175, "614163xxxxx", "", "John", "Doe");
$methodResponse = $transmitsmsAPI->addContactListRecipientsMulti(175, array(
	array("mobile" => '614163xxxxx', 'mobile_dest_country' => '', 'firstname' => 'John', 'lastname' => 'Doe', 'custom1' => 'whatever'),
	array("mobile" => '04166xxxxx', 'mobile_dest_country' => 'AU', 'firstname' => 'Jane', 'lastname' => 'Doe'),
));
$methodResponse = $transmitsmsAPI->deleteContactListRecipient(175, "61406xxxxx");
$methodResponse = $transmitsmsAPI->deleteRecipientGlobal('6140xxxxx');
$methodResponse = $transmitsmsAPI->getMessages(0, 4);
$methodResponse = $transmitsmsAPI->addMessage(20, "This is a message being added to one of my lists", "John");
$methodResponse = $transmitsmsAPI->SMS("61406xxxxx", "Hi John", "Test");
$methodResponse = $transmitsmsAPI->getTransactionsDailyActivity(0, 0, null, null);
$methodResponse = $transmitsmsAPI->getTransactionsDailyActivityForUser(150, 0, 0, null, null);
$methodResponse = $transmitsmsAPI->getTransactions(0, 0, null, null);
$methodResponse = $transmitsmsAPI->getTransactionsForUser(150, 0, 0, null, null);
$methodResponse = $transmitsmsAPI->getTransactionsForClients(0, 0, null, null);
$methodResponse = $transmitsmsAPI->getTransactionDetail(1);

// examples - reseller specific
$methodResponse = $transmitsmsAPI->getClients(0, 10);
$methodResponse = $transmitsmsAPI->getClientsFull(0, 10);
$methodResponse = $transmitsmsAPI->getClientByID(150);
$methodResponse = $transmitsmsAPI->addClient('John Doe', "john@doe.com", "password", "614824xxxxxx");


$xml = @simplexml_load_string($methodResponse);
if (! $xml) { 
	exit(date("y-m-d H:i:s") . " - Problem with request : " . $methodResponse);
}

echo "<pre>";
print_r($xml);
echo "</pre>";
exit;

*/

?>