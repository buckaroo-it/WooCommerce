<?php
/**
 * @package Buckaroo
 */
class WC_Push_Buckaroo extends WC_Gateway_Buckaroo {

	public function __construct() {
		parent::__construct();
		fn_buckaroo_process_response_push( $this );
		exit;
	}
}
