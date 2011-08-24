<?php
class SMSResponse extends Object {
	
	/**
	 * Whether or not the send was successful.
	 * @var boolean
	 */
	public $success = true;
	
	/**
	 * The error code, for an unsuccessful response.
	 * @var string
	 */
	public $code = null;
	
	public $method = null;
	
	public $total = null;
	
	public $time = null;
	
	public $timestamp = null;
	
	public $result = null;
	
	public $dataset = null;
	
	public $data = null;
		
}