<?php
/**
 * @package Buckaroo
 */
class BuckarooResponseDefault extends BuckarooResponse {
	public $transactionId;
    public $order;

	protected function _parseSoapResponseChild() {
	}

	protected function _parsePostResponseChild() {
	}
}
